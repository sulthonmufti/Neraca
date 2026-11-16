<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockLevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id');
        $lowStockOnly = filter_var($request->query('low_stock'), FILTER_VALIDATE_BOOLEAN);

        $stocks = StockLevel::with(['variant.product', 'outlet'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($lowStockOnly, fn ($q) => $q->whereColumn('qty', '<=', 'reorder_point'))
            ->orderBy('outlet_id')
            ->orderBy('variant_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stocks,
        ]);
    }

    public function updateReorderPoint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:outlets,id',
            'variant_id' => 'required|exists:product_variants,id',
            'reorder_point' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi reorder point gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $outletId = (int) $request->input('outlet_id');
        $variantId = (int) $request->input('variant_id');
        $reorderPoint = (float) $request->input('reorder_point');

        DB::table('stock_levels')->upsert(
            [
                [
                    'outlet_id' => $outletId,
                    'variant_id' => $variantId,
                    'qty' => 0,
                    'reorder_point' => $reorderPoint,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['outlet_id', 'variant_id'],
            ['reorder_point', 'updated_at']
        );

        $stockLevel = StockLevel::with(['variant.product', 'outlet'])
            ->where('outlet_id', $outletId)
            ->where('variant_id', $variantId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Batas minimum stok (reorder point) berhasil diperbarui',
            'data' => $stockLevel,
        ]);
    }
}