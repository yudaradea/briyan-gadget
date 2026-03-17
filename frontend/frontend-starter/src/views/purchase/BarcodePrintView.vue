<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../api";
import QRCode from "qrcode";

const route = useRoute();
const router = useRouter();
const purchase = ref(null);
const loading = ref(true);
const barcodesReady = ref(false);
const selectedSize = ref("50x20");

const PRINT_PRESETS = {
    "50x20": {
        pageWidthMm: 50,
        pageHeightMm: 20,
        qrSizeMm: 13,
        qrPixels: 100,
        nameFontPt: 6.5,
        codeFontPt: 6.5,
        imeiFontPt: 5.5,
    },
    "40x20": {
        pageWidthMm: 40,
        pageHeightMm: 20,
        qrSizeMm: 12,
        qrPixels: 90,
        nameFontPt: 6,
        codeFontPt: 6,
        imeiFontPt: 5,
    },
};

async function loadPurchase() {
    loading.value = true;
    try {
        const { data } = await api.get(`/purchases/${route.params.id}`);
        let loaded = data.data;

        const itemId = route.query.item_id;
        const productId = route.query.product_id;
        if (itemId && loaded.items)
            loaded.items = loaded.items.filter(
                (i) => String(i.id) === String(itemId)
            );
        else if (productId && loaded.items)
            loaded.items = loaded.items.filter(
                (i) => String(i.product_id) === String(productId)
            );

        purchase.value = loaded;
        await nextTick();
        setTimeout(async () => {
            await renderAllQR();
            barcodesReady.value = true;
        }, 200);
    } catch {
        alert("Gagal memuat data");
        router.push("/dashboard/purchases");
    } finally {
        loading.value = false;
    }
}

async function renderAllQR() {
    if (!purchase.value?.items) return;
    for (let idx = 0; idx < purchase.value.items.length; idx++) {
        const item = purchase.value.items[idx];
        const canvas = document.getElementById(`qr-${idx}`);
        if (canvas && item.product?.barcode) {
            try {
                await QRCode.toCanvas(canvas, item.product.barcode, {
                    width: 100,
                    margin: 0,
                    color: { dark: "#000000", light: "#ffffff" },
                });
            } catch (e) {
                console.error("QR error:", e);
            }
        }
    }
}

async function doPrint() {
    if (!purchase.value?.items?.length) return;
    const preset = PRINT_PRESETS[selectedSize.value] || PRINT_PRESETS["50x20"];

    // Buat QR data URL dari canvas yang sudah dirender
    const labelPromises = purchase.value.items.map(async (item) => {
        let qrDataUrl = "";
        if (item.product?.barcode) {
            try {
                qrDataUrl = await QRCode.toDataURL(item.product.barcode, {
                    width: preset.qrPixels,
                    margin: 0,
                    color: { dark: "#000000", light: "#ffffff" },
                });
            } catch (e) {
                console.error("QR DataURL error:", e);
            }
        }
        const name = (item.product?.nama || "").toUpperCase();
        const code = item.product?.barcode || "";
        const imei = item.product?.imei1 || "";
        return `
            <div class="lbl-card">
                <div class="lbl-left">
                    ${
                        qrDataUrl
                            ? `<img class="lbl-qr" src="${qrDataUrl}" alt="qr"/>`
                            : ""
                    }
                </div>
                <div class="lbl-right">
                    <div class="lbl-code">${code}</div>
                    <div class="lbl-name">${name}</div>
                    ${imei ? `<div class="lbl-imei">${imei}</div>` : ""}
                </div>
            </div>`;
    });
    const labelsHtml = (await Promise.all(labelPromises)).join("");

    // Buka popup — popup yang mengelola ukuran halaman sendiri via @page CSS
    const popup = window.open("", "_blank", "width=600,height=500");
    if (!popup) {
        alert("Popup diblokir browser. Izinkan popup untuk halaman ini.");
        return;
    }

    popup.document.write(`<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<title>Barcode</title>
<style>
/* Reset absolut untuk printer thermal */
@page {
    size: ${preset.pageWidthMm}mm ${preset.pageHeightMm}mm;
    margin: 0mm !important; /* Wajib 0 agar tidak bergeser */
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    width: ${preset.pageWidthMm}mm;
    background: #fff;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    margin: 0 !important;
    padding: 0 !important;
}

.lbl-card {
    width: ${preset.pageWidthMm}mm;
    height: ${preset.pageHeightMm}mm;
    /* Sedikit modifikasi padding agar aman dari tepi pemotongan printer */
    padding: 1.5mm 2mm; 
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 1.5mm;
    overflow: hidden;
    /* Aturan page break */
    page-break-after: always;
    break-after: page;
    break-inside: avoid;
}
.lbl-card:last-child { 
    page-break-after: auto; 
    break-after: auto; 
}

.lbl-left {
    flex-shrink: 0;
    width: ${preset.qrSizeMm}mm;
    height: ${preset.qrSizeMm}mm;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lbl-qr { width: ${preset.qrSizeMm}mm; height: ${preset.qrSizeMm}mm; display: block; }

.lbl-right {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.5mm;
    overflow: hidden;
}

.lbl-code {
    font-family: "Courier New", Courier, monospace;
    font-size: ${preset.codeFontPt}pt;
    font-weight: 700;
    line-height: 1.1;
    color: #000;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lbl-name {
    font-family: Arial, Helvetica, sans-serif;
    font-size: ${preset.nameFontPt}pt;
    font-weight: 700;
    line-height: 1.2;
    color: #000;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.lbl-imei {
    font-family: "Courier New", Courier, monospace;
    font-size: ${preset.imeiFontPt}pt;
    font-weight: 700;
    line-height: 1.1;
    color: #000;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
<script>
window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 600);
});
<\/script>
</head>
<body>${labelsHtml}</body>
</html>`);
    popup.document.close();
}

onMounted(loadPurchase);
</script>

<template>
    <div>
        <div class="flex items-center gap-3 mb-6">
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

            <div v-if="barcodesReady" class="flex items-center gap-3 ml-auto">
                <div
                    class="flex items-center gap-1 p-1 bg-slate-100 rounded-xl"
                >
                    <label
                        v-for="size in ['50x20', '40x20']"
                        :key="size"
                        class="cursor-pointer"
                    >
                        <input
                            type="radio"
                            v-model="selectedSize"
                            :value="size"
                            class="sr-only"
                        />
                        <span
                            :class="[
                                'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all select-none',
                                selectedSize === size
                                    ? 'bg-white text-slate-800 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700',
                            ]"
                            >{{ size }} mm</span
                        >
                    </label>
                </div>

                <button
                    @click="doPrint"
                    class="flex items-center gap-2 px-4 py-2.5 bg-slate-700 text-white text-xs font-semibold rounded-xl hover:bg-slate-800 transition-all"
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
                    Cetak
                </button>
            </div>
        </div>

        <div
            v-if="barcodesReady"
            class="max-w-md px-3 py-2 mb-4 text-xs border rounded-lg bg-amber-50 border-amber-200 text-amber-700"
        >
            ⚠️ Di dialog cetak: pilih printer <strong>Okay D100</strong>,
            pastikan <strong>Margin → None (Tidak Ada)</strong>, Skala
            <strong>100% / Default</strong>.
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <div
                class="w-8 h-8 border-b-2 border-blue-600 rounded-full animate-spin"
            ></div>
        </div>

        <div v-else-if="purchase">
            <p class="mb-3 text-xs text-slate-400">
                Preview ({{ selectedSize }} mm)
            </p>
            <div class="label-grid">
                <div
                    v-for="(item, idx) in purchase.items"
                    :key="item.id"
                    class="label-card-preview"
                >
                    <div class="label-left-preview">
                        <canvas
                            :id="`qr-${idx}`"
                            class="qr-canvas-preview"
                        ></canvas>
                    </div>
                    <div class="label-right-preview">
                        <div class="label-code-preview">
                            {{ item.product?.barcode }}
                        </div>
                        <div class="label-name-preview">
                            {{ item.product?.nama }}
                        </div>
                        <div
                            v-if="item.product?.imei1"
                            class="label-imei-preview"
                        >
                            {{ item.product.imei1 }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.label-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    padding: 10px;
    max-width: 480px;
}
.label-card-preview {
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 8px;
    background: white;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
    min-height: 68px;
    overflow: hidden;
}
.label-left-preview {
    flex-shrink: 0;
}
.qr-canvas-preview {
    width: 55px !important;
    height: 55px !important;
    display: block;
}
.label-right-preview {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
}
.label-code-preview {
    font-family: "Courier New", Courier, monospace;
    font-size: 10px;
    font-weight: 700;
    color: #111;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.label-name-preview {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    color: #333;
    line-height: 1.2;
}
.label-imei-preview {
    font-family: "Courier New", Courier, monospace;
    font-size: 8px;
    font-weight: 700;
    color: #222;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
