<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpenShiftService
{
    public function execute(array $data, int $userId, int $tenantId): Shift
    {
        return DB::transaction(function () use ($data, $userId, $tenantId) {
            $registerId = $data['register_id'];

            // Cek apakah register ini sudah memiliki shift yang masih 'open'
            $activeShift = Shift::where('register_id', $registerId)
                ->where('status', 'open')
                ->first();

            if ($activeShift) {
                throw new HttpException(422, 'Register ini sedang digunakan oleh shift aktif yang belum ditutup.');
            }

            // Buat shift baru
            $shift = Shift::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'outlet_id' => $data['outlet_id'],
                'register_id' => $registerId,
                'user_id' => $userId,
                'opened_at' => now(),
                'opening_cash' => $data['opening_cash'] ?? 0,
                'expected_cash' => $data['opening_cash'] ?? 0,
                'status' => 'open',
                'note' => $data['note'] ?? null,
            ]);

            return $shift->load(['register', 'user']);
        });
    }
}