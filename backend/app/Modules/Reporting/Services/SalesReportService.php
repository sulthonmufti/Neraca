<?php

namespace App\Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    protected function parseDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate
            ? Carbon::parse($startDate, 'Asia/Jakarta')->startOfDay()
            : Carbon::now('Asia/Jakarta')->startOfDay();

        $end = $endDate
            ? Carbon::parse($endDate, 'Asia/Jakarta')->endOfDay()
            : Carbon::now('Asia/Jakarta')->endOfDay();

        return [$start, $end];
    }

    //Ringkasan Laporan Penjualan & Harian (Summary + Daily Breakdown)
    public function getSummary(int $tenantId, ?int $outletId, ?string $startDate, ?string $endDate): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        //Agregat Header Sales (Completed)
        $salesQuery = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $salesStats = (clone $salesQuery)
            ->selectRaw('
                COUNT(id) as transactions_count,
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(discount_total), 0) as discount_total,
                COALESCE(SUM(tax_total), 0) as tax_total,
                COALESCE(SUM(service_total), 0) as service_total,
                COALESCE(SUM(rounding), 0) as rounding_total,
                COALESCE(SUM(grand_total), 0) as grand_total
            ')
            ->first();

        //Hitung Total HPP (COGS) dari snapshot saat transaksi
        $cogsTotal = (int) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('sales.outlet_id', $outletId))
            ->selectRaw('COALESCE(SUM(ROUND(sale_items.qty * sale_items.cost_snapshot)), 0) as total_cogs')
            ->value('total_cogs');

        //Hitung Total Retur pada periode tersebut
        $returnsTotal = (int) DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sale_returns.created_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('sales.outlet_id', $outletId))
            ->sum('sale_returns.refund_total');

        //Hitung jumlah transaksi yang di-void pada periode tersebut
        $voidedCount = (int) DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'voided')
            ->whereBetween('sold_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->count();

        $grossSales = (int) ($salesStats->gross_sales ?? 0);
        $discountTotal = (int) ($salesStats->discount_total ?? 0);
        $netSales = $grossSales - $discountTotal - $returnsTotal;
        $grossProfit = $netSales - $cogsTotal;

        //Rincian Per Hari (Daily Breakdown)
        $dailyBreakdown = (clone $salesQuery)
            ->selectRaw("
                DATE(sold_at AT TIME ZONE 'Asia/Jakarta') as sale_date,
                COUNT(id) as transactions_count,
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(discount_total), 0) as discount_total,
                COALESCE(SUM(tax_total), 0) as tax_total,
                COALESCE(SUM(grand_total), 0) as grand_total
            ")
            ->groupByRaw("DATE(sold_at AT TIME ZONE 'Asia/Jakarta')")
            ->orderByDesc('sale_date')
            ->get();

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'outlet_id' => $outletId,
            ],
            'summary' => [
                'transactions_count' => (int) ($salesStats->transactions_count ?? 0),
                'voided_count' => $voidedCount,
                'gross_sales' => $grossSales,
                'discount_total' => $discountTotal,
                'returns_total' => $returnsTotal,
                'net_sales' => $netSales,
                'tax_total' => (int) ($salesStats->tax_total ?? 0),
                'service_total' => (int) ($salesStats->service_total ?? 0),
                'rounding_total' => (int) ($salesStats->rounding_total ?? 0),
                'grand_total_collected' => (int) ($salesStats->grand_total ?? 0),
                'cogs_total' => $cogsTotal,
                'gross_profit' => $grossProfit,
            ],
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    //Laporan Penjualan per Produk / Varian
    public function getByProduct(int $tenantId, ?int $outletId, ?string $startDate, ?string $endDate)
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('sales.outlet_id', $outletId))
            ->selectRaw('
                sale_items.variant_id,
                sale_items.sku_snapshot as sku,
                sale_items.name_snapshot as product_name,
                COUNT(DISTINCT sales.id) as transactions_count,
                SUM(sale_items.qty) as total_qty_sold,
                SUM(sale_items.line_total) as total_revenue,
                SUM(ROUND(sale_items.qty * sale_items.cost_snapshot)) as total_cogs,
                SUM(sale_items.line_total - ROUND(sale_items.qty * sale_items.cost_snapshot)) as gross_profit
            ')
            ->groupBy('sale_items.variant_id', 'sale_items.sku_snapshot', 'sale_items.name_snapshot')
            ->orderByDesc('total_revenue')
            ->get();
    }

    //Laporan Penjualan per Metode Pembayaran
    public function getByPaymentMethod(int $tenantId, ?int $outletId, ?string $startDate, ?string $endDate)
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        return DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sale_payments.status', 'paid')
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('sales.outlet_id', $outletId))
            ->selectRaw('
                payment_methods.id as payment_method_id,
                payment_methods.code,
                payment_methods.name,
                payment_methods.type,
                COUNT(DISTINCT sales.id) as transactions_count,
                SUM(sale_payments.amount) as total_received,
                SUM(CASE WHEN payment_methods.type = \'cash\' THEN (sale_payments.amount - sales.change_total) ELSE sale_payments.amount END) as net_amount
            ')
            ->groupBy('payment_methods.id', 'payment_methods.code', 'payment_methods.name', 'payment_methods.type')
            ->orderByDesc('net_amount')
            ->get();
    }

    //Laporan Penjualan per Kasir
    public function getByCashier(int $tenantId, ?int $outletId, ?string $startDate, ?string $endDate)
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        return DB::table('sales')
            ->join('users', 'users.id', '=', 'sales.cashier_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('sales.outlet_id', $outletId))
            ->selectRaw('
                users.id as cashier_id,
                users.name as cashier_name,
                users.email as cashier_email,
                COUNT(sales.id) as transactions_count,
                SUM(sales.subtotal) as gross_sales,
                SUM(sales.discount_total) as discount_total,
                SUM(sales.grand_total) as grand_total
            ')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('grand_total')
            ->get();
    }
}