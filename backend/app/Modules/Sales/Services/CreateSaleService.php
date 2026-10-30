<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\ReceiptCounter;
use App\Modules\Sales\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSaleService
{
    public function execute(array $data, int $cashierId, int $tenantId): Sale
    {
        return DB::transaction(function () use ($data, $cashierId, $tenantId) {
            $outletId = $data['outlet_id'];
            $today = Carbon::now('Asia/Jakarta')->toDateString();

            //Generate Nomor Struk Atomik Harian per Outlet
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
                'shift_id' => $data['shift_id'] ?? null,
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

            //Simpan Sale Payments (Split Payment Support)
            foreach ($data['payments'] as $payment) {
                $sale->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'gateway_payload' => $payment['gateway_payload'] ?? null,
                ]);
            }

            return $sale->load(['items', 'payments', 'cashier']);
        });
    }
}