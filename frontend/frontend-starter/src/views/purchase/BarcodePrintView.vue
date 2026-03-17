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
        let loadedPurchase = data.data;

        // Filter by item_id or product_id if provided in query
        const itemId = route.query.item_id;
        const productId = route.query.product_id;

        if (itemId && loadedPurchase.items) {
            loadedPurchase.items = loadedPurchase.items.filter(
                (item) => String(item.id) === String(itemId),
            );
        } else if (productId && loadedPurchase.items) {
            loadedPurchase.items = loadedPurchase.items.filter(
                (item) => String(item.product_id) === String(productId),
            );
        }

        purchase.value = loadedPurchase;
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
                    width: 1.55,
                    height: 42,
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

const PRINT_PRESETS = {
    "50x20": {
        pageWidthMm: 50,
        pageHeightMm: 20,
        labelPadding: "1.2mm 1.4mm 1mm",
        barcodeWidthMm: 40,
        barcodeHeightMm: 9,
        barcodeLineWidth: 1.55,
        barcodePixelHeight: 42,
        nameMaxWidthMm: 40,
    },
    "40x20": {
        pageWidthMm: 40,
        pageHeightMm: 20,
        labelPadding: "1.1mm 1.2mm 0.9mm",
        barcodeWidthMm: 32,
        barcodeHeightMm: 8.5,
        barcodeLineWidth: 1.25,
        barcodePixelHeight: 36,
        nameMaxWidthMm: 32,
    },
};

function printPage(paperSize = "50x20") {
    if (!purchase.value?.items?.length) return;
    const preset = PRINT_PRESETS[paperSize] || PRINT_PRESETS["50x20"];

    const popup = window.open("", "_blank", "width=420,height=320");
    if (!popup) {
        alert("Popup diblokir browser. Izinkan popup lalu coba print lagi.");
        return;
    }

    const labelsHtml = purchase.value.items
        .map((item, idx) => {
            let barcodeImage = "";
            if (item.product?.barcode) {
                const tempCanvas = document.createElement("canvas");
                JsBarcode(tempCanvas, item.product.barcode, {
                    format: "CODE128",
                    width: preset.barcodeLineWidth,
                    height: preset.barcodePixelHeight,
                    displayValue: false,
                    margin: 0,
                    background: "#ffffff",
                    lineColor: "#000000",
                });
                barcodeImage = tempCanvas.toDataURL("image/png");
            }

            return `
                <div class="label-card">
                    <img class="barcode-img" src="${barcodeImage}" alt="barcode" />
                    <div class="label-code">${item.product?.barcode || ""}</div>
                    <div class="label-name">${item.product?.nama || ""}</div>
                    ${
                        item.product?.imei1
                            ? `<div class="label-imei">${item.product.imei1}</div>`
                            : ""
                    }
                </div>
            `;
        })
        .join("");

    popup.document.write(`
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8" />
            <title>Print Barcode</title>
            <style>
                @page {
                    size: ${preset.pageWidthMm}mm ${preset.pageHeightMm}mm;
                    margin: 0;
                }
                html, body {
                    width: ${preset.pageWidthMm}mm;
                    margin: 0;
                    padding: 0;
                    background: #fff;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .label-card {
                    width: ${preset.pageWidthMm}mm;
                    height: ${preset.pageHeightMm}mm;
                    box-sizing: border-box;
                    padding: ${preset.labelPadding};
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    overflow: hidden;
                    page-break-after: always;
                }
                .label-card:last-child {
                    page-break-after: auto;
                }
                .barcode-img {
                    width: ${preset.barcodeWidthMm}mm;
                    max-width: ${preset.barcodeWidthMm}mm;
                    height: ${preset.barcodeHeightMm}mm;
                    object-fit: contain;
                    display: block;
                }
                .label-code {
                    font-family: "Courier New", Courier, monospace;
                    font-size: 7pt;
                    font-weight: 700;
                    letter-spacing: 0.4px;
                    margin-top: 0.6mm;
                    line-height: 1;
                }
                .label-name {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 6pt;
                    font-weight: 700;
                    text-transform: uppercase;
                    margin-top: 0.4mm;
                    max-width: ${preset.nameMaxWidthMm}mm;
                    line-height: 1;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    color: #222;
                }
                .label-imei {
                    font-family: "Courier New", Courier, monospace;
                    font-size: 5.6pt;
                    margin-top: 0.3mm;
                    line-height: 1;
                    color: #444;
                }
            </style>
        </head>
        <body>${labelsHtml}</body>
        </html>
    `);
    popup.document.close();
    popup.onload = () => {
        setTimeout(() => {
            popup.focus();
            popup.print();
            popup.close();
        }, 300);
    };
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
            <div v-if="barcodesReady" class="ml-auto flex items-center gap-2">
                <button
                    @click="printPage('50x20')"
                    class="px-4 py-2.5 bg-slate-700 text-white text-xs font-semibold rounded-xl hover:bg-slate-800 transition-all"
                >
                    Print 50x20 mm
                </button>
                <button
                    @click="printPage('40x20')"
                    class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white text-xs font-semibold rounded-xl hover:shadow-lg hover:shadow-purple-500/25 transition-all"
                >
                    Print 40x20 mm
                </button>
            </div>
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
/* === SCREEN PREVIEW (A6 preview: 2 columns) === */
.label-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    padding: 10px;
    max-width: 420px;
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
    max-height: 55px;
}

.label-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-top: 4px;
    color: #111;
}

.label-name {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    margin-top: 3px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.label-imei {
    font-family: "Courier New", Courier, monospace;
    font-size: 8px;
    color: #555;
    margin-top: 2px;
}

/* === PRINT STYLES (Thermal Label 50x20mm) === */
@media print {
    @page {
        size: 50mm 20mm;
        margin: 0;
    }

    html,
    body {
        width: 50mm !important;
        height: 20mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
        print-color-adjust: exact;
    }

    body * {
        visibility: hidden !important;
    }

    #print-area,
    #print-area * {
        visibility: visible !important;
    }

    #print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 50mm !important;
        height: 20mm !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .label-grid {
        display: block;
        padding: 0;
        margin: 0;
        width: 50mm;
    }

    .label-card {
        width: 50mm;
        height: 20mm;
        border: none;
        border-radius: 0;
        box-sizing: border-box;
        padding: 1.2mm 1.4mm 1mm;
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-after: always;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .label-card:last-child {
        page-break-after: auto;
    }

    .barcode-canvas {
        width: 40mm;
        max-width: 40mm;
        height: 9mm;
        max-height: 9mm;
    }

    .label-code {
        font-size: 7pt;
        letter-spacing: 0.5px;
        margin-top: 0.6mm;
    }

    .label-name {
        font-size: 6pt;
        margin-top: 0.4mm;
        max-width: 40mm;
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
        line-height: 1;
    }

    .label-imei {
        font-size: 5.6pt;
        margin-top: 0.3mm;
        line-height: 1;
    }
}
</style>


