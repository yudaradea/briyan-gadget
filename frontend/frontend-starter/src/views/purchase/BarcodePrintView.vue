<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../api";
import JsBarcode from "jsbarcode";

const route = useRoute();
const router = useRouter();
const purchase = ref(null);
const loading = ref(true);
const barcodesReady = ref(false);

async function loadPurchase() {
    loading.value = true;
    try {
        const { data } = await api.get(`/purchases/${route.params.id}`);
        purchase.value = data.data;
        await nextTick();
        setTimeout(() => {
            renderAllBarcodes();
            barcodesReady.value = true;
        }, 200);
    } catch (err) {
        alert("Gagal memuat data");
        router.push("/dashboard/purchases");
    } finally {
        loading.value = false;
    }
}

function renderAllBarcodes() {
    if (!purchase.value?.items) return;
    purchase.value.items.forEach((item, idx) => {
        try {
            const el = document.getElementById(`bc-${idx}`);
            if (el && item.product?.barcode) {
                JsBarcode(el, item.product.barcode, {
                    format: "CODE128",
                    width: 1.8,
                    height: 50,
                    displayValue: false,
                    margin: 0,
                    background: "#ffffff",
                    lineColor: "#000000",
                });
            }
        } catch (e) {
            console.error("Barcode error:", e);
        }
    });
}

function printPage() {
    window.print();
}

onMounted(loadPurchase);
</script>

<template>
    <div>
        <!-- Screen Header -->
        <div class="no-print flex items-center gap-3 mb-6">
            <button
                @click="router.back()"
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
                <h1 class="text-2xl font-bold text-slate-800">Cetak Barcode</h1>
                <p class="text-sm text-slate-400 mt-0.5" v-if="purchase">
                    {{ purchase.no_invoice }} —
                    {{ purchase.items?.length || 0 }} label
                </p>
            </div>
            <button
                v-if="barcodesReady"
                @click="printPage"
                class="ml-auto px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white text-sm font-semibold rounded-xl hover:shadow-lg hover:shadow-purple-500/25 transition-all flex items-center gap-2"
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
                Print
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"
            ></div>
        </div>

        <!-- Barcode Labels Grid (A4 preview) -->
        <div v-else-if="purchase" id="print-area">
            <div class="label-grid">
                <div
                    v-for="(item, idx) in purchase.items"
                    :key="item.id"
                    class="label-card"
                >
                    <canvas :id="`bc-${idx}`" class="barcode-canvas"></canvas>
                    <div class="label-code">{{ item.product?.barcode }}</div>
                    <div class="label-name">{{ item.product?.nama }}</div>
                    <div v-if="item.product?.imei1" class="label-imei">
                        {{ item.product.imei1 }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* === SCREEN PREVIEW === */
.label-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    padding: 8px;
    max-width: 800px;
}

.label-card {
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 10px 8px 8px;
    text-align: center;
    background: white;
}

.barcode-canvas {
    display: block;
    margin: 0 auto;
    width: 100%;
    max-height: 50px;
}

.label-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-top: 3px;
    color: #111;
}

.label-name {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8px;
    font-weight: 700;
    text-transform: uppercase;
    margin-top: 2px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.label-imei {
    font-family: "Courier New", Courier, monospace;
    font-size: 7px;
    color: #555;
    margin-top: 1px;
}

/* === PRINT STYLES (A4) === */
@media print {
    /* Hide sidebar, topbar, screen-only elements */
    .no-print,
    .no-print * {
        display: none !important;
    }

    body,
    html {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Hide the dashboard sidebar and topbar */
    aside,
    header,
    nav,
    [class*="bg-gradient-to-b"][class*="from-slate-900"],
    [class*="bg-white/80"][class*="backdrop-blur"] {
        display: none !important;
    }

    /* Remove any layout constraints */
    .min-h-screen,
    .flex-1 {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    main {
        padding: 0 !important;
        margin: 0 !important;
    }

    @page {
        size: A4;
        margin: 8mm 10mm;
    }

    /* Grid: 4 columns x multiple rows on A4 */
    .label-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 3mm;
        padding: 0;
        max-width: none;
    }

    .label-card {
        border: 0.3pt solid #ccc;
        border-radius: 0;
        padding: 2mm 1.5mm;
        page-break-inside: avoid;
        text-align: center;
        min-height: 22mm;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .barcode-canvas {
        max-width: 40mm;
        height: 12mm;
    }

    .label-code {
        font-size: 7pt;
        margin-top: 0.5mm;
    }

    .label-name {
        font-size: 5.5pt;
        margin-top: 0.3mm;
        max-width: 40mm;
    }

    .label-imei {
        font-size: 5pt;
        margin-top: 0.2mm;
    }
}
</style>
