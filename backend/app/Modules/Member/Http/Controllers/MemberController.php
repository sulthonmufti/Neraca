<?php

namespace App\Modules\Member\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Member\Models\Department;
use App\Modules\Member\Models\Member;
use App\Modules\Member\Models\MemberType;
use App\Modules\Member\Models\MemberWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    // --- DEPARTMENTS ---
    public function listDepartments(Request $request): JsonResponse
    {
        $departments = Department::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $departments]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('departments', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $department = Department::create([
            'tenant_id' => $tenantId,
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil ditambahkan',
            'data' => $department,
        ], 201);
    }

    // --- MEMBER TYPES ---
    public function listMemberTypes(Request $request): JsonResponse
    {
        $types = MemberType::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

    public function storeMemberType(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'default_credit_limit' => 'nullable|integer|min:0',
            'points_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $type = MemberType::create([
            'tenant_id' => $tenantId,
            'name' => $request->input('name'),
            'discount_percent' => $request->input('discount_percent', 0),
            'default_credit_limit' => $request->input('default_credit_limit', 0),
            'points_rate' => $request->input('points_rate', 0),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tipe member berhasil ditambahkan',
            'data' => $type,
        ], 201);
    }

    // --- MEMBERS ---
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $search = $request->query('search');

        $members = Member::with(['memberType', 'department', 'wallet'])
            ->where('tenant_id', $tenantId)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('member_no', 'ilike', "%{$search}%")
                        ->orWhere('card_code', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $members]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'member_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('members', 'member_no')->where('tenant_id', $tenantId),
            ],
            'member_type_id' => 'required|exists:member_types,id',
            'department_id' => 'nullable|exists:departments,id',
            'employee_no' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'card_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('members', 'card_code')->where('tenant_id', $tenantId),
            ],
            'credit_limit' => 'nullable|integer|min:0',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pendaftaran member gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = DB::transaction(function () use ($request, $tenantId) {
            /** @var MemberType $memberType */
            $memberType = MemberType::findOrFail($request->input('member_type_id'));
            $memberNo = $request->input('member_no');
            $cardCode = $request->input('card_code') ?: ('CARD-' . $memberNo);

            $member = Member::create([
                'tenant_id' => $tenantId,
                'member_no' => $memberNo,
                'member_type_id' => $memberType->id,
                'department_id' => $request->input('department_id'),
                'employee_no' => $request->input('employee_no'),
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'card_code' => $cardCode,
                'status' => 'active',
                'credit_limit' => $request->input('credit_limit', $memberType->default_credit_limit),
                'joined_at' => now(),
                'note' => $request->input('note'),
            ]);

            MemberWallet::create([
                'member_id' => $member->id,
                'balance' => 0,
                'credit_used' => 0,
            ]);

            return $member->load(['memberType', 'department', 'wallet']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Member berhasil didaftarkan',
            'data' => $member,
        ], 201);
    }

    /**
     * Scan Kartu / QR atau Nomor Member di Kasir
     */
    public function lookupByCode(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $code = $request->query('code');

        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter code (nomor kartu / QR / nomor member) wajib diisi.',
            ], 422);
        }

        $member = Member::with(['memberType', 'department', 'wallet'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) use ($code) {
                $q->where('card_code', $code)
                    ->orWhere('member_no', $code)
                    ->orWhere('phone', $code);
            })
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member tidak ditemukan atau tidak aktif.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $member,
        ]);
    }
}