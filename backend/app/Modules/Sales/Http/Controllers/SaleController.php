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
use App\Modules\Member\Models\Member;
use Illuminate\Support\Facades\DB;

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
        $sales = Sale::with(['items', 'member.memberType'])
            ->where('status', 'hold')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }
    public function updateHold(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'nullable|integer|exists:members,id',
            'note' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.name_snapshot' => 'required|string',
            'items.*.sku_snapshot' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pembaruan hold bill gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            $sale = DB::transaction(function () use ($request, $id, $user) {
                $sale = Sale::where('id', $id)
                    ->where('tenant_id', $user->tenant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sale->status !== 'hold') {
                    throw new \Exception('Hanya transaksi berstatus hold yang dapat diubah.');
                }

                $subtotal = 0;
                $itemsData = [];

                foreach ($request->input('items') as $item) {
                    $lineTotal = ($item['qty'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);
                    $subtotal += $lineTotal;

                    $itemsData[] = [
                        'sale_id' => $sale->id,
                        'variant_id' => $item['variant_id'],
                        'unit_id' => $item['unit_id'],
                        'name_snapshot' => $item['name_snapshot'],
                        'sku_snapshot' => $item['sku_snapshot'],
                        'qty' => $item['qty'],
                        'unit_price' => $item['unit_price'],
                        'cost_snapshot' => $item['cost_snapshot'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'line_total' => $lineTotal,
                        'modifiers' => json_encode($item['modifiers'] ?? null),
                        'note' => $item['note'] ?? null,
                        'kitchen_status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Hitung ulang diskon member jika ada
                $memberId = $request->input('member_id');
                $discountTotal = 0;

                if ($memberId) {
                    $member = Member::with('memberType')
                        ->where('id', $memberId)
                        ->where('tenant_id', $user->tenant_id)
                        ->first();

                    if ($member && $member->status === 'active' && $member->memberType) {
                        $discountPercent = (float) $member->memberType->discount_percent;
                        if ($discountPercent > 0) {
                            $discountTotal = (int) round($subtotal * ($discountPercent / 100));
                        }
                    }
                }

                $grandTotal = max(0, $subtotal - $discountTotal);

                // Ganti daftar item lama dengan item baru
                DB::table('sale_items')->where('sale_id', $sale->id)->delete();
                DB::table('sale_items')->insert($itemsData);

                /** @var Sale $sale */
                $sale->update([
                    'member_id' => $memberId,
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'grand_total' => $grandTotal,
                    'note' => $request->input('note', $sale->note),
                ]);

                /** @var Sale $sale */
                return $sale->load(['items', 'member.memberType']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Hold bill berhasil diperbarui',
                'data' => $sale,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancelHold(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $sale = DB::transaction(function () use ($request, $id, $user) {
                $sale = Sale::where('id', $id)
                    ->where('tenant_id', $user->tenant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sale->status !== 'hold') {
                    throw new \Exception('Hanya transaksi berstatus hold yang dapat dibatalkan.');
                }

                /** @var Sale $sale */
                $sale->update([
                    'status' => 'voided',
                    'voided_at' => now(),
                    'void_reason' => $request->input('void_reason', 'Hold bill dibatalkan pelanggan'),
                    'voided_by' => $user->id,
                ]);

                return $sale;
            });

            return response()->json([
                'success' => true,
                'message' => 'Hold bill berhasil dibatalkan',
                'data' => $sale,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
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