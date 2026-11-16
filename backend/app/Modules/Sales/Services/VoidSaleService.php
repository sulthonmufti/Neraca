<?php

namespace App\Modules\Sales\Services;

use App\Modules\Cash\Models\Shift;
use App\Modules\Cash\Services\RecordCashMovementService;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\PaymentMethod;
use App\Modules\Inventory\Services\RecordStockMovementService;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VoidSaleService
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

    public function execute(int $saleId, array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($saleId, $data, $userId) {
            /** @var Sale|null $sale */
            $sale = Sale::with(['items', 'payments', 'shift'])
                ->where('id', $saleId)
                ->lockForUpdate()
                ->first();

            if (!$sale) {
                throw new HttpException(404, 'Transaksi penjualan tidak ditemukan.');
            }

            if ($sale->status === 'voided') {
                throw new HttpException(422, 'Transaksi ini sudah pernah dibatalkan (void).');
            }

            //Perbarui status transaksi menjadi voided
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $data['void_reason'],
                'voided_by' => $userId,
                'approved_by' => $data['approved_by'] ?? $userId,
            ]);

            //Kembalikan Stok (Inventory Ledger tipe 'sale_void')
            $sortedItems = collect($sale->items)->sortBy('variant_id')->values();
            $variantIds = $sortedItems->pluck('variant_id')->unique();

            $variants = ProductVariant::with('product')
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

            $variantUnits = DB::table('variant_units')
                ->whereIn('variant_id', $variantIds)
                ->get()
                ->keyBy(fn ($row) => $row->variant_id . '_' . $row->unit_id);

            foreach ($sortedItems as $item) {
                $variant = $variants->get($item->variant_id);

                if ($variant && $variant->product && $variant->product->track_stock) {
                    $factor = 1.0;
                    if ((int) $item->unit_id !== (int) $variant->base_unit_id) {
                        $unitKey = $item->variant_id . '_' . $item->unit_id;
                        if ($variantUnits->has($unitKey)) {
                            $factor = (float) $variantUnits->get($unitKey)->factor;
                        }
                    }

                    $qtyToRestore = (float) $item->qty * $factor; // Positif untuk mengembalikan stok
                    $unitCost = (int) ($item->cost_snapshot ?? $variant->cost_price ?? 0);

                    $this->recordStockMovementService->execute(
                        $sale->outlet_id,
                        (int) $item->variant_id,
                        'sale_void',
                        $qtyToRestore,
                        $unitCost,
                        $userId,
                        'sales',
                        $sale->id,
                        "Void Transaksi #{$sale->receipt_no}"
                    );
                }
            }

            //Balikkan Aliran Kas Laci (Cash Ledger tipe 'refund_cash') jika shift masih open
            if ($sale->shift_id) {
                /** @var Shift|null $shift */
                $shift = Shift::where('id', $sale->shift_id)->lockForUpdate()->first();

                if ($shift && $shift->status === 'open') {
                    $paymentMethodIds = $sale->payments->pluck('payment_method_id')->unique();
                    $paymentMethods = PaymentMethod::whereIn('id', $paymentMethodIds)->get()->keyBy('id');

                    $cashPaid = 0;
                    $nonCashTotal = 0;

                    foreach ($sale->payments as $payment) {
                        $method = $paymentMethods->get($payment->payment_method_id);
                        if ($method && $method->type === 'cash') {
                            $cashPaid += $payment->amount;
                        } else {
                            $nonCashTotal += $payment->amount;
                        }
                    }

                    // Uang tunai bersih yang masuk saat transaksi dulu
                    $netCash = max(0, $cashPaid - $sale->change_total);

                    if ($netCash > 0) {
                        $this->recordCashMovementService->execute(
                            $shift->id,
                            'refund_cash',
                            $netCash,
                            "Void Transaksi #{$sale->receipt_no}",
                            $userId,
                            $sale->id,
                            'void'
                        );
                    }

                    if ($nonCashTotal > 0) {
                        $shift->decrement('total_non_cash_sales', $nonCashTotal);
                    }
                }
            }

            return $sale->fresh(['items', 'payments']);
        });
    }
}