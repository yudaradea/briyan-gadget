<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Purchase;
use App\Models\SalesTransaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $validated = $this->validateReportRequest($request, true);
        $perPage = $this->resolvePerPage($request);
        $tipe = $validated['tipe'] ?? 'all';

        $query = SalesTransaction::query()
            ->with(['user:id,name', 'salesRep:id,nama'])
            ->withSum('items as hpp_total', 'hpp_total')
            ->search($validated['search'] ?? null)
            ->dateRange($validated['start_date'] ?? null, $validated['end_date'] ?? null)
            ->when($tipe !== 'all', fn($q) => $q->where('tipe', $tipe))
            ->latest('tanggal')
            ->latest('created_at');

        if (($validated['export'] ?? null) === 'excel') {
            return $this->exportSalesCsv($query->get());
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
                'subtotal' => (float) $item->subtotal,
                'diskon_nominal' => (float) $item->diskon_nominal,
                'tax_nominal' => (float) $item->tax_nominal,
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
            ->latest('tanggal')
            ->latest('created_at');

        if (($validated['export'] ?? null) === 'excel') {
            return $this->exportPurchasesCsv($query->get());
        }

        $data = $query->paginate($perPage);
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
            ->latest('tanggal')
            ->latest('created_at');

        if (($validated['export'] ?? null) === 'excel') {
            return $this->exportProfitCsv($query->get());
        }

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

        return ResponseHelper::success([
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
        ], 'Profit report retrieved');
    }

    private function validateReportRequest(Request $request, bool $withType = false): array
    {
        $rules = [
            'search' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'export' => ['nullable', 'in:excel'],
        ];

        if ($withType) {
            $rules['tipe'] = ['nullable', 'in:all,penjualan,service'];
        }

        return $request->validate($rules);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 50, 100], true)) {
            $perPage = 10;
        }
        return $perPage;
    }

    private function exportSalesCsv($rows)
    {
        $filename = 'laporan-penjualan-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No Invoice', 'Tanggal', 'Tipe', 'Pelanggan', 'Kasir', 'Sales', 'Subtotal', 'Diskon', 'Pajak', 'Grand Total', 'Metode', 'HPP', 'Laba Kotor']);

            foreach ($rows as $item) {
                $hpp = (float) ($item->hpp_total ?? 0);
                $grandTotal = (float) $item->grand_total;
                fputcsv($out, [
                    $item->no_invoice,
                    optional($item->tanggal)->format('Y-m-d'),
                    $item->tipe ?? 'penjualan',
                    $item->pelanggan,
                    $item->user?->name,
                    $item->salesRep?->nama,
                    (float) $item->subtotal,
                    (float) $item->diskon_nominal,
                    (float) $item->tax_nominal,
                    $grandTotal,
                    $item->metode_pembayaran,
                    $hpp,
                    $grandTotal - $hpp,
                ]);
            }
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
