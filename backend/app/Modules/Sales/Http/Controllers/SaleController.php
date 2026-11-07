<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\CreateSaleService;
use App\Modules\Sales\Services\VoidSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    protected CreateSaleService $createSaleService;
    protected VoidSaleService $voidSaleService;

    public function __construct(
        CreateSaleService $createSaleService,
        VoidSaleService $voidSaleService
    ) {
        $this->createSaleService = $createSaleService;
        $this->voidSaleService = $voidSaleService;
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

    public function voidSale(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'void_reason' => 'required|string|max:255',
            'approved_by' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pembatalan transaksi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $sale = $this->voidSaleService->execute($id, $request->all(), $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibatalkan (void)',
                'data' => $sale,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}