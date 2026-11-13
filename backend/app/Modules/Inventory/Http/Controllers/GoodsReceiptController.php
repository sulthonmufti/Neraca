<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Services\CreateGoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoodsReceiptController extends Controller
{
    protected CreateGoodsReceiptService $createGoodsReceiptService;

    public function __construct(CreateGoodsReceiptService $createGoodsReceiptService)
    {
        $this->createGoodsReceiptService = $createGoodsReceiptService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $outletId = $request->query('outlet_id');

        $receipts = GoodsReceipt::with(['supplier', 'user', 'items.variant'])
            ->where('tenant_id', $tenantId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $receipts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:outlets,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_invoice_no' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'received_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|integer|min:0',
            'items.*.batch_no' => 'nullable|string|max:50',
            'items.*.expired_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi penerimaan barang gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $receipt = $this->createGoodsReceiptService->execute(
                $request->all(),
                $user->id,
                $user->tenant_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Penerimaan barang berhasil diproses dan stok telah ditambahkan',
                'data' => $receipt,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}