<?php

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Grade;
use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\SalesRep;
use App\Models\SalesTransaction;
use App\Models\ServiceOrder;
use App\Models\Supplier;
use Carbon\Carbon;

class DashboardRepository
{
    public function summary()
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();

        $salesToday = (float) SalesTransaction::whereDate('tanggal', $today)->sum('grand_total');
        $salesMonth = (float) SalesTransaction::whereDate('tanggal', '>=', $monthStart)->sum('grand_total');
        $salesYear = (float) SalesTransaction::whereDate('tanggal', '>=', $yearStart)->sum('grand_total');
        $salesTotal = (float) SalesTransaction::sum('grand_total');

        $hppToday = (float) SaleItem::whereHas('salesTransaction', fn($q) => $q->whereDate('tanggal', $today))->sum('hpp_total');
        $hppMonth = (float) SaleItem::whereHas('salesTransaction', fn($q) => $q->whereDate('tanggal', '>=', $monthStart))->sum('hpp_total');
        $hppYear = (float) SaleItem::whereHas('salesTransaction', fn($q) => $q->whereDate('tanggal', '>=', $yearStart))->sum('hpp_total');
        $hppTotal = (float) SaleItem::sum('hpp_total');

        $purchaseToday = (float) Purchase::whereDate('tanggal', $today)->sum('total');
        $purchaseMonth = (float) Purchase::whereDate('tanggal', '>=', $monthStart)->sum('total');
        $purchaseYear = (float) Purchase::whereDate('tanggal', '>=', $yearStart)->sum('total');
        $purchaseTotal = (float) Purchase::sum('total');

        $dailyDays = collect(range(13, 0))->map(fn($i) => Carbon::today()->subDays($i))->values();
        $dailyLabels = $dailyDays->map(fn($d) => $d->format('d M'))->values();

        $salesDailyMap = SalesTransaction::query()
            ->selectRaw('DATE(tanggal) as tgl, SUM(grand_total) as total')
            ->whereDate('tanggal', '>=', Carbon::today()->subDays(13))
            ->groupBy('tgl')
            ->pluck('total', 'tgl');

        $purchaseDailyMap = Purchase::query()
            ->selectRaw('DATE(tanggal) as tgl, SUM(total) as total')
            ->whereDate('tanggal', '>=', Carbon::today()->subDays(13))
            ->groupBy('tgl')
            ->pluck('total', 'tgl');

        $hppDailyMap = SaleItem::query()
            ->join('sales_transactions', 'sale_items.sales_transaction_id', '=', 'sales_transactions.id')
            ->selectRaw('DATE(sales_transactions.tanggal) as tgl, SUM(sale_items.hpp_total) as total')
            ->whereDate('sales_transactions.tanggal', '>=', Carbon::today()->subDays(13))
            ->groupBy('tgl')
            ->pluck('total', 'tgl');

        $dailySales = $dailyDays->map(fn($d) => (float) ($salesDailyMap[$d->toDateString()] ?? 0))->values();
        $dailyPurchases = $dailyDays->map(fn($d) => (float) ($purchaseDailyMap[$d->toDateString()] ?? 0))->values();
        $dailyProfit = $dailyDays->map(function ($d) use ($salesDailyMap, $hppDailyMap) {
            $date = $d->toDateString();
            return (float) (($salesDailyMap[$date] ?? 0) - ($hppDailyMap[$date] ?? 0));
        })->values();

        $monthNumbers = collect(range(1, 12));
        $monthLabels = collect(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']);

        $salesMonthlyMap = SalesTransaction::query()
            ->selectRaw('MONTH(tanggal) as bulan, SUM(grand_total) as total')
            ->whereYear('tanggal', now()->year)
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $purchaseMonthlyMap = Purchase::query()
            ->selectRaw('MONTH(tanggal) as bulan, SUM(total) as total')
            ->whereYear('tanggal', now()->year)
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $hppMonthlyMap = SaleItem::query()
            ->join('sales_transactions', 'sale_items.sales_transaction_id', '=', 'sales_transactions.id')
            ->selectRaw('MONTH(sales_transactions.tanggal) as bulan, SUM(sale_items.hpp_total) as total')
            ->whereYear('sales_transactions.tanggal', now()->year)
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $monthlySales = $monthNumbers->map(fn($m) => (float) ($salesMonthlyMap[$m] ?? 0))->values();
        $monthlyPurchases = $monthNumbers->map(fn($m) => (float) ($purchaseMonthlyMap[$m] ?? 0))->values();
        $monthlyProfit = $monthNumbers->map(fn($m) => (float) (($salesMonthlyMap[$m] ?? 0) - ($hppMonthlyMap[$m] ?? 0)))->values();

        $data = [
            'cards' => [
                'sales' => [
                    'today' => $salesToday,
                    'month' => $salesMonth,
                    'year' => $salesYear,
                    'total' => $salesTotal,
                ],
                'profit' => [
                    'today' => $salesToday - $hppToday,
                    'month' => $salesMonth - $hppMonth,
                    'year' => $salesYear - $hppYear,
                    'total' => $salesTotal - $hppTotal,
                ],
                'purchases' => [
                    'today' => $purchaseToday,
                    'month' => $purchaseMonth,
                    'year' => $purchaseYear,
                    'total' => $purchaseTotal,
                ],
                'summary' => [
                    'purchase_invoices' => Purchase::count(),
                    'catalog_products' => MasterProduct::count(),
                    'stock_products' => Product::count(),
                    'categories' => Category::count(),
                    'grades' => Grade::count(),
                    'brands' => Brand::count(),
                    'sales_reps' => SalesRep::count(),
                    'suppliers' => Supplier::count(),
                    'services' => ServiceOrder::count(),
                ],
            ],
            'charts' => [
                'daily' => [
                    'labels' => $dailyLabels,
                    'sales' => $dailySales,
                    'profit' => $dailyProfit,
                    'purchases' => $dailyPurchases,
                ],
                'monthly' => [
                    'labels' => $monthLabels,
                    'sales' => $monthlySales,
                    'profit' => $monthlyProfit,
                    'purchases' => $monthlyPurchases,
                ],
            ],
        ];

        return ResponseHelper::success($data, 'Dashboard summary retrieved successfully');
    }
}
