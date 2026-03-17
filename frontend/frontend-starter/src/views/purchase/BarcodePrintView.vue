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

async function loadPurchase() {
    loading.value = true;
    try {
        const { data } = await api.get(`/purchases/${route.params.id}`);
        let loadedPurchase = data.data;

        const itemId = route.query.item_id;
        const productId = route.query.product_id;

        if (itemId && loadedPurchase.items) {
            loadedPurchase.items = loadedPurchase.items.filter(
                (item) => String(item.id) === String(itemId)
            );
        } else if (productId && loadedPurchase.items) {
            loadedPurchase.items = loadedPurchase.items.filter(
                (item) => String(item.product_id) === String(productId)
            );
        }

        purchase.value = loadedPurchase;
        await nextTick();
        setTimeout(async () => {
            await renderAllQR();
            barcodesReady.value = true;
        }, 200);
    } catch (err) {
        alert("Gagal memuat data");
        router.push("/dashboard/purchases");
    } finally {
        loading.value = false;
    }
}

/**
 * Pitch fisik stiker roll = 20mm (label) + 3mm (gap) = 23mm.
 * @page HARUS = 23mm agar tidak ada drift antar label.
 *
 * Dalam 23mm halaman:
 *   GAP_TOP_MM = 1.5mm → margin atas dalam area label (blank)
 *   Konten      = 18.5mm (1.5mm s/d 20mm dalam label)
 *   GAP_BOT_MM = 3mm   → jeda antar stiker (blank, di luar label)
 */
const GAP_TOP_MM = 1.5;
const GAP_BOT_MM = 3;
const PRINT_PRESETS = {
    "50x20": {
        pageWidthMm: 50,
        pageHeightMm: 20,
        totalHeightMm: 20 + GAP_BOT_MM,   // 23mm = pitch fisik
        qrSizeMm: 13,
        qrPixels: 100,
        nameFontPt: 6.5,
        codeFontPt: 6.5,
        imeiFontPt: 5.5,
    },
    "40x20": {
        pageWidthMm: 40,
        pageHeightMm: 20,
        totalHeightMm: 20 + GAP_BOT_MM,   // 23mm = pitch fisik
        qrSizeMm: 12,
        qrPixels: 90,
        nameFontPt: 6,
        codeFontPt: 6,
        imeiFontPt: 5,
    },
};

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

function isMobilePrintContext() {
    const ua = navigator.userAgent || "";
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        ua
    );
}

async function buildPopupLabelsHtml(preset) {
    if (!purchase.value?.items?.length) return "";

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
            <div class="label-card">
                <div class="label-content">
                    <div class="label-left">
                        ${
                            qrDataUrl
                                ? `<img class="qr-img" src="${qrDataUrl}" alt="qr" />`
                                : ""
                        }
                    </div>
                    <div class="label-right">
                        <div class="label-code">${code}</div>
                        <div class="label-name">${name}</div>
                        ${imei ? `<div class="label-imei">${imei}</div>` : ""}
                    </div>
                </div>
            </div>
        `;
    });

    return (await Promise.all(labelPromises)).join("");
}

async function printViaPopup(preset) {
    const popup = window.open("", "_blank", "width=500,height=400");
    if (!popup) return false;

    const labelsHtml = await buildPopupLabelsHtml(preset);

    popup.document.write(`
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8" />
            <title>Print Barcode</title>
            <style>
                /*
                 * @page = totalHeightMm (label 20mm + gap 3mm = 23mm).
                 * Konten label mengisi 20mm, sisa 3mm jatuh di jeda fisik stiker.
                 */
                @page {
                    size: ${preset.pageWidthMm}mm ${preset.totalHeightMm}mm;
                    margin: 0;
                }
                * { box-sizing: border-box; }
                html, body {
                    width: ${preset.pageWidthMm}mm;
                    margin: 0;
                    padding: 0;
                    background: #fff;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                #print-root {
                    width: ${preset.pageWidthMm}mm;
                    margin: 0;
                    padding: 0;
                }
                /*
                 * @page = 23mm = pitch fisik (20mm label + 3mm gap).
                 * Padding top  : 1.5mm → margin atas dalam label (blank)
                 * Padding bot  : 3mm   → jatuh di gap fisik antar stiker (blank)
                 * Konten aktif : 23 - 1.5 - 3 = 18.5mm, di dalam area putih label
                 */
                .label-card {
                    width: ${preset.pageWidthMm}mm;
                    height: ${preset.totalHeightMm}mm;
                    box-sizing: border-box;
                    padding: ${GAP_TOP_MM}mm 2.5mm ${GAP_BOT_MM}mm 2.5mm;
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    overflow: hidden;
                    page-break-after: always;
                    break-after: page;
                    break-inside: avoid;
                }
                .label-card:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
                /* Konten 20mm persis — QR + teks center vertikal */
                .label-content {
                    display: flex;
                    flex-direction: row;
                    align-items: center;
                    justify-content: flex-start;
                    gap: 1mm;
                    width: 100%;
                    flex: 1;
                    overflow: hidden;
                }
                .label-left {
                    flex-shrink: 0;
                    width: ${preset.qrSizeMm}mm;
                    height: ${preset.qrSizeMm}mm;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .qr-img {
                    width: ${preset.qrSizeMm}mm;
                    height: ${preset.qrSizeMm}mm;
                    display: block;
                }
                .label-right {
                    flex: 1;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 0.6mm;
                    overflow: hidden;
                }
                .label-code {
                    font-family: "Courier New", Courier, monospace;
                    font-size: ${preset.codeFontPt}pt;
                    font-weight: 700;
                    line-height: 1.1;
                    color: #000;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .label-name {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: ${preset.nameFontPt}pt;
                    font-weight: 700;
                    line-height: 1.2;
                    color: #000;
                    word-break: break-word;
                    overflow: hidden;
                }
                .label-imei {
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
                setTimeout(function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 300);
                }, 600);
            });
        <\/script>
        </head>
        <body>
            <div id="print-root">${labelsHtml}</div>
        </body>
        </html>
    `);
    popup.document.close();

    return true;
}

async function doPrint() {
    if (!purchase.value?.items?.length) return;
    const preset = PRINT_PRESETS[selectedSize.value] || PRINT_PRESETS["50x20"];

    if (!isMobilePrintContext()) {
        await printViaPopup(preset);
        return;
    }
    window.print();
}

onMounted(loadPurchase);
</script>

<template>
    <div>
        <!-- Screen Header -->
        <div class="flex items-center gap-3 mb-6 no-print">
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
                <!-- Radio toggle ukuran kertas -->
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
                        >
                            {{ size }} mm
                        </span>
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

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div
                class="w-8 h-8 border-b-2 border-blue-600 rounded-full animate-spin"
            ></div>
        </div>

        <!-- Preview -->
        <div v-else-if="purchase" id="print-area">
            <p class="mb-3 text-xs text-slate-400 no-print">
                Preview ({{ selectedSize }} mm) — klik Cetak untuk mencetak
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

<style>
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

@media print {
    body * {
        visibility: hidden !important;
    }
}
</style>
