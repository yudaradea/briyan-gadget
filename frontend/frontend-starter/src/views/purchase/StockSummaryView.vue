<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api";
import { useToast } from "../../composables/useToast";
import debounce from "lodash-es/debounce";

const toast = useToast();

const items = ref([]);
const categories = ref([]);
const brands = ref([]);
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
    category_id: "",
    brand_id: "",
});

onMounted(() => {
    fetchItems();
    fetchCategories();
    fetchBrands();
});

async function fetchCategories() {
    try {
        const { data } = await api.get("/categories/all");
        categories.value = data.data;
    } catch (e) {
        console.error("Gagal memuat kategori", e);
    }
}

async function fetchBrands() {
    try {
        const { data } = await api.get("/brands/all");
        brands.value = data.data;
    } catch (e) {
        console.error("Gagal memuat merk", e);
    }
}

async function fetchItems(page = 1) {
    isLoading.value = true;
    try {
        const { data } = await api.get("/products", {
            params: {
                page,
                per_page: perPage.value,
                search: searchQuery.value,
                category_id: filters.value.category_id,
                brand_id: filters.value.brand_id,
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
        toast.error("Gagal memuat stok barang");
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
        fetchItems(1);
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
</script>

<template>
    <div class="px-4 md:px-8 mx-auto py-6 space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Stok Barang (Ready)
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Ringkasan stok barang yang tersedia untuk dijual (digabung
                    per tipe/grade)
                </p>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex flex-wrap items-end gap-5">
                <!-- Search -->
                <div class="flex-1 min-w-[280px]">
                    <label
                        class="text-[11px] font-bold text-slate-400 uppercase mb-2 block tracking-wider"
                        >Cari Produk</label
                    >
                    <div class="relative group">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari nama barang..."
                            class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition-all"
                        />
                        <div
                            class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors"
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
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Category Filter -->
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[11px] font-bold text-slate-400 uppercase block tracking-wider"
                            >Kategori</label
                        >
                        <select
                            v-model="filters.category_id"
                            class="appearance-none px-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 transition-all min-w-[140px]"
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
                    </div>

                    <!-- Brand Filter -->
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[11px] font-bold text-slate-400 uppercase block tracking-wider"
                            >Merk</label
                        >
                        <select
                            v-model="filters.brand_id"
                            class="appearance-none px-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 transition-all min-w-[140px]"
                        >
                            <option value="">Semua Merk</option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.nama }}
                            </option>
                        </select>
                    </div>

                    <!-- Reset -->
                    <button
                        @click="
                            filters = { category_id: '', brand_id: '' };
                            searchQuery = '';
                        "
                        class="p-2.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-xl transition-all"
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
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 1111.106 4.106c.47-.044.948-.066 1.432-.066"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div
            class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider"
                            >
                                Barang
                            </th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center"
                            >
                                Brand & Kategori
                            </th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center"
                            >
                                Grade
                            </th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center"
                            >
                                Total Stok
                            </th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right"
                            >
                                Harga Jual
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="isLoading">
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-slate-400"
                            >
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="w-6 h-6 border-2 border-slate-200 border-t-blue-500 rounded-full animate-spin"
                                    ></div>
                                    <span class="text-xs font-medium"
                                        >Memuat data stok...</span
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-else-if="items.length === 0"
                            class="hover:bg-slate-50/50 transition-colors"
                        >
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-slate-400"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <svg
                                        class="w-10 h-10 text-slate-200"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                        />
                                    </svg>
                                    <span class="text-sm font-medium"
                                        >Tidak ada produk ditemukan</span
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="hover:bg-slate-50/50 transition-all group"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 shrink-0"
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
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <div
                                            class="font-bold text-slate-700 group-hover:text-blue-600 transition-colors capitalize"
                                        >
                                            {{ item.nama }}
                                        </div>
                                        <div
                                            class="text-[10px] font-bold text-slate-400 mt-0.5 uppercase tracking-wider"
                                        >
                                            {{ item.unit?.nama || "Unit" }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex flex-col gap-1">
                                    <span
                                        class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase tracking-tight"
                                    >
                                        {{ item.brand?.nama || "Generic" }}
                                    </span>
                                    <span
                                        class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-600 text-[10px] font-bold uppercase tracking-tight"
                                    >
                                        {{ item.category?.nama }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    v-if="item.grade"
                                    class="px-2.5 py-1 rounded-lg bg-purple-50 text-purple-600 text-[10px] font-bold uppercase"
                                >
                                    {{ item.grade?.nama }}
                                </span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-black text-slate-800">
                                    {{ item.total_stok }}
                                </span>
                                <span
                                    class="text-[10px] font-bold text-slate-400 ml-1 uppercase"
                                    >Ready</span
                                >
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-black text-blue-600">
                                    {{ formatCurrency(item.harga_jual) }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between"
            >
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-500 font-medium">
                        Menampilkan <b>{{ items.length }}</b> dari
                        <b>{{ pagination.total }}</b> produk
                    </span>
                    <select
                        v-model="perPage"
                        class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:ring-0"
                    >
                        <option :value="10">10 / hal</option>
                        <option :value="25">25 / hal</option>
                        <option :value="50">50 / hal</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="fetchItems(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold hover:bg-white transition-all disabled:opacity-30 disabled:hover:bg-transparent"
                    >
                        Sebelumnya
                    </button>
                    <div
                        class="flex items-center px-4 rounded-lg bg-blue-50 text-blue-600 font-bold text-xs border border-blue-100"
                    >
                        {{ pagination.current_page }} /
                        {{ pagination.last_page }}
                    </div>
                    <button
                        @click="fetchItems(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page >= pagination.last_page
                        "
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold hover:bg-white transition-all disabled:opacity-30 disabled:hover:bg-transparent"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
