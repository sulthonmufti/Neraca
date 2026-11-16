<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Models\StockOpnameCounter;
use App\Modules\Inventory\Models\StockOpnameItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateStockOpnameService
{
    protected RecordStockMovementService $recordStockMovementService;

    public function __construct(RecordStockMovementService $recordStockMovementService)
    {
        $this->recordStockMovementService = $recordStockMovementService;
    }

    public function execute(array $data, int $userId, int $tenantId): StockOpname
    {
        return DB::transaction(function () use ($data, $userId, $tenantId) {
            $outletId = (int) $data['outlet_id'];
            $today = Carbon::now('Asia/Jakarta')->toDateString();

            //Generate Nomor Dokumen Opname Atomik per Outlet
            DB::table('stock_opname_counters')->insertOrIgnore([
                'outlet_id' => $outletId,
                'counter_date' => $today,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var StockOpnameCounter $counter */
            $counter = StockOpnameCounter::where('outlet_id', $outletId)
                ->where('counter_date', $today)
                ->lockForUpdate()
                ->first();

            $counter->increment('last_number');
            $opnameNo = sprintf('OPN-%s-%05d', str_replace('-', '', $today), $counter->last_number);

            //Urutkan item berdasarkan variant_id ascending untuk cegah deadlock
            $sortedItems = collect($data['items'])->sortBy('variant_id')->values();
            $variantIds = $sortedItems->pluck('variant_id')->unique();

            $variants = ProductVariant::with('product')
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

            //Simpan Header Stock Opname
            $stockOpname = StockOpname::create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'opname_no' => $opnameNo,
                'status' => 'completed',
                'note' => $data['note'] ?? null,
                'opname_at' => $data['opname_at'] ?? now(),
                'user_id' => $userId,
            ]);

            //Proses setiap item opname dan sesuaikan stok jika ada selisih
            foreach ($sortedItems as $item) {
                $variantId = (int) $item['variant_id'];
                $physicalQty = round((float) $item['physical_qty'], 3);

                /** @var ProductVariant|null $variant */
                $variant = $variants->get($variantId);
                if (!$variant) {
                    throw new HttpException(422, "Varian produk ID {$variantId} tidak ditemukan.");
                }

                //Pastikan baris stock_levels tersedia lalu kunci untuk membaca system_qty yang akurat
                DB::table('stock_levels')->insertOrIgnore([
                    'outlet_id' => $outletId,
                    'variant_id' => $variantId,
                    'qty' => 0,
                    'reorder_point' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stockLevel = StockLevel::where('outlet_id', $outletId)
                    ->where('variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                $systemQty = round((float) ($stockLevel->qty ?? 0), 3);
                $diffQty = round($physicalQty - $systemQty, 3);
                $unitCost = (int) ($variant->cost_price ?? 0);

                StockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'variant_id' => $variantId,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'diff_qty' => $diffQty,
                    'unit_cost' => $unitCost,
                    'reason' => $item['reason'] ?? null,
                ]);

                // Jika terdapat selisih antara fisik dan sistem, catat ke ledger stock_movements
                if (abs($diffQty) > 0.0001 && $variant->product && $variant->product->track_stock) {
                    $reasonNote = !empty($item['reason']) ? " ({$item['reason']})" : '';
                    $this->recordStockMovementService->execute(
                        $outletId,
                        $variantId,
                        'opname',
                        $diffQty,
                        $unitCost,
                        $userId,
                        'stock_opnames',
                        $stockOpname->id,
                        "Stok Opname #{$stockOpname->opname_no}{$reasonNote}"
                    );
                }
            }

            return $stockOpname->load(['items.variant', 'user', 'outlet']);
        });
    }
}