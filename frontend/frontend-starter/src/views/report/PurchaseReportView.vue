<script setup>
import { onMounted, ref, watch } from "vue";
import debounce from "lodash-es/debounce";
import api from "../../api";
import { useToast } from "../../composables/useToast";

const toast = useToast();

const isLoading = ref(false);
const exportingExcel = ref(false);
const rows = ref([]);
const search = ref("");
const perPage = ref(10);
const filters = ref({
    start_date: "",
    end_date: "",
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});

function formatCurrency(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
}

function formatDate(dateStr) {
    if (!dateStr) return "-";
    const [y, m, d] = String(dateStr).split("-");
    return `${d}-${m}-${y}`;
}

function fileSafeDate(value) {
    return value ? value.replaceAll("-", "") : "all";
}

function saveBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

async function fetchReport(page = 1) {
    if (
        filters.value.start_date &&
        filters.value.end_date &&
        filters.value.end_date < filters.value.start_date
    ) {
        toast.error("Tanggal sampai tidak boleh lebih kecil dari tanggal mulai");
        filters.value.end_date = "";
        return;
    }

    isLoading.value = true;
    try {
        const { data } = await api.get("/reports/purchases", {
            params: {
                page,
                per_page: perPage.value,
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
            },
        });

        rows.value = data.data.data || [];
        pagination.value = {
            current_page: data.data.current_page,
            last_page: data.data.last_page,
            per_page: data.data.per_page,
            total: data.data.total,
        };
    } catch (error) {
        toast.error("Gagal memuat laporan pembelian");
    } finally {
        isLoading.value = false;
    }
}

const debouncedSearch = debounce(() => fetchReport(1), 400);
watch(search, debouncedSearch);
watch(perPage, () => fetchReport(1));
watch(
    () => filters.value,
    () => fetchReport(1),
    { deep: true },
);

async function exportExcel() {
    if (exportingExcel.value) return;
    exportingExcel.value = true;
    try {
        const response = await api.get("/reports/purchases", {
            params: {
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
                export: "excel",
            },
            responseType: "blob",
        });

        saveBlob(
            response.data,
            `laporan-pembelian-${fileSafeDate(filters.value.start_date)}-${fileSafeDate(filters.value.end_date)}.csv`,
        );
    } catch (error) {
        toast.error("Gagal export Excel");
    } finally {
        exportingExcel.value = false;
    }
}

function buildPdfHtml(dataRows) {
    const bodyRows = dataRows
        .map(
            (row, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${row.no_invoice || "-"}</td>
                    <td>${formatDate(row.tanggal)}</td>
                    <td>${row.supplier || "-"}</td>
                    <td>${row.kasir || "-"}</td>
                    <td style="text-align:center;">${row.items_count || 0}</td>
                    <td style="text-align:right;">${formatCurrency(row.total)}</td>
                    <td>${row.keterangan || "-"}</td>
                </tr>`,
        )
        .join("");

    return `
        <html>
        <head>
            <title>Laporan Pembelian</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; color: #0f172a; }
                h1 { margin: 0 0 8px; font-size: 22px; }
                p { margin: 0 0 16px; font-size: 12px; color: #475569; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th, td { border: 1px solid #cbd5e1; padding: 8px; }
                th { background: #f8fafc; text-align: left; }
            </style>
        </head>
        <body>
            <h1>Laporan Pembelian</h1>
            <p>Periode: ${formatDate(filters.value.start_date) || "-"} s/d ${formatDate(filters.value.end_date) || "-"}</p>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Kasir</th>
                        <th>Item</th>
                        <th>Total</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>${bodyRows}</tbody>
            </table>
        </body>
        </html>
    `;
}

async function exportPdf() {
    try {
        const { data } = await api.get("/reports/purchases", {
            params: {
                page: 1,
                per_page: 100,
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
            },
        });

        const allRows = data.data.data || [];
        const printWindow = window.open("", "_blank");
        if (!printWindow) {
            toast.error("Popup diblokir browser");
            return;
        }
        printWindow.document.write(buildPdfHtml(allRows));
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    } catch (error) {
        toast.error("Gagal export PDF");
    }
}

onMounted(() => {
    fetchReport(1);
});
</script>

<template>
    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <h1 class="text-2xl font-black text-slate-800">Laporan Pembelian</h1>
            <p class="text-sm text-slate-500 mt-1">
                Ringkasan invoice supplier per periode.
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/60 flex flex-col lg:flex-row gap-4 justify-between">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full lg:w-auto">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Mulai</label>
                        <input
                            v-model="filters.start_date"
                            type="date"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Sampai</label>
                        <input
                            v-model="filters.end_date"
                            :min="filters.start_date || undefined"
                            type="date"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Tampilkan</label>
                        <select
                            v-model="perPage"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold"
                        >
                            <option :value="10">10</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3 w-full lg:w-auto">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari no invoice / supplier"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold min-w-[240px]"
                        />
                    </div>
                    <button
                        @click="exportPdf"
                        class="px-4 py-2.5 rounded-xl bg-slate-700 text-white text-xs font-black uppercase tracking-wider"
                    >
                        Export PDF
                    </button>
                    <button
                        @click="exportExcel"
                        :disabled="exportingExcel"
                        class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-black uppercase tracking-wider disabled:opacity-50"
                    >
                        {{ exportingExcel ? "Export..." : "Export Excel" }}
                    </button>
                </div>
            </div>

            <div class="table-container rounded-none border-0 shadow-none">
                <table class="table-fixed-layout table-wide">
                    <thead class="table-header">
                        <tr>
                            <th class="w-16 text-center">No</th>
                            <th class="w-44">No Invoice</th>
                            <th class="w-32">Tanggal</th>
                            <th class="w-44">Supplier</th>
                            <th class="w-36">Kasir</th>
                            <th class="w-24 text-center">Item</th>
                            <th class="w-40 text-right">Total</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="isLoading">
                            <td colspan="8" class="px-4 py-16 text-center text-slate-400">Memuat data...</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="8" class="px-4 py-16 text-center text-slate-400">Tidak ada data</td>
                        </tr>
                        <tr v-else v-for="(row, idx) in rows" :key="row.id" class="table-row">
                            <td class="table-cell text-center text-slate-500">
                                {{ (pagination.current_page - 1) * pagination.per_page + idx + 1 }}
                            </td>
                            <td class="table-cell font-bold text-blue-600">{{ row.no_invoice }}</td>
                            <td class="table-cell">{{ formatDate(row.tanggal) }}</td>
                            <td class="table-cell">{{ row.supplier || "-" }}</td>
                            <td class="table-cell">{{ row.kasir || "-" }}</td>
                            <td class="table-cell text-center font-bold text-slate-700">{{ row.items_count }}</td>
                            <td class="table-cell text-right font-black text-amber-600">{{ formatCurrency(row.total) }}</td>
                            <td class="table-cell text-slate-500">{{ row.keterangan || "-" }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="pagination.last_page > 1"
                class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/40"
            >
                <div class="text-xs text-slate-500 font-bold uppercase tracking-widest">
                    Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
                </div>
                <div class="flex gap-2">
                    <button
                        @click="fetchReport(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-lg border border-slate-200 bg-white disabled:opacity-50"
                    >
                        Prev
                    </button>
                    <button
                        @click="fetchReport(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-lg border border-slate-200 bg-white disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
