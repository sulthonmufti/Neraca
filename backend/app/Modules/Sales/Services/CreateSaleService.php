<?php

namespace App\Modules\Sales\Services;

use App\Modules\Cash\Models\Shift;
use App\Modules\Cash\Services\RecordCashMovementService;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\PaymentMethod;
use App\Modules\Inventory\Services\RecordStockMovementService;
use App\Modules\Sales\Models\ReceiptCounter;
use App\Modules\Sales\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateSaleService
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

    public function execute(array $data, int $cashierId, int $tenantId): Sale
    {
        return DB::transaction(function () use ($data, $cashierId, $tenantId) {
            $outletId = $data['outlet_id'];
            $today = Carbon::now('Asia/Jakarta')->toDateString();

            //Cari Shift Aktif (Berdasarkan shift_id dari request atau shift open milik kasir di outlet ini)
            /** @var Shift|null $shift */
            $shift = null;
            if (!empty($data['shift_id'])) {
                /** @var Shift|null $shift */
                $shift = Shift::where('id', $data['shift_id'])
                    ->where('outlet_id', $outletId)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();

                if (!$shift) {
                    throw new HttpException(422, 'Shift yang dipilih tidak ditemukan atau sudah ditutup.');
                }
            } else {
                /** @var Shift|null $shift */
                $shift = Shift::where('outlet_id', $outletId)
                    ->where('user_id', $cashierId)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
            }

            //Generate Nomor Struk Atomik Harian per Outlet
            /** @var ReceiptCounter $counter */
            $counter = ReceiptCounter::lockForUpdate()->firstOrCreate(
                ['outlet_id' => $outletId, 'counter_date' => $today],
                ['last_number' => 0]
            );
            $counter->increment('last_number');
            $receiptNo = sprintf('INV-%s-%05d', str_replace('-', '', $today), $counter->last_number);

            //Hitung Total & Grand Total dari Payload
            $subtotal = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                $lineTotal = ($item['qty'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'variant_id' => $item['variant_id'],
                    'unit_id' => $item['unit_id'],
                    'name_snapshot' => $item['name_snapshot'],
                    'sku_snapshot' => $item['sku_snapshot'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_snapshot' => $item['cost_snapshot'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'line_total' => $lineTotal,
                    'modifiers' => json_encode($item['modifiers'] ?? null),
                    'note' => $item['note'] ?? null,
                    'kitchen_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $discountTotal = $data['discount_total'] ?? 0;
            $taxTotal = $data['tax_total'] ?? 0;
            $serviceTotal = $data['service_total'] ?? 0;
            $rounding = $data['rounding'] ?? 0;

            $grandTotal = ($subtotal - $discountTotal) + $taxTotal + $serviceTotal + $rounding;

            $paidTotal = 0;
            foreach ($data['payments'] as $payment) {
                $paidTotal += $payment['amount'];
            }

            $changeTotal = max(0, $paidTotal - $grandTotal);

            //Simpan Header Transaksi (Sales)
            $sale = Sale::create([
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'outlet_id' => $outletId,
                'shift_id' => $shift?->id,
                'receipt_no' => $receiptNo,
                'cashier_id' => $cashierId,
                'member_id' => $data['member_id'] ?? null,
                'order_type' => $data['order_type'] ?? 'dine_in',
                'table_id' => $data['table_id'] ?? null,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'service_total' => $serviceTotal,
                'rounding' => $rounding,
                'grand_total' => $grandTotal,
                'paid_total' => $paidTotal,
                'change_total' => $changeTotal,
                'note' => $data['note'] ?? null,
                'sold_at' => $data['sold_at'] ?? now(),
            ]);

            //Simpan Sale Items
            foreach ($itemsData as &$itemRecord) {
                $itemRecord['sale_id'] = $sale->id;
            }
            DB::table('sale_items')->insert($itemsData);

            //Potong Stok & Catat Ledger Inventory (Urutkan variant_id menaik untuk mencegah deadlock)
            $sortedItems = collect($data['items'])->sortBy('variant_id')->values();
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
                $variant = $variants->get($item['variant_id']);

                // Hanya potong stok jika produk mengaktifkan track_stock
                if ($variant && $variant->product && $variant->product->track_stock) {
                    $factor = 1.0;

                    // Jika satuan jual bukan satuan dasar, kalikan dengan faktor konversi variant_units
                    if ((int) $item['unit_id'] !== (int) $variant->base_unit_id) {
                        $unitKey = $item['variant_id'] . '_' . $item['unit_id'];
                        if ($variantUnits->has($unitKey)) {
                            $factor = (float) $variantUnits->get($unitKey)->factor;
                        }
                    }

                    $baseQtyToDeduct = -1 * ((float) $item['qty'] * $factor);
                    $unitCost = (int) ($item['cost_snapshot'] ?? $variant->cost_price ?? 0);

                    $this->recordStockMovementService->execute(
                        $outletId,
                        (int) $item['variant_id'],
                        'sale',
                        $baseQtyToDeduct,
                        $unitCost,
                        $cashierId,
                        'sales',
                        $sale->id,
                        "Penjualan #{$sale->receipt_no}"
                    );
                }
            }

            //Simpan Sale Payments (Split Payment Support) & Hitung Aliran Kas Shift
            $paymentMethodIds = collect($data['payments'])->pluck('payment_method_id')->unique();
            $paymentMethods = PaymentMethod::whereIn('id', $paymentMethodIds)->get()->keyBy('id');

            $cashReceived = 0;
            $nonCashTotal = 0;

            foreach ($data['payments'] as $payment) {
                $sale->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'gateway_payload' => $payment['gateway_payload'] ?? null,
                ]);

                $method = $paymentMethods->get($payment['payment_method_id']);
                if ($method && $method->type === 'cash') {
                    $cashReceived += $payment['amount'];
                } else {
                    $nonCashTotal += $payment['amount'];
                }
            }

            //Jika ada Shift Aktif, catat Ledger Cash Movement & Non-Cash Sales
            if ($shift) {
                $netCash = max(0, $cashReceived - $changeTotal);

                if ($netCash > 0) {
                    $this->recordCashMovementService->execute(
                        $shift->id,
                        'sale_cash',
                        $netCash,
                        "Penjualan Tunai #{$sale->receipt_no}",
                        $cashierId,
                        $sale->id,
                        'sale'
                    );
                }

                if ($nonCashTotal > 0) {
                    $shift->increment('total_non_cash_sales', $nonCashTotal);
                }
            }

            return $sale->load(['items', 'payments', 'cashier']);
        });
    }
}