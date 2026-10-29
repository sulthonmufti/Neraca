<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\CreateSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    protected CreateSaleService $createSaleService;

    public function __construct(CreateSaleService $createSaleService)
    {
        $this->createSaleService = $createSaleService;
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:outlets,id',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.name_snapshot' => 'required|string',
            'items.*.sku_snapshot' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|integer|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi transaksi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $sale = $this->createSaleService->execute(
                $request->all(),
                $user->id,
                $user->tenant_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diproses',
                'data' => $sale,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat memproses transaksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}