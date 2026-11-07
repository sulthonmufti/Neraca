<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\Shift;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CloseShiftService
{
    public function execute(int $shiftId, array $data): Shift
    {
        return DB::transaction(function () use ($shiftId, $data) {
            /** @var Shift|null $shift */
            $shift = Shift::where('id', $shiftId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$shift) {
                throw new HttpException(404, 'Shift aktif tidak ditemukan atau sudah ditutup.');
            }

            $countedCash = $data['counted_cash'];

            $expectedCash = $shift->opening_cash + $shift->total_cash_sales + $shift->total_cash_in - $shift->total_cash_out;
            $diffCash = $countedCash - $expectedCash;

            $shift->update([
                'closed_at' => now(),
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'diff_cash' => $diffCash,
                'status' => 'closed',
                'close_note' => $data['close_note'] ?? null,
            ]);

            return $shift->fresh(['register', 'user']);
        });
    }
}