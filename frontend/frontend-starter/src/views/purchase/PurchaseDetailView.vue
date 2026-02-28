<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../api";
import { storageUrl } from "../../utils/storage";
import ImageModal from "../../components/ImageModal.vue";

const route = useRoute();
const router = useRouter();
const purchase = ref(null);
const loading = ref(true);

// Image modal
const modalSrc = ref(null);
const showModal = ref(false);
function openModal(url) {
    if (!url) return;
    modalSrc.value = url;
    showModal.value = true;
}

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(val || 0);
}

async function loadPurchase() {
    loading.value = true;
    try {
        const { data } = await api.get(`/purchases/${route.params.id}`);
        purchase.value = data.data;
    } catch (err) {
        alert("Gagal memuat data");
        router.push("/dashboard/purchases");
    } finally {
        loading.value = false;
    }
}

async function printBarcode(purchaseId, itemId) {
    router.push({
        name: "purchase-barcode",
        params: { id: purchaseId },
        query: { item_id: itemId },
    });
}

onMounted(loadPurchase);
</script>

<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center gap-3 mb-6">
            <button
                @click="router.push('/dashboard/purchases')"
                class="p-2.5 hover:bg-slate-100 rounded-xl transition"
            >
                <svg
                    class="w-5 h-5 text-slate-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 19l-7-7 7-7"
                    />
                </svg>
            </button>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Detail Stok Barang
                </h1>
                <p class="text-sm text-slate-400 mt-0.5" v-if="purchase">
                    {{ purchase.no_invoice }}
                </p>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"
            ></div>
        </div>

        <div v-else-if="purchase">
            <!-- Invoice Info Card -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-5"
            >
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">
                            No. Invoice
                        </p>
                        <p class="font-semibold text-slate-800 font-mono">
                            {{ purchase.no_invoice }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">
                            Tanggal
                        </p>
                        <p class="font-semibold text-slate-800">
                            {{ purchase.tanggal }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">
                            Supplier
                        </p>
                        <p class="font-semibold text-slate-800">
                            {{ purchase.supplier?.nama || "-" }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">
                            Total Pembelian
                        </p>
                        <p class="font-bold text-emerald-600">
                            {{ formatCurrency(purchase.total) }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="purchase.keterangan"
                    class="mt-4 pt-4 border-t border-slate-100"
                >
                    <p class="text-xs font-medium text-slate-400 mb-1">
                        Keterangan
                    </p>
                    <p class="text-sm text-slate-600">
                        {{ purchase.keterangan }}
                    </p>
                </div>
            </div>

            <!-- Items List -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5"
            >
                <!-- Header -->
                <div
                    class="px-5 py-3.5 bg-gradient-to-r from-slate-50 to-white flex justify-between items-center border-b border-slate-100"
                >
                    <h3 class="font-semibold text-slate-800 text-sm">
                        Daftar Barang
                        <span
                            class="ml-1.5 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium"
                            >{{ purchase.items?.length || 0 }}</span
                        >
                    </h3>
                    <div class="flex gap-2">
                        <router-link
                            :to="`/dashboard/purchases/${purchase.id}/edit`"
                            class="px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-lg border border-emerald-200 hover:bg-emerald-100 transition flex items-center gap-1.5"
                        >
                            <svg
                                class="w-3.5 h-3.5"
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
                            Tambah Item
                        </router-link>
                        <router-link
                            :to="`/dashboard/purchases/${purchase.id}/barcode`"
                            class="px-3 py-1.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white text-xs font-semibold rounded-lg hover:shadow-md hover:shadow-purple-500/25 transition-all flex items-center gap-1.5"
                        >
                            <svg
                                class="w-3.5 h-3.5"
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
                            Cetak Barcode
                        </router-link>
                    </div>
                </div>

                <!-- Item Rows -->
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="(item, idx) in purchase.items"
                        :key="item.id"
                        class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/60 transition"
                    >
                        <!-- Row Number -->
                        <span
                            class="text-xs text-slate-300 font-bold w-5 text-center shrink-0"
                            >{{ idx + 1 }}</span
                        >

                        <!-- Photo (clickable) -->
                        <div
                            class="w-11 h-11 rounded-xl bg-slate-100 shrink-0 overflow-hidden border border-slate-200 flex items-center justify-center"
                            :class="
                                item.product?.foto
                                    ? 'cursor-pointer hover:opacity-75 hover:border-blue-300 transition'
                                    : ''
                            "
                            @click="openModal(storageUrl(item.product?.foto))"
                            :title="
                                item.product?.foto
                                    ? 'Klik untuk lihat foto'
                                    : ''
                            "
                        >
                            <img
                                v-if="storageUrl(item.product?.foto)"
                                :src="storageUrl(item.product?.foto)"
                                class="w-full h-full object-cover"
                                @error="$event.target.style.display = 'none'"
                            />
                            <svg
                                v-if="!item.product?.foto"
                                class="w-5 h-5 text-slate-300"
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

                        <!-- Main Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span
                                    class="font-mono text-[11px] text-blue-600 font-semibold"
                                    >{{ item.product?.barcode }}</span
                                >
                                <span
                                    v-if="item.product?.unit"
                                    class="text-[10px] px-1.5 py-0.5 bg-amber-50 text-amber-600 rounded border border-amber-200 font-medium"
                                    >{{ item.product.unit }}</span
                                >
                                <span
                                    v-if="item.product?.grade"
                                    class="text-[10px] px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded border border-purple-200 font-medium"
                                    >{{ item.product.grade }}</span
                                >
                            </div>
                            <p
                                class="text-sm font-medium text-slate-700 truncate mt-0.5"
                            >
                                {{ item.product?.nama }}
                            </p>
                            <div
                                class="flex items-center gap-3 mt-0.5 text-[11px] text-slate-400"
                            >
                                <span v-if="item.product?.brand">{{
                                    item.product.brand
                                }}</span>
                                <span
                                    v-if="item.product?.category"
                                    class="text-slate-300"
                                    >·</span
                                >
                                <span v-if="item.product?.category">{{
                                    item.product.category
                                }}</span>
                            </div>
                        </div>

                        <!-- Fixed-width columns for alignment -->
                        <div class="flex items-center gap-3 shrink-0">
                            <!-- IMEI 1 -->
                            <div class="text-right w-32">
                                <p class="text-[10px] text-slate-400 mb-0.5">
                                    IMEI 1
                                </p>
                                <p
                                    class="text-xs font-mono font-medium text-slate-600 whitespace-nowrap"
                                >
                                    {{ item.product?.imei1 || "-" }}
                                </p>
                            </div>
                            <!-- IMEI 2 -->
                            <div class="text-right w-32">
                                <p class="text-[10px] text-slate-400 mb-0.5">
                                    IMEI 2
                                </p>
                                <p
                                    class="text-xs font-mono font-medium text-slate-600 whitespace-nowrap"
                                >
                                    {{ item.product?.imei2 || "-" }}
                                </p>
                            </div>
                            <div class="w-px h-8 bg-slate-100"></div>
                            <!-- Qty -->
                            <div class="text-center w-12">
                                <p class="text-[10px] text-slate-400 mb-0.5">
                                    Qty
                                </p>
                                <p class="text-sm font-bold text-slate-700">
                                    {{ item.qty }}
                                </p>
                            </div>
                            <div class="w-px h-8 bg-slate-100"></div>
                            <!-- Harga Beli -->
                            <div class="text-right w-28">
                                <p class="text-[10px] text-slate-400 mb-0.5">
                                    Harga Beli
                                </p>
                                <p
                                    class="text-xs font-medium text-slate-600 whitespace-nowrap"
                                >
                                    {{ formatCurrency(item.harga_beli) }}
                                </p>
                            </div>
                            <div class="w-px h-8 bg-slate-100"></div>
                            <!-- Action -->
                            <div class="w-10 flex justify-center">
                                <button
                                    @click="printBarcode(purchase.id, item.id)"
                                    class="p-1.5 text-purple-500 hover:bg-purple-100 rounded-lg transition"
                                    title="Print Barcode"
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Footer -->
                <div
                    class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-between items-center"
                >
                    <span
                        class="text-xs font-semibold text-slate-400 uppercase tracking-wider"
                        >Total Pembelian</span
                    >
                    <span class="text-lg font-bold text-emerald-600">{{
                        formatCurrency(purchase.total)
                    }}</span>
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <ImageModal
            :show="showModal"
            :src="modalSrc"
            @close="showModal = false"
        />
    </div>
</template>
