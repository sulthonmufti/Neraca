<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\Shift;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecordCashMovementService
{
    /**
     * Mencatat ledger kas laci (append-only) dan memperbarui cache ringkasan di shift.
     *
     * @param int $shiftId
     * @param string $type (sale_cash, refund_cash, cash_in, cash_out, topup_cash)
     * @param int $amount Nominal absolut (positif), tanda +/- diatur otomatis sesuai type
     * @param string $reason
     * @param int $userId
     * @param int|null $saleId
     * @param string|null $category
     * @return CashMovement
     */
    public function execute(
        int $shiftId,
        string $type,
        int $amount,
        string $reason,
        int $userId,
        ?int $saleId = null,
        ?string $category = null
    ): CashMovement {
        return DB::transaction(function () use ($shiftId, $type, $amount, $reason, $userId, $saleId, $category) {
            /** @var Shift|null $shift */
            $shift = Shift::where('id', $shiftId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$shift) {
                throw new HttpException(422, 'Shift aktif tidak ditemukan atau sudah ditutup.');
            }

            $absAmount = abs($amount);

            // Tentukan tanda (+/-) berdasarkan tipe ledger sesuai blueprint 5.5
            $signedAmount = in_array($type, ['cash_out', 'refund_cash'], true)
                ? -$absAmount
                : $absAmount;

            // 1. Tulis ke ledger cash_movements (append-only)
            $movement = CashMovement::create([
                'shift_id' => $shift->id,
                'type' => $type,
                'amount' => $signedAmount,
                'category' => $category,
                'reason' => $reason,
                'sale_id' => $saleId,
                'user_id' => $userId,
            ]);

            // 2. Perbarui cache ringkasan di tabel shifts
            if ($type === 'sale_cash') {
                $shift->total_cash_sales += $absAmount;
            } elseif ($type === 'refund_cash') {
                $shift->total_cash_sales -= $absAmount;
            } elseif (in_array($type, ['cash_in', 'topup_cash'], true)) {
                $shift->total_cash_in += $absAmount;
            } elseif ($type === 'cash_out') {
                $shift->total_cash_out += $absAmount;
            }

            // expected_cash = opening_cash + seluruh cash_movements pada shift tersebut
            $shift->expected_cash += $signedAmount;
            $shift->save();

            return $movement;
        });
    }
}