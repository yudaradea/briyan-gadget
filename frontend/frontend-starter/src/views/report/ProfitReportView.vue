<script setup>
import { computed, onMounted, ref, watch } from "vue";
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
    tipe: "all",
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});

const totalPendapatan = computed(() =>
    rows.value.reduce((sum, row) => sum + Number(row.pendapatan || 0), 0),
);
const totalHpp = computed(() =>
    rows.value.reduce((sum, row) => sum + Number(row.hpp_total || 0), 0),
);
const totalLaba = computed(() =>
    rows.value.reduce((sum, row) => sum + Number(row.laba_kotor || 0), 0),
);

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

function formatType(type) {
    return type === "service" ? "Service" : "Penjualan";
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
        const { data } = await api.get("/reports/profit", {
            params: {
                page,
                per_page: perPage.value,
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
                tipe: filters.value.tipe || "all",
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
        toast.error("Gagal memuat laporan laba rugi / hpp");
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
        const response = await api.get("/reports/profit", {
            params: {
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
                tipe: filters.value.tipe || "all",
                export: "excel",
            },
            responseType: "blob",
        });

        saveBlob(
            response.data,
            `laporan-laba-rugi-hpp-${fileSafeDate(filters.value.start_date)}-${fileSafeDate(filters.value.end_date)}.csv`,
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
                    <td>${formatType(row.tipe)}</td>
                    <td>${row.kasir || "-"}</td>
                    <td style="text-align:right;">${formatCurrency(row.pendapatan)}</td>
                    <td style="text-align:right;">${formatCurrency(row.hpp_total)}</td>
                    <td style="text-align:right;">${formatCurrency(row.laba_kotor)}</td>
                </tr>`,
        )
        .join("");

    return `
        <html>
        <head>
            <title>Laporan Laba Rugi / HPP</title>
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
            <h1>Laporan Laba Rugi / HPP</h1>
            <p>Periode: ${formatDate(filters.value.start_date) || "-"} s/d ${formatDate(filters.value.end_date) || "-"}</p>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Kasir</th>
                        <th>Pendapatan</th>
                        <th>HPP</th>
                        <th>Laba Kotor</th>
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
        const { data } = await api.get("/reports/profit", {
            params: {
                page: 1,
                per_page: 100,
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
                tipe: filters.value.tipe || "all",
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
            <h1 class="text-2xl font-black text-slate-800">Laporan Laba Rugi / HPP</h1>
            <p class="text-sm text-slate-500 mt-1">
                Pendapatan, HPP, dan laba kotor per transaksi.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">Pendapatan (Halaman ini)</p>
                <p class="text-xl font-black text-blue-600 mt-1">{{ formatCurrency(totalPendapatan) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">HPP (Halaman ini)</p>
                <p class="text-xl font-black text-amber-600 mt-1">{{ formatCurrency(totalHpp) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">Laba Kotor (Halaman ini)</p>
                <p class="text-xl font-black text-emerald-600 mt-1">{{ formatCurrency(totalLaba) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/60 flex flex-col lg:flex-row gap-4 justify-between">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 w-full lg:w-auto">
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
                        <label class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Tipe</label>
                        <select
                            v-model="filters.tipe"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold"
                        >
                            <option value="all">Semua</option>
                            <option value="penjualan">Penjualan</option>
                            <option value="service">Service</option>
                        </select>
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
                            placeholder="Cari no invoice"
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
                            <th class="w-28">Tipe</th>
                            <th class="w-36">Kasir</th>
                            <th class="w-40 text-right">Pendapatan</th>
                            <th class="w-40 text-right">HPP</th>
                            <th class="w-40 text-right">Laba Kotor</th>
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
                            <td class="table-cell">
                                <span class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider"
                                    :class="row.tipe === 'service' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600'">
                                    {{ formatType(row.tipe) }}
                                </span>
                            </td>
                            <td class="table-cell">{{ row.kasir || "-" }}</td>
                            <td class="table-cell text-right font-bold text-slate-700">{{ formatCurrency(row.pendapatan) }}</td>
                            <td class="table-cell text-right font-bold text-amber-600">{{ formatCurrency(row.hpp_total) }}</td>
                            <td class="table-cell text-right font-black text-emerald-600">{{ formatCurrency(row.laba_kotor) }}</td>
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
