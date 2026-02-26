<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter } from "vue-router";
import api from "../../api";
import { useToast } from "../../composables/useToast";

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(val || 0);
}

const router = useRouter();
const toast = useToast();

const isLoading = ref(false);
const today = new Date().toISOString().split("T")[0];

const form = ref({
    nama_pelanggan: "",
    no_hp_pelanggan: "",
    merk_hp: "",
    tipe_hp: "",
    kerusakan: "",
    imei_hp: "",
    kelengkapan: "",
    biaya_jasa: 0,
    tanggal_masuk: today,
    parts: [],
});

const displayBiayaJasa = ref("0");

// Sparepart state
const isSearchingPart = ref(false);
const partSearch = ref("");
const partResults = ref([]);
const selectedPart = ref(null);
const tempQty = ref(1);
const showPartDropdown = ref(false);
const partSearchWrapper = ref(null);

async function searchParts(forceOpen = false) {
    if (forceOpen) {
        showPartDropdown.value = true;
    }
    isSearchingPart.value = true;
    try {
        const { data } = await api.get("/products/search", {
            params: {
                keyword: partSearch.value || "",
                category: "Sparepart",
            },
        });
        partResults.value = (data.data || [])
            .filter((item) =>
                String(item?.category || "")
                    .toLowerCase()
                    .includes("sparepart"),
            )
            .slice(0, 6);
    } catch (e) {
        console.error(e);
        partResults.value = [];
    } finally {
        isSearchingPart.value = false;
    }
}

function selectPart(product) {
    selectedPart.value = product;
    tempQty.value = 1;
    partResults.value = [];
    partSearch.value = "";
    showPartDropdown.value = false;
}

function confirmAddPart() {
    if (!selectedPart.value) return;
    form.value.parts.push({
        product_id: selectedPart.value.id,
        nama: selectedPart.value.nama,
        qty: tempQty.value,
        harga_satuan: selectedPart.value.harga_jual,
    });
    selectedPart.value = null;
    toast.success("Sparepart ditambahkan");
}

function removePart(index) {
    form.value.parts.splice(index, 1);
}

function handlePartFocus() {
    searchParts(true);
}

function handleDocumentClick(event) {
    if (!partSearchWrapper.value) return;
    if (!partSearchWrapper.value.contains(event.target)) {
        showPartDropdown.value = false;
    }
}

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});

const totalParts = computed(() => {
    return form.value.parts.reduce((sum, p) => sum + p.qty * p.harga_satuan, 0);
});

function formatInputCurrency(val) {
    if (!val && val !== 0) return "";
    let str = val.toString().replace(/\D/g, "");
    if (str === "") return "";
    return new Intl.NumberFormat("id-ID").format(parseInt(str));
}

function parseInputCurrency(val) {
    if (!val) return 0;
    return parseInt(val.toString().replace(/\D/g, "")) || 0;
}

watch(
    () => displayBiayaJasa.value,
    (newVal) => {
        const rawValue = parseInputCurrency(newVal);
        if (form.value.biaya_jasa !== rawValue) {
            form.value.biaya_jasa = rawValue;
        }
        const formatted = formatInputCurrency(rawValue);
        if (displayBiayaJasa.value !== formatted) {
            displayBiayaJasa.value = formatted;
        }
    },
);

watch(partSearch, () => {
    if (!showPartDropdown.value) showPartDropdown.value = true;
    searchParts();
});

async function handleSubmit() {
    isLoading.value = true;
    try {
        await api.post("/services", form.value);
        toast.success("Service berhasil didaftarkan");
        router.push("/dashboard/services");
    } catch (err) {
        toast.error(
            err.response?.data?.message || "Gagal mendaftarkan service",
        );
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <div class="px-4 md:px-8 mx-auto py-6 space-y-6 max-w-[1400px]">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <button
                @click="router.back()"
                class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-200 transition-all shadow-sm"
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
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"
                    />
                </svg>
            </button>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Service Masuk Baru
                </h1>
                <p class="text-slate-500 text-sm">
                    Input detail barang dan keluhan pelanggan
                </p>
            </div>
        </div>

        <form
            @submit.prevent="handleSubmit"
            class="grid grid-cols-1 lg:grid-cols-3 gap-6"
        >
            <!-- Left Side: Basic Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Data Pelanggan Card -->
                <div
                    class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-slate-100 bg-slate-50"
                    >
                        <h3
                            class="font-bold text-slate-800 text-sm uppercase tracking-wider flex items-center gap-2"
                        >
                            <svg
                                class="w-4 h-4 text-blue-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                            Informasi Pelanggan
                        </h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                >Nama Pelanggan *</label
                            >
                            <input
                                v-model="form.nama_pelanggan"
                                type="text"
                                required
                                placeholder="Contoh: Budi Sudarsono"
                                class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium transition-all"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                >No. HP / WhatsApp</label
                            >
                            <input
                                v-model="form.no_hp_pelanggan"
                                type="text"
                                placeholder="0812xxxx"
                                class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium transition-all"
                            />
                        </div>
                    </div>
                </div>

                <!-- Data Unit Card -->
                <div
                    class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-slate-100 bg-slate-50"
                    >
                        <h3
                            class="font-bold text-slate-800 text-sm uppercase tracking-wider flex items-center gap-2"
                        >
                            <svg
                                class="w-4 h-4 text-indigo-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                                />
                            </svg>
                            Detail Unit & Keluhan
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                    >Merk HP *</label
                                >
                                <input
                                    v-model="form.merk_hp"
                                    type="text"
                                    required
                                    placeholder="Contoh: Samsung, iPhone"
                                    class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium transition-all"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                    >Tipe HP *</label
                                >
                                <input
                                    v-model="form.tipe_hp"
                                    type="text"
                                    required
                                    placeholder="Contoh: Note 20 Ultra"
                                    class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium transition-all"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                    >IMEI / SN</label
                                >
                                <input
                                    v-model="form.imei_hp"
                                    type="text"
                                    placeholder="Masukkan IMEI"
                                    class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium font-mono transition-all"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                    >Kelengkapan</label
                                >
                                <input
                                    v-model="form.kelengkapan"
                                    type="text"
                                    placeholder="Contoh: Dus, Charger"
                                    class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium transition-all"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                >Kerusakan / Keluhan *</label
                            >
                            <textarea
                                v-model="form.kerusakan"
                                rows="3"
                                required
                                placeholder="Jelaskan secara detail masalahnya..."
                                class="block w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium transition-all italic"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Action Panel -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Biaya Card -->
                <div
                    class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between"
                    >
                        <h3
                            class="font-bold text-slate-800 text-sm uppercase tracking-wider flex items-center gap-2"
                        >
                            Spareparts & Biaya
                        </h3>
                        <span
                            class="text-xs font-bold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-md"
                            >{{ form.parts.length }} Part</span
                        >
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- Sparepart Search -->
                        <div ref="partSearchWrapper" class="relative">
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                >Tambah Sparepart (Opsional)</label
                            >
                            <div class="relative group">
                                <input
                                    v-model="partSearch"
                                    @focus="handlePartFocus"
                                    type="text"
                                    placeholder="Cari sprepart disini..."
                                    class="w-full rounded-xl border border-slate-300 focus:border-blue-500 py-3 pl-10 pr-4 text-sm font-medium transition-all"
                                />
                                <svg
                                    class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors"
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

                            <!-- Search Results Popup -->
                            <div
                                v-if="showPartDropdown"
                                class="absolute left-0 top-full z-[60] mt-2 w-full bg-white rounded-xl shadow-xl border border-slate-100 max-h-60 overflow-y-auto"
                            >
                                <div
                                    v-if="isSearchingPart"
                                    class="p-3 text-xs font-semibold text-slate-500"
                                >
                                    Mencari sparepart...
                                </div>
                                <div
                                    v-for="res in partResults"
                                    :key="res.id"
                                    @click="selectPart(res)"
                                    class="p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0"
                                >
                                    <div
                                        class="font-bold text-sm text-slate-800"
                                    >
                                        {{ res.nama }}
                                    </div>
                                    <div
                                        class="text-xs text-blue-600 mt-1 flex justify-between"
                                    >
                                        <span>{{
                                            formatCurrency(res.harga_jual)
                                        }}</span>
                                        <span
                                            :class="
                                                res.stok > 0
                                                    ? 'text-emerald-500'
                                                    : 'text-rose-500'
                                            "
                                            >Stok: {{ res.stok }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    v-if="!isSearchingPart && partResults.length === 0"
                                    class="p-3 text-xs text-slate-500"
                                >
                                    Sparepart tidak ditemukan
                                </div>
                            </div>

                            <!-- Selected Part Config -->
                            <div
                                v-if="selectedPart"
                                class="mt-3 p-4 bg-blue-50 border border-blue-100 rounded-xl"
                            >
                                <p
                                    class="text-sm font-bold text-slate-800 mb-2"
                                >
                                    {{ selectedPart.nama }}
                                </p>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model.number="tempQty"
                                        type="number"
                                        min="1"
                                        class="w-20 px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white"
                                        placeholder="Qty"
                                    />
                                    <button
                                        type="button"
                                        @click="confirmAddPart"
                                        class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition"
                                    >
                                        Tambahkan
                                    </button>
                                </div>
                            </div>

                            <!-- Selected List -->
                            <div
                                v-if="form.parts.length > 0"
                                class="mt-4 space-y-2"
                            >
                                <div
                                    v-for="(p, idx) in form.parts"
                                    :key="idx"
                                    class="flex justify-between items-center p-3 border border-slate-200 rounded-xl bg-slate-50/50"
                                >
                                    <div class="text-xs">
                                        <p class="font-bold text-slate-800">
                                            {{ p.nama }}
                                        </p>
                                        <p class="text-slate-500 mt-1">
                                            {{ p.qty }}x
                                            {{ formatCurrency(p.harga_satuan) }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removePart(idx)"
                                        class="text-rose-500 hover:text-rose-700 p-1"
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
                                                d="M6 18L18 6M6 6l12 12"
                                            ></path>
                                        </svg>
                                    </button>
                                </div>
                                <div
                                    class="flex justify-between items-center pt-2 text-sm font-bold text-slate-700"
                                >
                                    <span>Subtotal Part</span>
                                    <span>{{
                                        formatCurrency(totalParts)
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                >Estimasi Biaya Jasa</label
                            >
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold"
                                    >Rp</span
                                >
                                <input
                                    v-model="displayBiayaJasa"
                                    type="text"
                                    class="block w-full pl-12 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xl font-black text-slate-800 transition-all"
                                />
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2"
                                >Tanggal Masuk</label
                            >
                            <input
                                v-model="form.tanggal_masuk"
                                type="date"
                                readonly
                                class="block w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed font-medium text-sm"
                            />
                        </div>

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="isLoading"
                                class="w-full py-4 rounded-2xl bg-blue-600 text-white font-black uppercase tracking-wider text-xs shadow-xl shadow-blue-500/20 hover:bg-blue-700 disabled:opacity-50 transition-all flex items-center justify-center gap-2"
                            >
                                <svg
                                    v-if="isLoading"
                                    class="animate-spin h-4 w-4 text-white"
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
                                {{
                                    isLoading
                                        ? "Sedang Menyimpan..."
                                        : "Simpan Service"
                                }}
                            </button>
                            <button
                                type="button"
                                @click="router.back()"
                                class="w-full mt-3 py-3 rounded-2xl border-2 border-slate-100 text-slate-400 font-black uppercase tracking-wider text-[10px] hover:bg-slate-50 transition-all"
                            >
                                Batalkan
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="p-4 bg-amber-50 rounded-2xl border border-amber-100 shadow-sm"
                >
                    <div class="flex gap-3">
                        <svg
                            class="w-5 h-5 text-amber-500 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                        <p
                            class="text-[11px] text-amber-800 leading-relaxed font-semibold"
                        >
                            Pastikan data pelanggan dan unit sudah benar. Tanda
                            terima akan dapat dicetak setelah data disimpan.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
