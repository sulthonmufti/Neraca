<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecordStockMovementService
{
    /**
     * Mencatat mutasi stok ke ledger append-only dan memperbarui cache stock_levels.
     *
     * @param int $outletId
     * @param int $variantId
     * @param string $type (sale, sale_void, return, receipt, adjustment, opname, dll)
     * @param float $qtyChange Perubahan kuantitas (positif untuk penambahan, negatif untuk pengurangan)
     * @param int $unitCost Harga modal / HPP saat mutasi
     * @param int $userId ID pengguna yang melakukan aksi
     * @param string|null $refType Tipe referensi (misal: 'sales')
     * @param int|null $refId ID referensi sumber
     * @param string|null $note Catatan tambahan
     * @return StockMovement
     */
    public function execute(
        int $outletId,
        int $variantId,
        string $type,
        float $qtyChange,
        int $unitCost = 0,
        int $userId = 1,
        ?string $refType = null,
        ?int $refId = null,
        ?string $note = null
    ): StockMovement {
        return DB::transaction(function () use (
            $outletId,
            $variantId,
            $type,
            $qtyChange,
            $unitCost,
            $userId,
            $refType,
            $refId,
            $note
        ) {
            //Ambil atau buat baris stock_level dengan menguncinya (FOR UPDATE)
            /** @var StockLevel|null $stockLevel */
            $stockLevel = StockLevel::where('outlet_id', $outletId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$stockLevel) {
                DB::table('stock_levels')->insertOrIgnore([
                    'outlet_id' => $outletId,
                    'variant_id' => $variantId,
                    'qty' => 0,
                    'reorder_point' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                /** @var StockLevel $stockLevel */
                $stockLevel = StockLevel::where('outlet_id', $outletId)
                    ->where('variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();
            }

            $currentQty = (float) $stockLevel->qty;
            $newQty = $currentQty + $qtyChange;

            // Validasi stok minimum jika stok menjadi minus (kecuali diizinkan oleh pengaturan sistem)
            if ($newQty < 0) {
                throw new HttpException(
                    422,
                    "Stok tidak mencukupi untuk varian ID {$variantId}. Stok saat ini: {$currentQty}, diminta: " . abs($qtyChange)
                );
            }

            //Tulis ke ledger append-only (stock_movements)
            $movement = StockMovement::create([
                'outlet_id' => $outletId,
                'variant_id' => $variantId,
                'type' => $type,
                'qty_change' => $qtyChange,
                'qty_after' => $newQty,
                'unit_cost' => $unitCost,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'note' => $note,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            //Perbarui cache di tabel stock_levels menggunakan composite key
            DB::table('stock_levels')
                ->where('outlet_id', $outletId)
                ->where('variant_id', $variantId)
                ->update([
                    'qty' => $newQty,
                    'updated_at' => now(),
                ]);

            return $movement;
        });
    }
}