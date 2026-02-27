<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api";
import ConfirmDialog from "../../components/ConfirmDialog.vue";
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import debounce from "lodash-es/debounce";

const router = useRouter();
const toast = useToast();

const items = ref([]);
const suppliers = ref([]);
const categories = ref([]);
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
    category_id: "",
    no_invoice: "",
    status: "",
});

const showDelete = ref(false);
const deleteId = ref(null);
const deleting = ref(false);

onMounted(() => {
    fetchItems();
    fetchSuppliers();
    fetchCategories();
});

async function fetchCategories() {
    try {
        const { data } = await api.get("/categories/all");
        categories.value = data.data;
    } catch (e) {
        console.error("Gagal memuat kategori", e);
    }
}

async function fetchSuppliers() {
    try {
        const { data } = await api.get("/suppliers/all");
        suppliers.value = data.data;
    } catch (e) {
        console.error("Gagal memuat supplier", e);
    }
}

async function fetchItems(page = 1) {
    isLoading.value = true;
    try {
        const { data } = await api.get("/purchases/items", {
            params: {
                page,
                per_page: perPage.value,
                search: searchQuery.value,
                start_date: filters.value.start_date,
                end_date: filters.value.end_date,
                supplier_id: filters.value.supplier_id,
                category_id: filters.value.category_id,
                no_invoice: filters.value.no_invoice,
                status: filters.value.status,
            },
        });

        items.value = data.data.data;
        pagination.value = {
            current_page: data.data.current_page,
            last_page: data.data.last_page,
            total: data.data.total,
            per_page: data.data.per_page,
        };
    } catch (err) {
        toast.error("Gagal memuat detail stok");
    } finally {
        isLoading.value = false;
    }
}

const throttledSearch = debounce(() => {
    fetchItems(1);
}, 500);

watch(searchQuery, () => {
    throttledSearch();
});

watch(perPage, () => {
    fetchItems(1);
});

watch(
    () => filters.value,
    () => {
        if (
            filters.value.start_date &&
            filters.value.end_date &&
            filters.value.end_date < filters.value.start_date
        ) {
            toast.error(
                "Tanggal akhir tidak boleh lebih awal dari tanggal awal"
            );
            filters.value.end_date = "";
            return;
        }
        fetchItems(1);
    },
    { deep: true }
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

async function printBarcode(purchaseId) {
    if (!purchaseId) return toast.error("ID Pembelian tidak tersedia");
    router.push({
        name: "purchase-barcode",
        params: { id: purchaseId },
    });
}

function confirmDelete(id, purchaseId) {
    deleteId.value = { id, purchaseId };
    showDelete.value = true;
}

async function doDelete() {
    deleting.value = true;
    try {
        const { id, purchaseId } = deleteId.value;
        await api.delete(`/purchases/${purchaseId}/items/${id}`);
        toast.success("Item berhasil dihapus dari transaksi");
        showDelete.value = false;
        fetchItems(pagination.value.current_page);
    } catch (err) {
        toast.error(err.response?.data?.message || "Gagal menghapus item");
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <div class="px-4 py-6 mx-auto space-y-6 md:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    List Semua Barang
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    Daftar breakdown semua unit unit/imei yang masuk
                </p>
            </div>
            <div class="flex gap-2">
                <router-link
                    to="/dashboard/purchases"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-semibold transition-all bg-white border text-slate-600 rounded-xl border-slate-200 hover:bg-slate-50"
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
                            d="M4 6h16M4 10h16M4 14h16M4 18h16"
                        />
                    </svg>
                    Stok per Supplier
                </router-link>
            </div>
        </div>

        <div
            class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
        >
            <!-- Header Actions -->
            <div
                class="flex flex-col items-end justify-between gap-4 px-6 py-4 border-b border-slate-200 bg-slate-50 md:flex-row"
            >
                <!-- Left Side: Advanced Filters -->
                <div
                    class="grid items-end w-full grid-cols-2 gap-3 md:grid-cols-4 lg:flex md:w-auto"
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
                            >Status</label
                        >
                        <div class="relative">
                            <select
                                v-model="filters.status"
                                class="appearance-none w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white min-w-[120px] pr-8"
                            >
                                <option value="">Semua</option>
                                <option value="available">Tersedia</option>
                                <option value="sold">Terjual</option>
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
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Kategori</label
                        >
                        <div class="relative">
                            <select
                                v-model="filters.category_id"
                                class="appearance-none w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white min-w-[140px] pr-8 shadow-sm"
                            >
                                <option value="">Semua Kategori</option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.nama }}
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
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >Supplier</label
                        >
                        <div class="relative">
                            <select
                                v-model="filters.supplier_id"
                                class="appearance-none w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white min-w-[140px] pr-8"
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
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase"
                            >No. Invoice</label
                        >
                        <input
                            v-model="filters.no_invoice"
                            type="text"
                            placeholder="INV..."
                            class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-white w-32"
                        />
                    </div>
                    <div class="flex flex-col gap-1 lg:justify-end pb-0.5">
                        <button
                            @click="
                                filters = {
                                    status: '',
                                    start_date: '',
                                    end_date: '',
                                    supplier_id: '',
                                    category_id: '',
                                    no_invoice: '',
                                };
                                searchQuery = '';
                            "
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

                <!-- Right Side -->
                <div class="flex flex-row items-end w-full gap-2 md:w-auto">
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
                                placeholder="Cari barang/IMEI..."
                                class="block w-full md:w-64 pl-10 pr-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition shadow-sm"
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
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <table class="table-fixed-layout table-wide">
                    <thead class="table-header">
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th class="w-32">Kode</th>
                            <th class="w-56 text-left">Nama Barang</th>
                            <th class="w-24">Satuan</th>
                            <th class="w-32">Kategori</th>
                            <th class="w-24">Grade</th>
                            <th class="w-40">IMEI 1</th>
                            <th class="w-40">IMEI 2</th>
                            <th class="w-20 text-center">Stok</th>
                            <th class="w-32 text-right">Modal</th>
                            <th class="w-32 text-right">Jual</th>
                            <th class="w-40">No Invoice</th>
                            <th class="w-40">Supplier</th>
                            <th class="w-32">Tanggal</th>
                            <th class="w-48">Keterangan</th>
                            <th class="text-left w-28">Status</th>
                            <th class="table-col-action-h">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-if="isLoading">
                            <td
                                colspan="17"
                                class="px-6 py-12 text-center text-slate-500"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <div
                                        class="w-8 h-8 border-4 rounded-full border-slate-200 border-t-blue-500 animate-spin"
                                    ></div>
                                    <span class="text-sm font-medium"
                                        >Memuat data detail...</span
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="items.length === 0">
                            <td
                                colspan="17"
                                class="px-6 py-12 italic text-center text-slate-500"
                            >
                                Tidak ada data ditemukan.
                            </td>
                        </tr>
                        <tr
                            v-for="(item, index) in items"
                            :key="item.id"
                            class="table-row group"
                        >
                            <td class="table-cell text-center text-slate-500">
                                {{
                                    (pagination.current_page - 1) *
                                        pagination.per_page +
                                    index +
                                    1
                                }}
                            </td>
                            <td
                                class="table-cell font-mono text-[10px] text-slate-600"
                            >
                                {{ item.product?.barcode }}
                            </td>
                            <td class="table-cell font-semibold text-slate-800">
                                {{ item.product?.nama }}
                            </td>
                            <td class="table-cell text-slate-500">
                                {{ item.product?.unit || "-" }}
                            </td>
                            <td class="table-cell text-slate-500">
                                {{ item.product?.category || "-" }}
                            </td>
                            <td class="table-cell">
                                <span
                                    class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-bold border border-blue-100 uppercase"
                                >
                                    {{ item.product?.grade || "-" }}
                                </span>
                            </td>
                            <td class="table-cell font-medium text-slate-600">
                                {{ item.product?.imei1 || "-" }}
                            </td>
                            <td class="table-cell font-medium text-slate-600">
                                {{ item.product?.imei2 || "-" }}
                            </td>
                            <td
                                class="table-cell font-bold text-center text-slate-700"
                            >
                                {{ item.product?.stok }}
                            </td>
                            <td
                                class="table-cell font-bold text-right text-slate-500"
                            >
                                {{ formatCurrency(item.harga_beli) }}
                            </td>
                            <td
                                class="table-cell font-bold text-right text-emerald-600"
                            >
                                {{ formatCurrency(item.product?.harga_jual) }}
                            </td>
                            <td class="table-cell font-medium text-blue-600">
                                {{ item.purchase?.no_invoice }}
                            </td>
                            <td class="table-cell text-slate-600">
                                {{ item.purchase?.supplier?.nama || "-" }}
                            </td>
                            <td class="table-cell text-slate-500">
                                {{ formatDate(item.purchase?.tanggal) }}
                            </td>
                            <td
                                class="table-cell text-slate-400 italic truncate max-w-[150px]"
                            >
                                {{ item.product?.keterangan || "-" }}
                            </td>
                            <td class="table-cell">
                                <span
                                    v-if="item.product?.is_sold"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-100"
                                >
                                    Terjual
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100"
                                >
                                    Tersedia
                                </span>
                            </td>
                            <!-- Sticky Ops Column for better UX -->
                            <td class="table-col-action">
                                <div class="table-actions">
                                    <button
                                        @click="printBarcode(item.purchase_id)"
                                        class="p-1.5 text-purple-500 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak Barcode"
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
                                    </button>
                                    <router-link
                                        :to="{
                                            name: 'purchase-edit',
                                            params: { id: item.purchase_id },
                                        }"
                                        class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition"
                                        title="Edit Transaksi"
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
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                            />
                                        </svg>
                                    </router-link>
                                    <button
                                        @click="
                                            confirmDelete(
                                                item.id,
                                                item.purchase_id
                                            )
                                        "
                                        class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition"
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
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between px-6 py-3 border-t border-slate-200 bg-slate-50"
            >
                <div class="text-[11px] text-slate-500">
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
                        @click="fetchItems(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-3 py-1 text-sm font-medium bg-white border rounded border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Sebelumnya
                    </button>
                    <button
                        @click="fetchItems(pagination.current_page + 1)"
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

        <ConfirmDialog
            :show="showDelete"
            :loading="deleting"
            @confirm="doDelete"
            @cancel="showDelete = false"
        />
    </div>
</template>
