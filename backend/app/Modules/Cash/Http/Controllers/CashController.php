<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Services\CloseShiftService;
use App\Modules\Cash\Services\OpenShiftService;
use App\Modules\Cash\Services\RecordCashMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CashController extends Controller
{
    protected OpenShiftService $openShiftService;
    protected CloseShiftService $closeShiftService;
    protected RecordCashMovementService $recordCashMovementService;

    public function __construct(
        OpenShiftService $openShiftService,
        CloseShiftService $closeShiftService,
        RecordCashMovementService $recordCashMovementService
    ) {
        $this->openShiftService = $openShiftService;
        $this->closeShiftService = $closeShiftService;
        $this->recordCashMovementService = $recordCashMovementService;
    }

    public function openShift(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:outlets,id',
            'register_id' => 'required|exists:registers,id',
            'opening_cash' => 'required|integer|min:0',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi buka shift gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $shift = $this->openShiftService->execute(
                $request->all(),
                $user->id,
                $user->tenant_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Shift berhasil dibuka',
                'data' => $shift,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeMovement(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:cash_in,cash_out',
            'amount' => 'required|integer|min:1',
            'category' => 'nullable|string|max:50',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi mutasi kas gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $movement = $this->recordCashMovementService->execute(
                $id,
                $request->input('type'),
                $request->input('amount'),
                $request->input('reason'),
                $request->user()->id,
                null,
                $request->input('category')
            );

            return response()->json([
                'success' => true,
                'message' => 'Mutasi kas berhasil dicatat',
                'data' => $movement,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function closeShift(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'counted_cash' => 'required|integer|min:0',
            'close_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi tutup shift gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shift = $this->closeShiftService->execute($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Shift berhasil ditutup dan direkonsiliasi',
                'data' => $shift,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}