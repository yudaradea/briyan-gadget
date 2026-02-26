<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api";
import ConfirmDialog from "../../components/ConfirmDialog.vue";
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import debounce from "lodash-es/debounce";

const router = useRouter();
const toast = useToast();

const purchases = ref([]);
const suppliers = ref([]);
const isLoading = ref(false);
const searchQuery = ref("");
const perPage = ref(10);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 10,
});

const filters = ref({
    start_date: "",
    end_date: "",
    supplier_id: "",
});

const showDelete = ref(false);
const deleteId = ref(null);
const deleting = ref(false);

onMounted(() => {
    fetchPurchases();
    fetchSuppliers();
});

async function fetchSuppliers() {
    try {
        const { data } = await api.get("/suppliers/all");
        suppliers.value = data.data;
    } catch (e) {
        console.error("Gagal memuat supplier", e);
    }
}

async function fetchPurchases(page = 1) {
    isLoading.value = true;
    try {
        const { data } = await api.get("/purchases", {
            params: {
                page,
                per_page: perPage.value,
                search: searchQuery.value,
                start_date: filters.value.start_date,
                end_date: filters.value.end_date,
                supplier_id: filters.value.supplier_id,
            },
        });

        purchases.value = data.data.data;
        pagination.value = {
            current_page: data.data.current_page,
            last_page: data.data.last_page,
            total: data.data.total,
            per_page: data.data.per_page,
        };
    } catch (err) {
        toast.error("Gagal memuat data stok");
    } finally {
        isLoading.value = false;
    }
}

const throttledSearch = debounce(() => {
    fetchPurchases(1);
}, 500);

watch(searchQuery, () => {
    throttledSearch();
});

watch(perPage, () => {
    fetchPurchases(1);
});

watch(
    () => filters.value,
    () => {
        // Validation: end date shouldn't be before start date
        if (
            filters.value.start_date &&
            filters.value.end_date &&
            filters.value.end_date < filters.value.start_date
        ) {
            toast.error(
                "Tanggal akhir tidak boleh lebih awal dari tanggal awal",
            );
            filters.value.end_date = "";
            return;
        }
        fetchPurchases(1);
    },
    { deep: true },
);

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(val || 0);
}

function formatDate(dateStr) {
    if (!dateStr) return "-";
    const [y, m, d] = dateStr.split("-");
    return `${d}-${m}-${y}`;
}

function confirmDelete(id) {
    deleteId.value = id;
    showDelete.value = true;
}

async function doDelete() {
    deleting.value = true;
    try {
        await api.delete(`/purchases/${deleteId.value}`);
        toast.success("Transaksi berhasil dihapus dan stok diperbarui");
        showDelete.value = false;
        fetchPurchases(pagination.value.current_page);
    } catch (err) {
        toast.error(err.response?.data?.message || "Gagal menghapus");
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <div class="px-4 md:px-8 mx-auto py-6 space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Stok Barang (Masuk)
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Kelola stok barang masuk dari supplier
                </p>
            </div>
            <div class="flex gap-2">
                <router-link
                    to="/dashboard/purchase-items"
                    class="px-4 py-2 bg-white text-slate-600 text-sm font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all flex items-center gap-2"
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
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                        />
                    </svg>
                    Lihat per Barang
                </router-link>
                <router-link
                    to="/dashboard/purchases/create"
                    class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold rounded-xl hover:shadow-lg hover:shadow-blue-500/25 transition-all flex items-center gap-2"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Tambah Stok Barang
                </router-link>
            </div>
        </div>

        <div
            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden"
        >
            <!-- Header Actions (Modified to match Sales List) -->
            <div
                class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center"
            >
                <!-- Left Side: Advanced Filters -->
                <div
                    class="grid grid-cols-2 md:grid-cols-4 lg:flex gap-3 w-full md:w-auto"
                >
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Mulai</label
                        >
                        <input
                            type="date"
                            v-model="filters.start_date"
                            class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Sampai</label
                        >
                        <input
                            type="date"
                            v-model="filters.end_date"
                            :min="filters.start_date"
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
                                class="appearance-none w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white min-w-[160px] pr-8"
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
                                    class="h-4 w-4"
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
                        <button
                            @click="
                                filters = {
                                    start_date: '',
                                    end_date: '',
                                    supplier_id: '',
                                }
                            "
                            class="p-2 text-slate-400 hover:text-rose-500 transition-colors"
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

                <!-- Right Side: Per Page & Search Combined -->
                <div class="flex flex-row items-end gap-2 w-full md:w-auto">
                    <!-- Per Page -->
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
                                    class="h-4 w-4"
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

                    <!-- Simple Search -->
                    <div class="flex flex-col gap-1 grow md:grow-0">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Search</label
                        >
                        <div class="relative">
                            <input
                                type="text"
                                v-model="searchQuery"
                                placeholder="Cari invoice/supplier..."
                                class="block w-full md:w-64 pl-10 pr-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition shadow-sm"
                            />
                            <div
                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                            >
                                <svg
                                    class="h-4 w-4 text-slate-400"
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
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-16 text-center"
                            >
                                No
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider"
                            >
                                No. Invoice
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider"
                            >
                                Tanggal
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider"
                            >
                                Supplier
                            </th>
                            <th
                                class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider"
                            >
                                Jumlah Item
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider"
                            >
                                Total
                            </th>
                            <th
                                class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-36"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-if="isLoading">
                            <td
                                colspan="7"
                                class="px-6 py-4 text-center text-slate-500"
                            >
                                Memuat data...
                            </td>
                        </tr>
                        <tr v-else-if="purchases.length === 0">
                            <td
                                colspan="7"
                                class="px-6 py-4 text-center text-slate-500"
                            >
                                Tidak ada data stok ditemukan.
                            </td>
                        </tr>
                        <tr
                            v-for="(p, index) in purchases"
                            :key="p.id"
                            class="hover:bg-slate-50 transition"
                        >
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center"
                            >
                                {{
                                    (pagination.current_page - 1) *
                                        pagination.per_page +
                                    index +
                                    1
                                }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600"
                            >
                                {{ p.no_invoice }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"
                            >
                                {{ formatDate(p.tanggal) }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-slate-800 font-medium"
                            >
                                {{ p.supplier?.nama || "-" }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-center"
                            >
                                <span
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-600"
                                >
                                    {{ p.items_count || 0 }} item
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-bold text-emerald-600 text-right"
                            >
                                {{ formatCurrency(p.total) }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center space-x-1"
                            >
                                <router-link
                                    :to="`/dashboard/purchases/${p.id}`"
                                    class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg inline-flex"
                                    title="Detail"
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
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                        />
                                    </svg>
                                </router-link>
                                <router-link
                                    :to="`/dashboard/purchases/${p.id}/edit`"
                                    class="p-1.5 text-emerald-500 hover:bg-emerald-50 rounded-lg inline-flex"
                                    title="Tambah Item"
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
                                            d="M12 4v16m8-8H4"
                                        />
                                    </svg>
                                </router-link>
                                <router-link
                                    :to="`/dashboard/purchases/${p.id}/barcode`"
                                    class="p-1.5 text-purple-500 hover:bg-purple-50 rounded-lg inline-flex"
                                    title="Barcode"
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
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                                        />
                                    </svg>
                                </router-link>
                                <button
                                    @click="confirmDelete(p.id)"
                                    class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg inline-flex"
                                    title="Hapus"
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
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="px-6 py-3 border-t border-slate-200 flex items-center justify-between bg-slate-50"
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
                            pagination.total,
                        )
                    }}</span>
                    dari
                    <span class="font-medium">{{ pagination.total }}</span>
                    hasil
                </div>
                <div class="flex gap-2">
                    <button
                        @click="fetchPurchases(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-3 py-1 border border-slate-300 rounded text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed bg-white"
                    >
                        Sebelumnya
                    </button>
                    <button
                        @click="fetchPurchases(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        class="px-3 py-1 border border-slate-300 rounded text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed bg-white"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showDelete"
            :loading="deleting"
            @confirm="doDelete"
            @cancel="showDelete = false"
        />
    </div>
</template>
