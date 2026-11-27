<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Services\CreateStockOpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockOpnameController extends Controller
{
    protected CreateStockOpnameService $createStockOpnameService;

    public function __construct(CreateStockOpnameService $createStockOpnameService)
    {
        $this->createStockOpnameService = $createStockOpnameService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $outletId = $request->query('outlet_id');

        $opnames = StockOpname::with([
                'user',
                'outlet',
                'items.variant' => fn ($q) => $q->withTrashed(),
                'items.variant.product' => fn ($q) => $q->withTrashed(),
            ])
            ->where('tenant_id', $tenantId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $opnames,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:outlets,id',
            'note' => 'nullable|string',
            'opname_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi stok opname gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $opname = $this->createStockOpnameService->execute(
                $request->all(),
                $user->id,
                $user->tenant_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Stok opname berhasil diproses dan saldo stok telah disesuaikan',
                'data' => $opname,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}