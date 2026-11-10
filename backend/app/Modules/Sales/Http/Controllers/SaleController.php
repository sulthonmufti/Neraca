<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\CreateSaleService;
use App\Modules\Sales\Services\PayHoldSaleService;
use App\Modules\Sales\Services\ReturnSaleService;
use App\Modules\Sales\Services\VoidSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    protected CreateSaleService $createSaleService;
    protected VoidSaleService $voidSaleService;
    protected ReturnSaleService $returnSaleService;
    protected PayHoldSaleService $payHoldSaleService;

    public function __construct(
        CreateSaleService $createSaleService,
        VoidSaleService $voidSaleService,
        ReturnSaleService $returnSaleService,
        PayHoldSaleService $payHoldSaleService
    ) {
        $this->createSaleService = $createSaleService;
        $this->voidSaleService = $voidSaleService;
        $this->returnSaleService = $returnSaleService;
        $this->payHoldSaleService = $payHoldSaleService;
    }

    public function store(Request $request): JsonResponse
    {
        $isHold = $request->input('status') === 'hold';

        $rules = [
            'outlet_id' => 'required|exists:outlets,id',
            'uuid' => 'nullable|uuid',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.name_snapshot' => 'required|string',
            'items.*.sku_snapshot' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|integer|min:0',
        ];

        // Jika bukan hold, payments wajib diisi
        if (!$isHold) {
            $rules['payments'] = 'required|array|min:1';
            $rules['payments.*.payment_method_id'] = 'required|exists:payment_methods,id';
            $rules['payments.*.amount'] = 'required|integer|min:0';
        }

        $validator = Validator::make($request->all(), $rules);

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
                'message' => $isHold ? 'Transaksi berhasil disimpan (hold)' : 'Transaksi berhasil diproses',
                'data' => $sale,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses transaksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listHold(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id');
        $sales = Sale::with(['items'])
            ->where('status', 'hold')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    public function payHold(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pembayaran gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $sale = $this->payHoldSaleService->execute($id, $request->all(), $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran hold bill berhasil diproses',
                'data' => $sale,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
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

    public function returnSale(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'refund_method_id' => 'required|exists:payment_methods,id',
            'approved_by' => 'nullable|integer|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.amount' => 'nullable|integer|min:0',
            'items.*.restock' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi retur transaksi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $saleReturn = $this->returnSaleService->execute($id, $request->all(), $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Retur transaksi berhasil diproses',
                'data' => $saleReturn,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}