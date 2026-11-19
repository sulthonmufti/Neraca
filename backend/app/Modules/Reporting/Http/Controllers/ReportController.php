<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected SalesReportService $salesReportService;

    public function __construct(SalesReportService $salesReportService)
    {
        $this->salesReportService = $salesReportService;
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $outletId = $request->query('outlet_id') ? (int) $request->query('outlet_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->salesReportService->getSummary($tenantId, $outletId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function byProduct(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $outletId = $request->query('outlet_id') ? (int) $request->query('outlet_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->salesReportService->getByProduct($tenantId, $outletId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function byPaymentMethod(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $outletId = $request->query('outlet_id') ? (int) $request->query('outlet_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->salesReportService->getByPaymentMethod($tenantId, $outletId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function byCashier(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $outletId = $request->query('outlet_id') ? (int) $request->query('outlet_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->salesReportService->getByCashier($tenantId, $outletId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}