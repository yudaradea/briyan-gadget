<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\SalesTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view reports|view all reports', only: ['sales', 'purchases', 'profit']),
        ];
    }

    public function sales(Request $request)
    {
        $validated = $this->validateReportRequest($request, true);
        $perPage = $this->resolvePerPage($request);

        $query = SalesTransaction::query()
            ->with(['user:id,name', 'salesRep:id,nama'])
            ->withSum('items as hpp_total', 'hpp_total')
            ->withSum('items as qty_total', 'qty');
        $this->applySalesReportFilters($query, $validated);
        $query
            ->latest('tanggal')
            ->latest('created_at')
            ->latest('id');

        $summary = $this->buildSalesSummary($validated);

        if (($validated['export'] ?? null) === 'excel') {
            return $this->exportSalesCsv($query->get(), $summary);
        }

        $data = $query->paginate($perPage);
        $rows = $data->getCollection()->map(function ($item) {
            $hppTotal = (float) ($item->hpp_total ?? 0);

            return [
                'id' => $item->id,
                'no_invoice' => $item->no_invoice,
                'tanggal' => optional($item->tanggal)->format('Y-m-d'),
                'tipe' => $item->tipe ?? 'penjualan',
                'pelanggan' => $item->pelanggan,
                'kasir' => $item->user?->name,
                'sales' => $item->salesRep?->nama,
                'qty_total' => (int) ($item->qty_total ?? 0),
                'grand_total' => (float) $item->grand_total,
                'metode_pembayaran' => $item->metode_pembayaran,
                'hpp_total' => $hppTotal,
                'laba_kotor' => (float) $item->grand_total - $hppTotal,
            ];
        })->values();
        $data->setCollection($rows);

        return ResponseHelper::success([
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'summary' => $summary,
        ], 'Sales report retrieved');
    }

    public function purchases(Request $request)
    {
        $validated = $this->validateReportRequest($request);
        $perPage = $this->resolvePerPage($request);

        $query = Purchase::query()
            ->with(['supplier:id,nama', 'user:id,name'])
            ->withCount('items')
            ->search($validated['search'] ?? null)
            ->dateRange($validated['start_date'] ?? null, $validated['end_date'] ?? null)
            ->when($validated['supplier_id'] ?? null, fn($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->latest('tanggal')
            ->latest('created_at');

        if (($validated['export'] ?? null) === 'excel') {
            return $this->exportPurchasesCsv($query->get());
        }

        $data = $query->paginate($perPage);

        // Get summary totals
        $summaryQuery = Purchase::query()
            ->withCount('items')
            ->search($validated['search'] ?? null)
            ->dateRange($validated['start_date'] ?? null, $validated['end_date'] ?? null)
            ->when($validated['supplier_id'] ?? null, fn($q, $supplierId) => $q->where('supplier_id', $supplierId));

        $rows = $data->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'no_invoice' => $item->no_invoice,
                'tanggal' => optional($item->tanggal)->format('Y-m-d'),
                'supplier' => $item->supplier?->nama,
                'kasir' => $item->user?->name,
                'items_count' => (int) $item->items_count,
                'total' => (float) $item->total,
                'keterangan' => $item->keterangan,
            ];
        })->values();
        $data->setCollection($rows);

        return ResponseHelper::success([
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'summary' => [
                'items_count' => (int) $summaryQuery->get()->sum('items_count'),
                'total' => (float) $summaryQuery->sum('total'),
            ],
        ], 'Purchase report retrieved');
    }

    public function profit(Request $request)
    {
        $validated = $this->validateReportRequest($request, true);
        $perPage = $this->resolvePerPage($request);
        $tipe = $validated['tipe'] ?? 'all';

        $query = SalesTransaction::query()
            ->with(['user:id,name'])
            ->withSum('items as hpp_total', 'hpp_total')
            ->search($validated['search'] ?? null)
            ->dateRange($validated['start_date'] ?? null, $validated['end_date'] ?? null)
            ->when($tipe !== 'all', fn($q) => $q->where('tipe', $tipe))
            ->when($validated['sales_rep_id'] ?? null, fn($q, $salesRepId) => $q->where('sales_rep_id', $salesRepId))
            ->latest('tanggal')
            ->latest('created_at');

        if (($validated['export'] ?? null) === 'excel') {
            return $this->exportProfitCsv($query->get());
        }

        // Get summary for all data
        $summaryQuery = SalesTransaction::query()
            ->withSum('items as hpp_total', 'hpp_total')
            ->search($validated['search'] ?? null)
            ->dateRange($validated['start_date'] ?? null, $validated['end_date'] ?? null)
            ->when($tipe !== 'all', fn($q) => $q->where('tipe', $tipe))
            ->when($validated['sales_rep_id'] ?? null, fn($q, $salesRepId) => $q->where('sales_rep_id', $salesRepId));

        $data = $query->paginate($perPage);
        $rows = $data->getCollection()->map(function ($item) {
            $hppTotal = (float) ($item->hpp_total ?? 0);
            $pendapatan = (float) $item->grand_total;

            return [
                'id' => $item->id,
                'no_invoice' => $item->no_invoice,
                'tanggal' => optional($item->tanggal)->format('Y-m-d'),
                'tipe' => $item->tipe ?? 'penjualan',
                'kasir' => $item->user?->name,
                'pendapatan' => $pendapatan,
                'hpp_total' => $hppTotal,
                'laba_kotor' => $pendapatan - $hppTotal,
            ];
        })->values();
        $data->setCollection($rows);

        $allData = $summaryQuery->get();
        $totalPendapatan = $allData->sum('grand_total');
        $totalHpp = $allData->sum('hpp_total');

        return ResponseHelper::success([
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'summary' => [
                'pendapatan' => (float) $totalPendapatan,
                'hpp_total' => (float) $totalHpp,
                'laba_kotor' => (float) ($totalPendapatan - $totalHpp),
            ],
        ], 'Profit report retrieved');
    }

    private function validateReportRequest(Request $request, bool $withType = false): array
    {
        $rules = [
            'search' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'export' => ['nullable', 'in:excel,pdf'],
            'sales_rep_id' => ['nullable', 'exists:sales_reps,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'per_page' => ['nullable', 'integer'],
        ];

        if ($withType) {
            $rules['tipe'] = ['nullable', 'in:all,penjualan,service'];
        }

        return $request->validate($rules);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);
        // Allow -1 for export (get all data)
        if ($perPage === -1) {
            return 100000;
        }
        if (!in_array($perPage, [10, 50, 100], true)) {
            $perPage = 10;
        }
        return $perPage;
    }

    private function applySalesReportFilters($query, array $filters): void
    {
        $tipe = $filters['tipe'] ?? 'all';
        $salesRepId = $filters['sales_rep_id'] ?? null;

        $query
            ->search($filters['search'] ?? null)
            ->dateRange($filters['start_date'] ?? null, $filters['end_date'] ?? null)
            ->when($tipe !== 'all', fn($q) => $q->where('tipe', $tipe))
            ->when($salesRepId, fn($q) => $q->where('sales_rep_id', $salesRepId));
    }

    private function buildSalesSummary(array $filters): array
    {
        $base = SalesTransaction::query();
        $this->applySalesReportFilters($base, $filters);
        $grandTotal = (float) (clone $base)->sum('grand_total');

        $qtyTotal = (int) SaleItem::query()
            ->whereHas('salesTransaction', fn($q) => $this->applySalesReportFilters($q, $filters))
            ->sum('qty');

        $hppTotal = (float) SaleItem::query()
            ->whereHas('salesTransaction', fn($q) => $this->applySalesReportFilters($q, $filters))
            ->sum('hpp_total');

        return [
            'qty_total' => $qtyTotal,
            'grand_total' => $grandTotal,
            'hpp_total' => $hppTotal,
            'laba_kotor' => $grandTotal - $hppTotal,
        ];
    }

    private function exportSalesCsv($rows, array $summary)
    {
        $filename = 'laporan-penjualan-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        return response()->stream(function () use ($rows, $summary) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No Invoice', 'Tanggal', 'Pelanggan', 'Kasir', 'Sales', 'Qty', 'Total Bayar', 'Modal (HPP)', 'Laba']);

            foreach ($rows as $item) {
                $hpp = (float) ($item->hpp_total ?? 0);
                $grandTotal = (float) $item->grand_total;
                fputcsv($out, [
                    $item->no_invoice,
                    optional($item->tanggal)->format('Y-m-d'),
                    $item->pelanggan,
                    $item->user?->name,
                    $item->salesRep?->nama,
                    (int) ($item->qty_total ?? 0),
                    $grandTotal,
                    $hpp,
                    $grandTotal - $hpp,
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['TOTAL', '', '', '', '', (int) ($summary['qty_total'] ?? 0), (float) ($summary['grand_total'] ?? 0), (float) ($summary['hpp_total'] ?? 0), (float) ($summary['laba_kotor'] ?? 0)]);
            fclose($out);
        }, 200, $headers);
    }

    private function exportPurchasesCsv($rows)
    {
        $filename = 'laporan-pembelian-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No Invoice', 'Tanggal', 'Supplier', 'Kasir', 'Jumlah Item', 'Total', 'Keterangan']);

            foreach ($rows as $item) {
                fputcsv($out, [
                    $item->no_invoice,
                    optional($item->tanggal)->format('Y-m-d'),
                    $item->supplier?->nama,
                    $item->user?->name,
                    (int) ($item->items_count ?? 0),
                    (float) $item->total,
                    $item->keterangan,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    private function exportProfitCsv($rows)
    {
        $filename = 'laporan-laba-rugi-hpp-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No Invoice', 'Tanggal', 'Tipe', 'Kasir', 'Pendapatan', 'HPP', 'Laba Kotor']);

            foreach ($rows as $item) {
                $hpp = (float) ($item->hpp_total ?? 0);
                $pendapatan = (float) $item->grand_total;
                fputcsv($out, [
                    $item->no_invoice,
                    optional($item->tanggal)->format('Y-m-d'),
                    $item->tipe ?? 'penjualan',
                    $item->user?->name,
                    $pendapatan,
                    $hpp,
                    $pendapatan - $hpp,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
