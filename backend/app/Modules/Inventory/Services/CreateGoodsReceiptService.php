<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Models\GoodsReceiptCounter;
use App\Modules\Inventory\Models\GoodsReceiptItem;
use App\Modules\Inventory\Models\StockLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateGoodsReceiptService
{
    protected RecordStockMovementService $recordStockMovementService;

    public function __construct(RecordStockMovementService $recordStockMovementService)
    {
        $this->recordStockMovementService = $recordStockMovementService;
    }

    public function execute(array $data, int $userId, int $tenantId): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $userId, $tenantId) {
            $outletId = (int) $data['outlet_id'];
            $today = Carbon::now('Asia/Jakarta')->toDateString();

            //Generate Nomor Dokumen GRN Atomik per Outlet (insertOrIgnore + lockForUpdate)
            DB::table('goods_receipt_counters')->insertOrIgnore([
                'outlet_id' => $outletId,
                'counter_date' => $today,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var GoodsReceiptCounter $counter */
            $counter = GoodsReceiptCounter::where('outlet_id', $outletId)
                ->where('counter_date', $today)
                ->lockForUpdate()
                ->first();

            $counter->increment('last_number');
            $receiptNo = sprintf('GRN-%s-%05d', str_replace('-', '', $today), $counter->last_number);

            //Urutkan item berdasarkan variant_id secara ascending untuk mencegah deadlock
            $sortedItems = collect($data['items'])->sortBy('variant_id')->values();
            $variantIds = $sortedItems->pluck('variant_id')->unique();

            $variants = ProductVariant::with('product')
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variantUnits = DB::table('variant_units')
                ->whereIn('variant_id', $variantIds)
                ->get()
                ->keyBy(fn ($u) => $u->variant_id . '_' . $u->unit_id);

            $totalCost = 0;
            $preparedItems = [];

            foreach ($sortedItems as $item) {
                $variantId = (int) $item['variant_id'];
                $unitId = (int) $item['unit_id'];
                $qty = (float) $item['qty'];
                $unitCost = (int) $item['unit_cost'];

                /** @var ProductVariant|null $variant */
                $variant = $variants->get($variantId);
                if (!$variant) {
                    throw new HttpException(422, "Varian produk ID {$variantId} tidak ditemukan.");
                }

                // Cek faktor konversi satuan
                $factor = 1.0;
                if ($unitId !== (int) $variant->base_unit_id) {
                    $unitKey = $variantId . '_' . $unitId;
                    if (!$variantUnits->has($unitKey)) {
                        throw new HttpException(
                            422,
                            "Satuan unit ID {$unitId} belum terdaftar pada konversi satuan untuk '{$variant->name}'."
                        );
                    }
                    $factor = (float) $variantUnits->get($unitKey)->factor;
                }

                $baseQty = $qty * $factor;
                $lineTotal = (int) round($qty * $unitCost);
                $baseUnitCost = $factor > 0 ? (int) round($unitCost / $factor) : $unitCost;

                $totalCost += $lineTotal;

                $preparedItems[] = [
                    'variant' => $variant,
                    'variant_id' => $variantId,
                    'unit_id' => $unitId,
                    'qty' => $qty,
                    'conversion_factor' => $factor,
                    'base_qty' => $baseQty,
                    'unit_cost' => $unitCost,
                    'base_unit_cost' => $baseUnitCost,
                    'line_total' => $lineTotal,
                    'batch_no' => $item['batch_no'] ?? null,
                    'expired_date' => $item['expired_date'] ?? null,
                ];
            }

            //Simpan Header Goods Receipt
            $goodsReceipt = GoodsReceipt::create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'receipt_no' => $receiptNo,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'status' => 'completed',
                'total_cost' => $totalCost,
                'note' => $data['note'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'user_id' => $userId,
            ]);

            //Simpan Detail Item, Update HPP Moving Average, & Catat Ledger Stok
            foreach ($preparedItems as $row) {
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $goodsReceipt->id,
                    'variant_id' => $row['variant_id'],
                    'unit_id' => $row['unit_id'],
                    'qty' => $row['qty'],
                    'conversion_factor' => $row['conversion_factor'],
                    'base_qty' => $row['base_qty'],
                    'unit_cost' => $row['unit_cost'],
                    'base_unit_cost' => $row['base_unit_cost'],
                    'line_total' => $row['line_total'],
                    'batch_no' => $row['batch_no'],
                    'expired_date' => $row['expired_date'],
                ]);

                /** @var ProductVariant $variant */
                $variant = $row['variant'];

                // Hitung Moving Average Cost sebelum stok ditambah
                $currentStockRow = StockLevel::where('outlet_id', $outletId)
                    ->where('variant_id', $variant->id)
                    ->first();

                $oldQty = $currentStockRow ? max(0.0, (float) $currentStockRow->qty) : 0.0;
                $oldCost = (int) ($variant->cost_price ?? 0);
                $incomingBaseQty = (float) $row['base_qty'];
                $incomingBaseCost = (int) $row['base_unit_cost'];

                if ($oldQty + $incomingBaseQty > 0) {
                    $newMovingAvgCost = (int) round(
                        (($oldQty * $oldCost) + ($incomingBaseQty * $incomingBaseCost)) / ($oldQty + $incomingBaseQty)
                    );
                    $variant->update(['cost_price' => $newMovingAvgCost]);
                }

                // Catat ke Ledger Stok jika produk melacak stok (track_stock = true)
                if ($variant->product && $variant->product->track_stock) {
                    $this->recordStockMovementService->execute(
                        $outletId,
                        $variant->id,
                        'receipt',
                        $incomingBaseQty,
                        $incomingBaseCost,
                        $userId,
                        'goods_receipts',
                        $goodsReceipt->id,
                        "Penerimaan Barang #{$goodsReceipt->receipt_no}"
                    );
                }
            }

            return $goodsReceipt->load(['supplier', 'items.variant', 'items.unit', 'user']);
        });
    }
}