<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('suppliers', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi supplier gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $supplier = Supplier::create([
            'tenant_id' => $tenantId,
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier berhasil ditambahkan',
            'data' => $supplier,
        ], 201);
    }
}