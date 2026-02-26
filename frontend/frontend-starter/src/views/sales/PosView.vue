<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useAuthStore } from "../../stores/auth";
import { useRouter, useRoute } from "vue-router";
import api from "../../api";
import { useToast } from "../../composables/useToast";
import { storageUrl } from "../../utils/storage";

const toast = useToast();
const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

const editId = ref(route.query.edit_id || null);
const isEditing = computed(() => !!editId.value);

const searchContainer = ref(null);
const searchInput = ref(null);

function handleKeydown(e) {
    // Press '/' to focus search
    if (
        e.key === "/" &&
        document.activeElement.tagName !== "INPUT" &&
        document.activeElement.tagName !== "TEXTAREA"
    ) {
        e.preventDefault();
        searchInput.value?.focus();
    }
}

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
});
onUnmounted(() => {
    window.removeEventListener("keydown", handleKeydown);
});
const searchQuery = ref("");
const searchTimeout = ref(null);
const searchResults = ref([]);
const showDropdown = ref(false);

const cart = ref([]);
const taxOptions = ref([]);
const salesReps = ref([]);
const users = ref([]);

const form = ref({
    pelanggan: "Umum",
    user_id: authStore.user?.id || "",
    sales_rep_id: "",
    tax_id: "",
    tax_persen: 0,
    diskon_type: "persen", // 'persen' | 'nominal'
    diskon_persen: 0,
    diskon_nominal: 0,
    metode_pembayaran: "cash",
    jumlah_bayar: "",
});

const displayJumlahBayar = ref("");
const displayDiskonNominal = ref("");

function formatInputCurrency(val) {
    if (!val) return "";
    let str = val.toString().replace(/\D/g, "");
    return new Intl.NumberFormat("id-ID").format(str);
}

function parseInputCurrency(val) {
    if (!val) return 0;
    return parseInt(val.toString().replace(/\D/g, "")) || 0;
}

watch(
    () => displayJumlahBayar.value,
    (newVal) => {
        const rawValue = parseInputCurrency(newVal);
        if (form.value.jumlah_bayar !== rawValue) {
            form.value.jumlah_bayar = rawValue;
        }
        const formatted = formatInputCurrency(rawValue);
        if (displayJumlahBayar.value !== formatted) {
            displayJumlahBayar.value = formatted;
        }
    },
);

watch(
    () => displayDiskonNominal.value,
    (newVal) => {
        const rawValue = parseInputCurrency(newVal);
        if (form.value.diskon_nominal !== rawValue) {
            form.value.diskon_nominal = rawValue;
        }
        const formatted = formatInputCurrency(rawValue);
        if (displayDiskonNominal.value !== formatted) {
            displayDiskonNominal.value = formatted;
        }
    },
);

const isProcessing = ref(false);

onMounted(async () => {
    await fetchOptions();
    if (isEditing.value) {
        await fetchSaleDetails();
    }
    // Auto focus search input
    if (searchInput.value) {
        searchInput.value.focus();
    }

    // Global listener for barcode scanner (prevent default if needed)
    window.addEventListener("keydown", handleGlobalKeydown);
    window.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleGlobalKeydown);
    window.removeEventListener("click", handleClickOutside);
});

function handleClickOutside(e) {
    if (searchContainer.value && !searchContainer.value.contains(e.target)) {
        showDropdown.value = false;
    }
}

function handleGlobalKeydown(e) {
    // If not typing in an input/textarea, focus back to search on alphanumeric keypress
    if (
        e.key.length === 1 &&
        document.activeElement.tagName !== "INPUT" &&
        document.activeElement.tagName !== "TEXTAREA"
    ) {
        if (searchInput.value) {
            searchInput.value.focus();
        }
    }
}

async function fetchOptions() {
    try {
        const [resTax, resSales, resUsers] = await Promise.all([
            api.get("/taxes"),
            api.get("/sales-reps"),
            api.get("/user/all?role=kasir"),
        ]);
        taxOptions.value = resTax.data.data.data;
        salesReps.value = resSales.data.data.data;
        users.value = resUsers.data.data;

        // Default set to current user if not editing
        if (!isEditing.value && authStore.user) {
            form.value.user_id = authStore.user.id;
        }
    } catch (e) {
        console.error("Failed to fetch options", e);
    }
}

async function fetchSaleDetails() {
    try {
        const res = await api.get(`/sales/${editId.value}`);
        const sale = res.data.data;

        form.value.pelanggan = sale.pelanggan;
        form.value.user_id = sale.user?.id || "";
        form.value.sales_rep_id = sale.sales_rep_id || "";
        form.value.tax_id = sale.tax_id || "";
        form.value.tax_persen = parseFloat(sale.tax_persen) || 0;

        if (parseFloat(sale.diskon_persen) > 0) {
            form.value.diskon_type = "persen";
            form.value.diskon_persen = parseFloat(sale.diskon_persen);
        } else {
            form.value.diskon_type = "nominal";
            form.value.diskon_nominal = parseFloat(sale.diskon_nominal);
        }

        form.value.metode_pembayaran = sale.metode_pembayaran;
        form.value.jumlah_bayar = sale.jumlah_bayar;
        displayJumlahBayar.value = formatInputCurrency(sale.jumlah_bayar);

        if (sale.diskon_nominal > 0) {
            displayDiskonNominal.value = formatInputCurrency(
                sale.diskon_nominal,
            );
        }

        cart.value = sale.items.map((item) => ({
            id: item.product_id,
            nama: item.product?.nama,
            barcode: item.product?.barcode,
            foto: item.product?.foto,
            harga_jual: parseFloat(item.harga_satuan),
            qty: item.qty,
            stok: (item.product?.stok || 0) + item.qty,
            maxStok: (item.product?.stok || 0) + item.qty,
        }));
    } catch (e) {
        toast.error("Gagal memuat detail transaksi");
        editId.value = null;
    }
}

// Watch tax_id to auto update tax_persen
function onTaxChange() {
    const tax = taxOptions.value.find((t) => t.id === form.value.tax_id);
    if (tax) {
        form.value.tax_persen = tax.persentase;
    } else {
        form.value.tax_persen = 0;
    }
}

// Auto search when typing
function onSearchInput() {
    clearTimeout(searchTimeout.value);
    if (!searchQuery.value) {
        searchResults.value = [];
        showDropdown.value = false;
        // Fetch a few items even when empty just to show something
        fetchSearchResults("");
        return;
    }

    searchTimeout.value = setTimeout(() => {
        fetchSearchResults(searchQuery.value);
    }, 300);
}

async function fetchSearchResults(kw) {
    try {
        const res = await api.get("/products/search", {
            params: { keyword: kw },
        });
        searchResults.value = res.data.data;
        showDropdown.value = true;
    } catch (error) {
        console.error(error);
    }
}

function handleSearchFocus() {
    if (!searchQuery.value) {
        fetchSearchResults("");
    } else {
        showDropdown.value = true;
    }
}

// Enter on search input might mean Barcode Scan completion
async function onSearchEnter() {
    if (!searchQuery.value) return;

    try {
        const res = await api.get("/products/scan", {
            params: { code: searchQuery.value },
        });

        const product = res.data.data;
        addToCart(product);

        // Reset search
        searchQuery.value = "";
        searchResults.value = [];
        showDropdown.value = false;
    } catch (error) {
        // Barcode not exact match, let user select from dropdown if exists
        if (searchResults.value.length === 1) {
            addToCart(searchResults.value[0]);
            searchQuery.value = "";
            searchResults.value = [];
            showDropdown.value = false;
        } else if (searchResults.value.length === 0) {
            toast.error("Barang tidak ditemukan atau stok kosong");
        }
    }
}

function selectFromDropdown(product) {
    addToCart(product);
    searchQuery.value = "";
    searchResults.value = [];
    showDropdown.value = false;
    if (searchInput.value) searchInput.value.focus();
}

function addToCart(product) {
    const existing = cart.value.find((i) => i.id === product.id);
    if (existing) {
        if (existing.qty < (existing.maxStok || product.stok)) {
            existing.qty++;
        } else {
            toast.error("Stok tidak mencukupi");
        }
    } else {
        cart.value.push({
            ...product,
            qty: 1,
            maxStok: product.stok,
        });
    }
}

function updateQty(item, amount) {
    const newQty = item.qty + amount;
    if (newQty >= 1 && newQty <= item.maxStok) {
        item.qty = newQty;
    } else if (newQty > item.maxStok) {
        toast.error(`Maksimal stok: ${item.maxStok}`);
    } else if (newQty < 1) {
        // Option: remove from cart? Let's just keep at 1 or use remove button
        item.qty = 1;
    }
}

function removeFromCart(itemIdx) {
    cart.value.splice(itemIdx, 1);
}

const subtotal = computed(() => {
    return cart.value.reduce(
        (acc, item) => acc + item.harga_jual * item.qty,
        0,
    );
});

const diskonNominal = computed(() => {
    if (form.value.diskon_type === "persen") {
        return subtotal.value * ((form.value.diskon_persen || 0) / 100);
    }
    return form.value.diskon_nominal || 0;
});

const afterDiskon = computed(() => {
    return subtotal.value - diskonNominal.value;
});

const taxNominal = computed(() => {
    return afterDiskon.value * ((form.value.tax_persen || 0) / 100);
});

const grandTotal = computed(() => {
    return afterDiskon.value + taxNominal.value;
});

const kembalian = computed(() => {
    if (form.value.metode_pembayaran !== "cash") return 0;
    const jb = parseFloat(form.value.jumlah_bayar) || 0;
    return jb > grandTotal.value ? jb - grandTotal.value : 0;
});

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(val || 0);
}

async function processTransaction() {
    if (cart.value.length === 0) {
        toast.error("Keranjang belanja kosong");
        return;
    }

    isProcessing.value = true;
    try {
        const payload = {
            pelanggan: form.value.pelanggan,
            user_id: form.value.user_id || authStore.user?.id,
            sales_rep_id: form.value.sales_rep_id || null,
            tax_id: form.value.tax_id || null,
            tax_persen: form.value.tax_persen || 0,
            diskon_persen:
                form.value.diskon_type === "persen"
                    ? form.value.diskon_persen || 0
                    : 0,
            diskon_nominal:
                form.value.diskon_type === "nominal"
                    ? form.value.diskon_nominal || 0
                    : diskonNominal.value,
            metode_pembayaran: form.value.metode_pembayaran,
            jumlah_bayar:
                form.value.metode_pembayaran === "cash"
                    ? parseFloat(form.value.jumlah_bayar) || 0
                    : grandTotal.value,
            items: cart.value.map((c) => ({
                product_id: c.id,
                qty: c.qty,
                harga_satuan: c.harga_jual,
            })),
        };

        let res;
        if (isEditing.value) {
            res = await api.put(`/sales/${editId.value}`, payload);
            toast.success("Transaksi berhasil diperbarui!");
        } else {
            res = await api.post("/sales", payload);
            toast.success("Transaksi berhasil!");
        }

        // Navigate to print
        router.push(`/dashboard/pos/${res.data.data.id}/invoice`);
    } catch (error) {
        const msg = error.response?.data?.message || "Terjadi kesalahan";
        toast.error(msg);
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <div class="max-w-[1400px] mx-auto flex flex-col lg:flex-row gap-6">
        <!-- Main POS Area (Cart & Scanning) -->
        <div class="flex-1 flex flex-col gap-6">
            <!-- Scan / Search Bar -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-200 p-3 relative"
                ref="searchContainer"
            >
                <div class="relative">
                    <div
                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                    >
                        <svg
                            class="h-5 w-5 text-slate-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </div>
                    <input
                        ref="searchInput"
                        type="text"
                        v-model="searchQuery"
                        @input="onSearchInput"
                        @focus="handleSearchFocus"
                        @keydown.enter.prevent="onSearchEnter"
                        class="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-xl leading-5 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-base font-medium shadow-sm"
                        placeholder="Scan Barcode / IMEI / Cari nama barang... [/]"
                    />
                </div>

                <!-- Dropdown Search Results (Elegant Design) -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                >
                    <div
                        v-show="showDropdown"
                        class="absolute z-[100] mt-3 w-full left-0 glass rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.1)] border border-white/40 max-h-[500px] overflow-hidden flex flex-col animate-in-fade"
                    >
                        <div
                            class="px-4 py-2 bg-slate-50 border-b border-slate-100 flex justify-between items-center shrink-0"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-widest text-slate-400"
                                >Hasil Pencarian Produk</span
                            >
                            <span
                                class="text-[10px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-bold"
                                >{{ searchResults.length }} Barang
                                ditemukan</span
                            >
                        </div>
                        <div class="overflow-y-auto flex-1 custom-scrollbar">
                            <ul class="divide-y divide-slate-50">
                                <li
                                    v-for="res in searchResults"
                                    :key="res.id"
                                    @click="selectFromDropdown(res)"
                                    class="px-4 py-1.5 hover:bg-blue-50/50 cursor-pointer flex items-center gap-4 transition group"
                                >
                                    <div
                                        class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 overflow-hidden border border-slate-200 group-hover:border-blue-200 transition shadow-sm"
                                    >
                                        <img
                                            v-if="res.foto"
                                            :src="storageUrl(res.foto)"
                                            class="w-full h-full object-cover group-hover:scale-110 transition duration-500"
                                        />
                                        <svg
                                            v-else
                                            class="w-7 h-7 text-slate-300 group-hover:text-blue-300 transition"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4
                                            class="text-xs font-black text-slate-800 truncate group-hover:text-blue-700 transition"
                                        >
                                            {{ res.nama }}
                                        </h4>
                                        <div
                                            class="flex flex-wrap items-center gap-2 mt-1"
                                        >
                                            <span
                                                class="text-[10px] font-mono bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200"
                                                >{{
                                                    res.barcode || "NO-BARCODE"
                                                }}</span
                                            >
                                            <span
                                                v-if="res.unit"
                                                class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded"
                                                >{{ res.unit.nama }}</span
                                            >
                                            <span
                                                v-if="res.stok > 0"
                                                class="text-[10px] font-bold px-1.5 py-0.5 bg-emerald-50 text-emerald-600 rounded border border-emerald-100"
                                                >Stok: {{ res.stok }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-[10px] font-bold px-1.5 py-0.5 bg-rose-50 text-rose-600 rounded border border-rose-100"
                                                >Stok Kosong</span
                                            >
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p
                                            class="text-sm font-black text-blue-600 tracking-tight"
                                        >
                                            {{ formatCurrency(res.harga_jual) }}
                                        </p>
                                        <div
                                            class="text-[9px] font-bold text-slate-400 uppercase mt-0.5"
                                        >
                                            Klik untuk tambah
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div
                            v-if="searchResults.length >= 10"
                            class="px-4 py-2 bg-slate-50 border-t border-slate-100 text-center text-[10px] text-slate-400 font-bold italic"
                        >
                            Hasil dibatasi (silakan ketik lebih detail)
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Cart Table -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex-1 flex flex-col"
            >
                <div
                    class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center"
                >
                    <h2 class="text-base font-bold text-slate-800">
                        Keranjang Belanja
                    </h2>
                    <span
                        class="text-xs font-semibold px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg"
                        >{{ cart.length }} Item</span
                    >
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div
                        v-if="cart.length === 0"
                        class="h-full flex flex-col items-center justify-center text-slate-400 p-8 space-y-4"
                    >
                        <svg
                            class="w-16 h-16 opacity-50"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                        <p class="text-sm font-medium">
                            Belum ada barang di keranjang
                        </p>
                    </div>

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="(item, idx) in cart"
                            :key="item.id"
                            class="p-4 hover:bg-slate-50 transition flex items-center gap-4"
                        >
                            <div
                                class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 overflow-hidden"
                            >
                                <img
                                    v-if="item.foto"
                                    :src="storageUrl(item.foto)"
                                    class="w-full h-full object-cover"
                                />
                                <svg
                                    v-else
                                    class="w-6 h-6 text-slate-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0">
                                <h4
                                    class="text-sm font-bold text-slate-800 truncate"
                                >
                                    {{ item.nama }}
                                </h4>
                                <div
                                    class="flex items-center gap-3 mt-1 text-xs text-slate-500"
                                >
                                    <span
                                        class="font-mono text-blue-600 font-semibold"
                                        >{{ item.barcode }}</span
                                    >
                                    <span>{{
                                        formatCurrency(item.harga_jual)
                                    }}</span>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-2 bg-slate-100 rounded-lg p-1 shrink-0"
                            >
                                <button
                                    @click="updateQty(item, -1)"
                                    class="w-8 h-8 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-slate-600 transition"
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
                                            d="M20 12H4"
                                        />
                                    </svg>
                                </button>
                                <span
                                    class="w-8 text-center text-sm font-bold text-slate-700"
                                    >{{ item.qty }}</span
                                >
                                <button
                                    @click="updateQty(item, 1)"
                                    class="w-8 h-8 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-slate-600 transition"
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
                                </button>
                            </div>

                            <div class="text-right w-28 shrink-0">
                                <p class="text-sm font-bold text-slate-800">
                                    {{
                                        formatCurrency(
                                            item.harga_jual * item.qty,
                                        )
                                    }}
                                </p>
                            </div>

                            <button
                                @click="removeFromCart(idx)"
                                class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition ml-2 shrink-0"
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
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    />
                                </svg>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Side: Check Out Details -->
        <div class="w-full lg:w-[380px] flex flex-col gap-6 shrink-0">
            <!-- Payment Block -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col"
            >
                <div class="p-4 space-y-4">
                    <div>
                        <label
                            class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                            >Pelanggan</label
                        >
                        <input
                            type="text"
                            v-model="form.pelanggan"
                            class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium"
                            placeholder="Nama Pelanggan / Umum"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4"
                                >Pajak</label
                            >
                            <select
                                v-model="form.tax_id"
                                @change="onTaxChange"
                                class="block w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            >
                                <option value="">Tanpa Pajak</option>
                                <option
                                    v-for="tax in taxOptions"
                                    :key="tax.id"
                                    :value="tax.id"
                                >
                                    {{ tax.nama }} ({{ tax.persentase }}%)
                                </option>
                            </select>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label
                                    class="block text-xs font-semibold text-slate-500 uppercase tracking-widest"
                                    >Diskon</label
                                >
                                <div
                                    class="flex bg-slate-100 rounded-lg p-0.5 text-[10px] font-bold"
                                >
                                    <button
                                        @click="form.diskon_type = 'persen'"
                                        :class="
                                            form.diskon_type === 'persen'
                                                ? 'bg-white shadow text-slate-800'
                                                : 'text-slate-500'
                                        "
                                        class="px-2 py-0.5 rounded transition"
                                    >
                                        %
                                    </button>
                                    <button
                                        @click="form.diskon_type = 'nominal'"
                                        :class="
                                            form.diskon_type === 'nominal'
                                                ? 'bg-white shadow text-slate-800'
                                                : 'text-slate-500'
                                        "
                                        class="px-2 py-0.5 rounded transition"
                                    >
                                        Rp
                                    </button>
                                </div>
                            </div>
                            <input
                                v-if="form.diskon_type === 'persen'"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                v-model.number="form.diskon_persen"
                                class="block w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                                placeholder="Contoh: 10"
                            />
                            <div class="relative">
                                <input
                                    v-if="form.diskon_type === 'nominal'"
                                    type="text"
                                    v-model="displayDiskonNominal"
                                    class="block w-full pl-8 pr-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm font-bold text-rose-600"
                                    placeholder="0"
                                />
                                <span
                                    v-if="form.diskon_type === 'nominal'"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400"
                                    >Rp</span
                                >
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                            >Kasir</label
                        >
                        <select
                            v-model="form.user_id"
                            :disabled="!authStore.isAdmin"
                            class="block w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm disabled:bg-slate-50 disabled:text-slate-500"
                        >
                            <option
                                v-for="u in users"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                            >Sales Rep</label
                        >
                        <select
                            v-model="form.sales_rep_id"
                            class="block w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        >
                            <option value="">
                                -- Pilih Sales (Opsional) --
                            </option>
                            <option
                                v-for="rep in salesReps"
                                :key="rep.id"
                                :value="rep.id"
                            >
                                {{ rep.nama }}
                            </option>
                        </select>
                    </div>

                    <div class="h-px bg-slate-100 my-1"></div>

                    <!-- Cost Details -->
                    <div class="space-y-2">
                        <div
                            class="flex justify-between items-center text-xs font-medium text-slate-600"
                        >
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div
                            v-if="diskonNominal > 0"
                            class="flex justify-between items-center text-xs font-medium text-emerald-600"
                        >
                            <span>Diskon ({{ form.diskon_persen }}%)</span>
                            <span>- {{ formatCurrency(diskonNominal) }}</span>
                        </div>
                        <div
                            v-if="taxNominal > 0"
                            class="flex justify-between items-center text-xs font-medium text-rose-500"
                        >
                            <span>Pajak ({{ form.tax_persen }}%)</span>
                            <span>+ {{ formatCurrency(taxNominal) }}</span>
                        </div>
                        <div class="h-px bg-slate-100 my-0.5"></div>
                        <div
                            class="flex justify-between items-center text-sm font-bold text-slate-800"
                        >
                            <span>Total</span>
                            <span>{{ formatCurrency(grandTotal) }}</span>
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div
                        class="bg-blue-50/50 rounded-xl p-4 border border-blue-100 mt-4"
                    >
                        <label
                            class="block text-xs font-semibold text-blue-800 uppercase tracking-widest mb-2"
                            >Metode Pembayaran</label
                        >
                        <select
                            v-model="form.metode_pembayaran"
                            class="block w-full px-3 py-2 bg-white border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm mb-3"
                        >
                            <option value="cash">tunai / Cash</option>
                            <option value="transfer">Bank Transfer</option>
                            <option value="qris">QRIS</option>
                        </select>

                        <div v-if="form.metode_pembayaran === 'cash'">
                            <label
                                class="block text-xs font-semibold text-blue-800 uppercase tracking-widest mb-2"
                                >Jumlah Bayar</label
                            >
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="displayJumlahBayar"
                                    class="block w-full pl-10 pr-3 py-3 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm mb-3 font-black text-blue-700 text-xl bg-white shadow-inner"
                                    placeholder="0"
                                />
                                <span
                                    class="absolute left-3 top-[18px] text-sm font-black text-blue-400"
                                    >Rp</span
                                >
                            </div>

                            <div
                                class="flex justify-between items-center text-sm"
                            >
                                <span class="font-medium text-blue-800"
                                    >Kembalian</span
                                >
                                <span
                                    class="font-bold text-blue-600"
                                    :class="{ 'text-rose-500': kembalian < 0 }"
                                    >{{ formatCurrency(kembalian) }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 border-t border-slate-100">
                    <div
                        class="flex justify-between items-center mb-1 text-[10px] text-slate-400 uppercase tracking-widest font-bold"
                    >
                        <span
                            >Kasir:
                            {{
                                users.find((u) => u.id === form.user_id)
                                    ?.name || authStore.user?.name
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between items-end mb-4">
                        <span
                            class="text-xs font-bold text-slate-400 uppercase tracking-widest"
                            >Total Bayar</span
                        >
                        <span
                            class="text-2xl font-bold tracking-tight text-blue-600"
                            >{{ formatCurrency(grandTotal) }}</span
                        >
                    </div>

                    <button
                        @click="processTransaction"
                        :disabled="cart.length === 0 || isProcessing"
                        class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-3 rounded-xl hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-blue-500/30 transition transform active:scale-95"
                    >
                        <template v-if="isProcessing">
                            <svg
                                class="animate-spin h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            Memproses...
                        </template>
                        <template v-else>
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            PROSES BAYAR
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
