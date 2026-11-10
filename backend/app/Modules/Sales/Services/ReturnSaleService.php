<?php

namespace App\Modules\Sales\Services;

use App\Modules\Cash\Models\Shift;
use App\Modules\Cash\Services\RecordCashMovementService;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\PaymentMethod;
use App\Modules\Inventory\Services\RecordStockMovementService;
use App\Modules\Sales\Models\ReturnCounter;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleReturn;
use App\Modules\Sales\Models\SaleReturnItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReturnSaleService
{
    protected RecordCashMovementService $recordCashMovementService;
    protected RecordStockMovementService $recordStockMovementService;

    public function __construct(
        RecordCashMovementService $recordCashMovementService,
        RecordStockMovementService $recordStockMovementService
    ) {
        $this->recordCashMovementService = $recordCashMovementService;
        $this->recordStockMovementService = $recordStockMovementService;
    }

    public function execute(int $saleId, array $data, int $userId): SaleReturn
    {
        return DB::transaction(function () use ($saleId, $data, $userId) {
            /** @var Sale|null $sale */
            $sale = Sale::with(['items'])
                ->where('id', $saleId)
                ->lockForUpdate()
                ->first();

            if (!$sale) {
                throw new HttpException(404, 'Transaksi penjualan tidak ditemukan.');
            }

            if ($sale->status !== 'completed') {
                throw new HttpException(422, 'Hanya transaksi berstatus completed yang dapat diretur.');
            }

            $saleItems = $sale->items->keyBy('id');

            // Hitung total qty yang sudah pernah diretur sebelumnya per sale_item_id
            $alreadyReturned = SaleReturnItem::whereIn('sale_item_id', $saleItems->keys())
                ->selectRaw('sale_item_id, SUM(qty) as total_returned_qty')
                ->groupBy('sale_item_id')
                ->pluck('total_returned_qty', 'sale_item_id');

            $refundTotal = 0;
            $returnItemsPayload = [];

            foreach ($data['items'] as $reqItem) {
                $saleItemId = (int) $reqItem['sale_item_id'];
                $saleItem = $saleItems->get($saleItemId);

                if (!$saleItem) {
                    throw new HttpException(422, "Item transaksi dengan ID {$saleItemId} tidak ditemukan pada struk ini.");
                }

                $qtyToReturn = (float) $reqItem['qty'];
                $prevReturned = (float) ($alreadyReturned->get($saleItemId) ?? 0);
                $maxReturnable = (float) $saleItem->qty - $prevReturned;

                if ($qtyToReturn > $maxReturnable) {
                    throw new HttpException(
                        422,
                        "Jumlah retur untuk '{$saleItem->name_snapshot}' ({$qtyToReturn}) melebihi sisa yang bisa diretur ({$maxReturnable})."
                    );
                }

                // Jika nominal refund per item tidak dikirim, hitung proporsional dari line_total
                $itemRefundAmount = isset($reqItem['amount'])
                    ? (int) $reqItem['amount']
                    : (int) round(($saleItem->line_total / (float) $saleItem->qty) * $qtyToReturn);

                $refundTotal += $itemRefundAmount;

                $returnItemsPayload[] = [
                    'sale_item' => $saleItem,
                    'sale_item_id' => $saleItemId,
                    'qty' => $qtyToReturn,
                    'amount' => $itemRefundAmount,
                    'restock' => (bool) ($reqItem['restock'] ?? true),
                ];
            }

            // Generate Nomor Retur Atomik Harian per Outlet menggunakan ReturnCounter
            $today = Carbon::now('Asia/Jakarta')->toDateString();

            // Pastikan baris counter hari ini tersedia tanpa race condition saat inisialisasi awal hari
            DB::table('return_counters')->insertOrIgnore([
                'outlet_id' => $sale->outlet_id,
                'counter_date' => $today,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var ReturnCounter $counter */
            $counter = ReturnCounter::where('outlet_id', $sale->outlet_id)
                ->where('counter_date', $today)
                ->lockForUpdate()
                ->first();

            $counter->increment('last_number');
            $returnNo = sprintf('RET-%s-%05d', str_replace('-', '', $today), $counter->last_number);

            // 1. Simpan Header Sale Return
            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'return_no' => $returnNo,
                'reason' => $data['reason'],
                'refund_method_id' => $data['refund_method_id'],
                'refund_total' => $refundTotal,
                'approved_by' => $data['approved_by'] ?? $userId,
                'user_id' => $userId,
            ]);

            // 2. Simpan Detail Item Retur
            foreach ($returnItemsPayload as $row) {
                SaleReturnItem::create([
                    'return_id' => $saleReturn->id,
                    'sale_item_id' => $row['sale_item_id'],
                    'qty' => $row['qty'],
                    'amount' => $row['amount'],
                    'restock' => $row['restock'],
                ]);
            }

            // 3. Kembalikan Stok ke Inventory jika restock = true (Urutkan variant_id menaik untuk cegah deadlock)
            $restockItems = collect($returnItemsPayload)
                ->where('restock', true)
                ->sortBy(fn ($r) => $r['sale_item']->variant_id)
                ->values();

            if ($restockItems->isNotEmpty()) {
                $variantIds = $restockItems->map(fn ($r) => $r['sale_item']->variant_id)->unique();
                $variants = ProductVariant::with('product')
                    ->whereIn('id', $variantIds)
                    ->get()
                    ->keyBy('id');

                $variantUnits = DB::table('variant_units')
                    ->whereIn('variant_id', $variantIds)
                    ->get()
                    ->keyBy(fn ($u) => $u->variant_id . '_' . $u->unit_id);

                foreach ($restockItems as $row) {
                    $saleItem = $row['sale_item'];
                    $variant = $variants->get($saleItem->variant_id);

                    if ($variant && $variant->product && $variant->product->track_stock) {
                        $factor = 1.0;
                        if ((int) $saleItem->unit_id !== (int) $variant->base_unit_id) {
                            $unitKey = $saleItem->variant_id . '_' . $saleItem->unit_id;
                            if ($variantUnits->has($unitKey)) {
                                $factor = (float) $variantUnits->get($unitKey)->factor;
                            }
                        }

                        $baseQtyToRestore = (float) $row['qty'] * $factor;
                        $unitCost = (int) ($saleItem->cost_snapshot ?? $variant->cost_price ?? 0);

                        $this->recordStockMovementService->execute(
                            $sale->outlet_id,
                            (int) $saleItem->variant_id,
                            'return',
                            $baseQtyToRestore,
                            $unitCost,
                            $userId,
                            'sale_returns',
                            $saleReturn->id,
                            "Retur #{$saleReturn->return_no} (Struk #{$sale->receipt_no})"
                        );
                    }
                }
            }

            // 4. Jika Refund menggunakan Tunai (cash), potong kas laci pada Shift Aktif
            /** @var PaymentMethod|null $refundMethod */
            $refundMethod = PaymentMethod::find($data['refund_method_id']);
            if ($refundMethod && $refundMethod->type === 'cash' && $refundTotal > 0) {
                /** @var Shift|null $activeShift */
                $activeShift = Shift::where('outlet_id', $sale->outlet_id)
                    ->where('status', 'open')
                    ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$userId])
                    ->lockForUpdate()
                    ->first();

                if ($activeShift) {
                    $this->recordCashMovementService->execute(
                        $activeShift->id,
                        'refund_cash',
                        $refundTotal,
                        "Refund Retur #{$saleReturn->return_no} (Struk #{$sale->receipt_no})",
                        $userId,
                        $sale->id,
                        'return'
                    );
                }
            }

            return $saleReturn->load(['items.saleItem', 'refundMethod', 'user']);
        });
    }
}