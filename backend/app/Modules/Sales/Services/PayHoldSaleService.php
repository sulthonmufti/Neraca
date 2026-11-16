<?php

namespace App\Modules\Sales\Services;

use App\Modules\Cash\Models\Shift;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayHoldSaleService
{
    protected CreateSaleService $createSaleService;

    public function __construct(CreateSaleService $createSaleService)
    {
        $this->createSaleService = $createSaleService;
    }

    public function execute(int $saleId, array $data, int $cashierId): Sale
    {
        return DB::transaction(function () use ($saleId, $data, $cashierId) {
            /** @var Sale|null $sale */
            $sale = Sale::with(['items'])
                ->where('id', $saleId)
                ->lockForUpdate()
                ->first();

            if (!$sale) {
                throw new HttpException(404, 'Transaksi tidak ditemukan.');
            }

            if ($sale->status !== 'hold') {
                throw new HttpException(422, "Transaksi ini tidak dalam status 'hold'. Status saat ini: {$sale->status}");
            }

            //Cari Shift Aktif Kasir
            /** @var Shift|null $shift */
            $shift = Shift::where('outlet_id', $sale->outlet_id)
                ->where('user_id', $cashierId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$shift) {
                throw new HttpException(422, 'Tidak ada shift kasir yang aktif untuk memproses pembayaran.');
            }

            //Hitung Pembayaran
            $paidTotal = 0;
            foreach ($data['payments'] as $payment) {
                $paidTotal += (int) $payment['amount'];
            }

            if ($paidTotal < $sale->grand_total) {
                throw new HttpException(422, "Jumlah pembayaran ({$paidTotal}) kurang dari total tagihan ({$sale->grand_total}).");
            }

            $changeTotal = max(0, $paidTotal - $sale->grand_total);

            //Update Status Transaksi menjadi Completed
            $sale->update([
                'status' => 'completed',
                'shift_id' => $shift->id,
                'paid_total' => $paidTotal,
                'change_total' => $changeTotal,
                'sold_at' => now(),
            ]);

            //Potong Stok Barang
            $itemsPayload = $sale->items->map(function ($item) {
                return [
                    'variant_id' => $item->variant_id,
                    'unit_id' => $item->unit_id,
                    'qty' => (float) $item->qty,
                    'cost_snapshot' => $item->cost_snapshot,
                ];
            })->toArray();

            $this->createSaleService->deductStock($itemsPayload, $sale->outlet_id, $sale, $cashierId);

            //Simpan Payments & Mutasi Kas Laci
            $this->createSaleService->processPayments($data['payments'], $sale, $shift, $cashierId, $changeTotal);

            return $sale->load(['items', 'payments', 'cashier']);
        });
    }
}