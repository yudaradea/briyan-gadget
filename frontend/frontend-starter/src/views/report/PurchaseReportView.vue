<script setup>
import { onMounted, ref, watch } from "vue";
import debounce from "lodash-es/debounce";
import api from "../../api";
import { useToast } from "../../composables/useToast";

const toast = useToast();

const isLoading = ref(false);
const exportingExcel = ref(false);
const rows = ref([]);
const suppliers = ref([]);
const search = ref("");
const perPage = ref(10);
const filters = ref({
    start_date: "",
    end_date: "",
    supplier_id: "",
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});

const summary = ref({
    items_count: 0,
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

async function fetchSuppliers() {
    try {
        const { data } = await api.get("/suppliers/all");
        suppliers.value = data.data || [];
    } catch (error) {
        suppliers.value = [];
    }
}

async function fetchReport(page = 1) {
    if (
        filters.value.start_date &&
        filters.value.end_date &&
        filters.value.end_date < filters.value.start_date
    ) {
        toast.error(
            "Tanggal sampai tidak boleh lebih kecil dari tanggal mulai"
        );
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
                supplier_id: filters.value.supplier_id || undefined,
            },
        });

        rows.value = data.data.data || [];
        pagination.value = {
            current_page: data.data.current_page,
            last_page: data.data.last_page,
            per_page: data.data.per_page,
            total: data.data.total,
        };
        summary.value = {
            items_count: Number(data.data.summary?.items_count || 0),
            total: Number(data.data.summary?.total || 0),
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
    { deep: true }
);

function resetFilters() {
    filters.value = { start_date: "", end_date: "", supplier_id: "" };
    search.value = "";
    fetchReport(1);
}

async function exportExcel() {
    if (exportingExcel.value) return;
    exportingExcel.value = true;
    try {
        const response = await api.get("/reports/purchases", {
            params: {
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
                supplier_id: filters.value.supplier_id || undefined,
                export: "excel",
            },
            responseType: "blob",
        });

        saveBlob(
            response.data,
            `laporan-pembelian-${fileSafeDate(
                filters.value.start_date
            )}-${fileSafeDate(filters.value.end_date)}.csv`
        );
    } catch (error) {
        toast.error("Gagal export Excel");
    } finally {
        exportingExcel.value = false;
    }
}

function buildPdfHtml(dataRows, footerSummary) {
    const bodyRows = dataRows
        .map(
            (row, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${row.no_invoice || "-"}</td>
                    <td>${formatDate(row.tanggal)}</td>
                    <td>${row.supplier || "-"}</td>
                    <td style="text-align:center;">${row.items_count || 0}</td>
                    <td>${row.keterangan || "-"}</td>
                    <td style="text-align:left;">${formatCurrency(
                        row.total
                    )}</td>
                </tr>`
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
                tfoot td { background: #e2eef6; font-weight: 700; }
            </style>
        </head>
        <body>
            <h1>Laporan Pembelian</h1>
            <p>Periode: ${formatDate(filters.value.start_date) || "-"} s/d ${
        formatDate(filters.value.end_date) || "-"
    } | Supplier: ${
        suppliers.value.find((s) => s.id == filters.value.supplier_id)?.nama ||
        "Semua"
    }</p>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Item</th>
                        <th>Keterangan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>${bodyRows}</tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:center;">TOTAL</td>
                        <td style="text-align:center;">${
                            footerSummary.items_count || 0
                        }</td>
                        <td></td>
                        <td style="text-align:left;">${formatCurrency(
                            footerSummary.total || 0
                        )}</td>
                    </tr>
                </tfoot>
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
                per_page: -1, // Get all data from server
                search: search.value,
                start_date: filters.value.start_date || undefined,
                end_date: filters.value.end_date || undefined,
                supplier_id: filters.value.supplier_id || undefined,
            },
        });

        const allRows = data.data.data || [];
        const printWindow = window.open("", "_blank");
        if (!printWindow) {
            toast.error("Popup diblokir browser");
            return;
        }
        printWindow.document.write(
            buildPdfHtml(allRows, data.data.summary || summary.value)
        );
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    } catch (error) {
        toast.error("Gagal export PDF");
    }
}

onMounted(() => {
    fetchSuppliers();
    fetchReport(1);
});
</script>

<template>
    <div class="space-y-6">
        <div class="p-6 bg-white border shadow-sm rounded-2xl border-slate-200">
            <h1 class="text-2xl font-black text-slate-800">
                Laporan Pembelian
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Ringkasan invoice supplier per periode.
            </p>
        </div>

        <div
            class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
        >
            <div
                class="flex flex-col items-start justify-between gap-4 px-6 py-4 border-b border-slate-200 bg-slate-50 md:flex-row md:items-center"
            >
                <!-- Left: Filters -->
                <div
                    class="grid w-full grid-cols-2 gap-3 md:grid-cols-4 lg:flex md:w-auto"
                >
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Mulai</label
                        >
                        <input
                            v-model="filters.start_date"
                            type="date"
                            class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Sampai</label
                        >
                        <input
                            v-model="filters.end_date"
                            :min="filters.start_date"
                            type="date"
                            class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white disabled:bg-slate-100 disabled:cursor-not-allowed"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Supplier</label
                        >
                        <div class="relative">
                            <select
                                v-model="filters.supplier_id"
                                class="appearance-none w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white pr-8 min-w-[140px]"
                            >
                                <option value="">Semua Supplier</option>
                                <option
                                    v-for="s in suppliers"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.nama }}
                                </option>
                            </select>
                            <div
                                class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-slate-400"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1 lg:justify-end pb-0.5">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase invisible"
                            >Reset</label
                        >
                        <button
                            @click="resetFilters"
                            class="p-2 transition-colors text-slate-400 hover:text-rose-500"
                            title="Reset Filter"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Right: Per Page, Search, Export -->
                <div class="flex flex-row items-end w-full gap-2 md:w-auto">
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Tampilkan</label
                        >
                        <div class="relative">
                            <select
                                v-model="perPage"
                                class="appearance-none block w-20 px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition shadow-sm bg-white pr-8"
                            >
                                <option :value="10">10</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                            <div
                                class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-slate-400"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1 grow md:grow-0">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Search</label
                        >
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari invoice/supplier..."
                                class="block w-full md:w-56 pl-10 pr-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition shadow-sm"
                            />
                            <div
                                class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"
                            >
                                <svg
                                    class="w-4 h-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-end gap-2 pb-0.5">
                        <button
                            @click="exportPdf"
                            class="px-3 py-1.5 bg-slate-700 text-white text-sm font-medium rounded-lg hover:bg-slate-600 transition"
                        >
                            PDF
                        </button>
                        <button
                            @click="exportExcel"
                            :disabled="exportingExcel"
                            class="px-3 py-1.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-500 transition disabled:opacity-50"
                        >
                            {{ exportingExcel ? "..." : "Excel" }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="border-0 rounded-none shadow-none table-container">
                <table class="table-fixed-layout table-wide">
                    <thead class="table-header">
                        <tr class="">
                            <th class="w-16 text-center">No</th>
                            <th class="">No Invoice</th>
                            <th class="">Tanggal</th>
                            <th class="">Supplier</th>
                            <th class="text-center">Item</th>
                            <th class="">Keterangan</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="isLoading">
                            <td
                                colspan="7"
                                class="px-4 py-16 text-center text-slate-400"
                            >
                                Memuat data...
                            </td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-16 text-center text-slate-400"
                            >
                                Tidak ada data
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="(row, idx) in rows"
                            :key="row.id"
                            class="table-row group"
                        >
                            <td class="table-cell text-center text-slate-500">
                                {{
                                    (pagination.current_page - 1) *
                                        pagination.per_page +
                                    idx +
                                    1
                                }}
                            </td>
                            <td class="table-cell font-bold text-blue-600">
                                {{ row.no_invoice }}
                            </td>
                            <td class="table-cell">
                                {{ formatDate(row.tanggal) }}
                            </td>
                            <td class="table-cell">
                                {{ row.supplier || "-" }}
                            </td>
                            <td
                                class="table-cell font-bold text-center text-slate-700"
                            >
                                {{ row.items_count }}
                            </td>
                            <td class="table-cell text-slate-500">
                                {{ row.keterangan || "-" }}
                            </td>
                            <td
                                class="table-cell font-black text-right text-amber-600"
                            >
                                {{ formatCurrency(row.total) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0">
                        <tr class="border-t bg-sky-50 border-slate-200">
                            <td
                                colspan="4"
                                class="table-cell font-black tracking-wider text-center uppercase"
                            >
                                Total
                            </td>
                            <td class="table-cell font-black text-center">
                                {{ summary.items_count }}
                            </td>
                            <td class="table-cell"></td>
                            <td
                                class="table-cell font-black text-right text-amber-700"
                            >
                                {{ formatCurrency(summary.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between px-6 py-3 border-t border-slate-200 bg-slate-50"
            >
                <div class="text-sm text-slate-500">
                    Menampilkan
                    <span class="font-medium">{{
                        (pagination.current_page - 1) * pagination.per_page + 1
                    }}</span>
                    s/d
                    <span class="font-medium">{{
                        Math.min(
                            pagination.current_page * pagination.per_page,
                            pagination.total
                        )
                    }}</span>
                    dari
                    <span class="font-medium">{{ pagination.total }}</span>
                    hasil
                </div>
                <div class="flex gap-2">
                    <button
                        @click="fetchReport(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-3 py-1 text-sm font-medium bg-white border rounded border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Sebelumnya
                    </button>
                    <button
                        @click="fetchReport(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        class="px-3 py-1 text-sm font-medium bg-white border rounded border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
