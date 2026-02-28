<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../api";
import { useToast } from "../../composables/useToast";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const sale = ref(null);
const store = ref(null);
const isLoading = ref(true);

onMounted(async () => {
    try {
        const id = route.params.id;
        const [saleRes, storeRes] = await Promise.all([
            api.get(`/sales/${id}`),
            api.get("/store-settings"),
        ]);
        sale.value = saleRes.data.data;
        store.value = storeRes.data.data;
    } catch (e) {
        toast.error("Gagal memuat detail invoice");
    } finally {
        isLoading.value = false;
        // Auto print after load
        nextTick(() => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    }
});

// Auto close/go back after print
if (typeof window !== "undefined") {
    window.onafterprint = () => {
        router.back();
    };
}

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 0,
    }).format(val || 0);
}

function formatDisplayDate(dateStr) {
    if (!dateStr) return "-";
    const [y, m, d] = String(dateStr).split("-");
    if (!y || !m || !d) return dateStr;
    return `${d}-${m}-${y}`;
}

function getProductIdentifierLines(product) {
    if (!product) return [];

    const identifierType = String(product.identifier_type || "none");
    const imei1 = product.imei1 ? String(product.imei1).trim() : "";
    const imei2 = product.imei2 ? String(product.imei2).trim() : "";
    const serial = product.barcode ? String(product.barcode).trim() : "";

    if (identifierType === "imei1") {
        return imei1 ? [`IMEI: ${imei1}`] : [];
    }

    if (identifierType === "imei2") {
        const lines = [];
        if (imei1) lines.push(`IMEI 1: ${imei1}`);
        if (imei2) lines.push(`IMEI 2: ${imei2}`);
        return lines;
    }

    if (identifierType === "serial") {
        return serial ? [`SN: ${serial}`] : [];
    }

    return [];
}

function printInvoice() {
    window.print();
}
</script>

<template>
    <div
        v-if="isLoading"
        class="flex items-center justify-center h-screen bg-white"
    >
        <p class="font-mono text-xs uppercase text-slate-500">
            MEMUAT INVOICE...
        </p>
    </div>

    <!-- MAIN CONTAINER -->
    <div
        v-else
        class="min-h-screen bg-white print:min-h-0 print:bg-transparent"
    >
        <!-- Print Button (Hidden in Print) -->
        <div class="max-w-[800px] mx-auto pt-6 px-4 print:hidden">
            <div
                class="flex items-center justify-between p-4 mb-6 border bg-slate-50 rounded-xl border-slate-200"
            >
                <button
                    @click="router.back()"
                    class="flex items-center gap-2 text-sm font-medium text-slate-600"
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
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                    KEMBALI
                </button>
                <button
                    @click="printInvoice"
                    class="flex items-center gap-2 px-6 py-2 text-sm font-bold text-white transition bg-blue-600 rounded-lg shadow-lg hover:bg-blue-700"
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
                        ></path>
                    </svg>
                    CETAK SEKARANG
                </button>
            </div>
        </div>

        <!-- ACTUAL INVOICE AREA -->
        <div
            id="invoice-printable"
            class="bg-white w-full max-w-[800px] mx-auto p-8 mb-20 print:mb-0 print:p-0 font-mono text-[11px] text-black"
        >
            <!-- Header Section -->
            <div class="flex items-center justify-between mb-4">
                <h1
                    class="font-serif text-5xl font-black tracking-tighter text-black"
                >
                    INVOICE
                </h1>
                <div class="text-right">
                    <img
                        v-if="store?.logo_url"
                        :src="store.logo_url"
                        class="object-contain ml-auto h-14"
                    />
                    <div
                        v-else
                        class="text-2xl font-black leading-none text-black uppercase"
                    >
                        {{ store?.name }}
                    </div>
                </div>
            </div>

            <!-- Divider Line -->
            <div class="mb-8 border-b-2 border-slate-800"></div>

            <!-- Store & Customer Details -->
            <table class="w-full mb-10 border-collapse table-fixed">
                <tr>
                    <!-- Store Info -->
                    <td class="w-1/2 pr-10 align-top">
                        <div class="leading-relaxed uppercase">
                            <p class="font-black text-[12px] mb-2">
                                {{ store?.name }}
                            </p>
                            <p class="text-slate-600">{{ store?.address }}</p>
                            <p class="mt-4 font-bold">
                                No Telp :
                                <span class="text-black">{{
                                    store?.phone
                                }}</span>
                            </p>
                            <p v-if="store?.email">
                                Email :
                                <span class="text-black">{{
                                    store?.email
                                }}</span>
                            </p>
                        </div>
                    </td>
                    <!-- Invoice Info -->
                    <td class="w-1/2 align-top">
                        <table class="w-full border-collapse">
                            <tr>
                                <td class="w-32 py-1 font-bold uppercase">
                                    No. Invoice
                                </td>
                                <td class="w-4 py-1 text-center">:</td>
                                <td class="py-1 font-bold text-right">
                                    {{ sale.no_invoice }}
                                </td>
                            </tr>
                            <tr v-if="sale.tipe === 'service'">
                                <td class="py-1 font-bold uppercase">
                                    No. Service
                                </td>
                                <td class="py-1 text-center">:</td>
                                <td class="py-1 font-bold text-right uppercase">
                                    {{ sale.service_order?.no_service || "-" }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-1 font-bold uppercase">
                                    Tanggal
                                </td>
                                <td class="py-1 text-center">:</td>
                                <td class="py-1 text-right">
                                    {{ formatDisplayDate(sale.tanggal) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-1 font-bold uppercase">
                                    Pelanggan
                                </td>
                                <td class="py-1 text-center">:</td>
                                <td class="py-1 font-bold text-right uppercase">
                                    {{ sale.pelanggan || "Umum" }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Purchase Title -->
            <div
                class="text-[10px] font-black mb-4 uppercase tracking-widest border-l-4 border-black pl-3 py-0.5"
            >
                {{
                    sale.tipe === "service"
                        ? "Rincian Service"
                        : "Daftar Pembelian"
                }}
            </div>

            <!-- Items Table -->
            <table class="w-full mb-6 border border-collapse border-slate-300">
                <thead>
                    <tr class="border-b bg-slate-50 border-slate-300">
                        <th
                            class="border-r border-slate-300 p-2 text-center w-[40px] font-bold"
                        >
                            No
                        </th>
                        <th
                            class="border-r border-slate-300 p-2 text-left w-[280px] font-bold"
                        >
                            Nama Produk
                        </th>
                        <th
                            class="border-r border-slate-300 p-2 text-center w-[80px] font-bold"
                        >
                            Satuan
                        </th>
                        <th
                            class="border-r border-slate-300 p-2 text-right w-[110px] font-bold"
                        >
                            Harga
                        </th>
                        <th
                            class="border-r border-slate-300 p-2 text-center w-[60px] font-bold"
                        >
                            Qty
                        </th>
                        <th class="p-2 text-right w-[130px] font-bold">
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, idx) in sale.items"
                        :key="idx"
                        class="align-top border-b border-slate-200"
                    >
                        <td
                            class="p-3 text-center border-r border-slate-200 text-slate-500"
                        >
                            {{ idx + 1 }}
                        </td>
                        <td class="p-3 border-r border-slate-200">
                            <div
                                class="mb-1 font-bold leading-tight text-black uppercase"
                            >
                                {{ item.product?.nama }}
                            </div>
                            <div class="text-[9px] text-slate-500 uppercase">
                                {{ item.product?.brand || "-" }}
                            </div>
                            <div
                                v-if="
                                    sale.tipe !== 'service' &&
                                    getProductIdentifierLines(item.product)
                                        .length > 0
                                "
                                class="text-[8px] text-blue-800 font-mono mt-2 pt-1 border-t border-slate-100 border-dashed"
                            >
                                <div
                                    v-for="(
                                        line, lineIndex
                                    ) in getProductIdentifierLines(
                                        item.product
                                    )"
                                    :key="`identifier-${idx}-${lineIndex}`"
                                >
                                    {{ line }}
                                </div>
                            </div>
                        </td>
                        <td
                            class="p-3 italic text-center uppercase border-r border-slate-200 text-slate-500"
                        >
                            {{ item.product?.unit || "Unit" }}
                        </td>
                        <td class="p-3 text-right border-r border-slate-200">
                            Rp.{{ formatCurrency(item.harga_satuan) }},-
                        </td>
                        <td
                            class="p-3 font-bold text-center border-r border-slate-200"
                        >
                            {{ item.qty }}
                        </td>
                        <td class="p-3 font-bold text-right text-black">
                            Rp.{{ formatCurrency(item.subtotal) }},-
                        </td>
                    </tr>
                    <tr
                        v-if="
                            sale.tipe === 'service' &&
                            (sale.service_order?.biaya_jasa || 0) > 0
                        "
                        class="align-top border-b border-slate-200"
                    >
                        <td
                            class="p-3 text-center border-r border-slate-200 text-slate-500"
                        >
                            {{ (sale.items?.length || 0) + 1 }}
                        </td>
                        <td class="p-3 border-r border-slate-200">
                            <div
                                class="mb-1 font-bold leading-tight text-black uppercase"
                            >
                                Biaya Jasa Service
                            </div>
                            <div class="text-[9px] text-slate-500 uppercase">
                                Jasa Perbaikan
                            </div>
                        </td>
                        <td
                            class="p-3 italic text-center uppercase border-r border-slate-200 text-slate-500"
                        >
                            Jasa
                        </td>
                        <td class="p-3 text-right border-r border-slate-200">
                            Rp.{{
                                formatCurrency(
                                    sale.service_order?.biaya_jasa || 0
                                )
                            }},-
                        </td>
                        <td
                            class="p-3 font-bold text-center border-r border-slate-200"
                        >
                            -
                        </td>
                        <td class="p-3 font-bold text-right text-black">
                            Rp.{{
                                formatCurrency(
                                    sale.service_order?.biaya_jasa || 0
                                )
                            }},-
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-black bg-slate-50">
                        <td
                            colspan="4"
                            class="p-3 text-right uppercase tracking-widest text-[9px]"
                        >
                            Total Kuantitas
                        </td>
                        <td class="p-3 text-center border-x border-slate-200">
                            {{
                                sale.items.reduce(
                                    (acc, item) => acc + item.qty,
                                    0
                                )
                            }}
                        </td>
                        <td class="p-3 font-black text-right">
                            Rp.{{ formatCurrency(sale.subtotal) }},-
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Footer Details Section -->
            <table class="w-full table-fixed">
                <tr>
                    <!-- Payment Info -->
                    <td class="w-1/2 pr-8 align-top">
                        <div
                            class="p-4 uppercase border rounded border-slate-200 bg-slate-50"
                        >
                            <p
                                class="font-bold text-slate-400 mb-2 text-[9px] tracking-widest"
                            >
                                INFO PEMBAYARAN
                            </p>

                            <!-- CASH -->
                            <div v-if="sale.metode_pembayaran === 'cash'">
                                <p class="text-sm font-black text-black">
                                    TUNAI / CASH
                                </p>
                            </div>

                            <!-- TRANSFER -->
                            <div
                                v-else-if="
                                    sale.metode_pembayaran === 'transfer'
                                "
                            >
                                <p class="mb-1 text-xs font-bold text-blue-800">
                                    {{ store?.bank_name || "BANK" }}
                                    {{ store?.bank_account }}
                                </p>
                                <p class="font-black text-black">
                                    A/N :
                                    {{
                                        store?.bank_account_name ||
                                        "FEBRYANA NURUDIN ARANIRI"
                                    }}
                                </p>
                                <p
                                    v-if="sale.keterangan"
                                    class="text-[9px] text-slate-500 mt-2 normal-case italic"
                                >
                                    Ket: {{ sale.keterangan }}
                                </p>
                            </div>

                            <!-- QRIS -->
                            <div
                                v-else-if="sale.metode_pembayaran === 'qris'"
                                class="flex flex-col items-center"
                            >
                                <p class="self-start mb-1 text-xs font-bold">
                                    QRIS
                                </p>
                                <img
                                    v-if="store?.qris_image_url"
                                    :src="store.qris_image_url"
                                    class="object-contain w-20 h-20"
                                />
                                <div
                                    v-else
                                    class="text-[8px] text-slate-400 italic"
                                >
                                    QRIS Belum Diunggah
                                </div>
                            </div>

                            <!-- OTHER -->
                            <div v-else>
                                <p class="mb-1 font-bold">
                                    {{ sale.metode_pembayaran }}
                                </p>
                                <p
                                    v-if="sale.keterangan"
                                    class="text-slate-600 text-[10px]"
                                >
                                    {{ sale.keterangan }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <!-- Calculation Info -->
                    <td class="w-1/2 align-top">
                        <table class="w-full border-collapse">
                            <tr class="border-b border-slate-100">
                                <td
                                    class="py-2 font-bold uppercase text-slate-500"
                                >
                                    Sub Total
                                </td>
                                <td class="py-2 font-bold text-right">
                                    Rp.{{ formatCurrency(sale.subtotal) }},-
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td
                                    class="py-2 font-bold uppercase text-slate-500"
                                >
                                    Diskon
                                </td>
                                <td
                                    class="py-2 font-bold text-right text-red-600"
                                >
                                    <span v-if="sale.diskon_persen > 0"
                                        >({{ sale.diskon_persen }}%)</span
                                    >
                                    Rp.{{
                                        formatCurrency(sale.diskon_nominal)
                                    }},-
                                </td>
                            </tr>
                            <tr class="border-t-2 border-black">
                                <td
                                    class="py-4 font-black uppercase text-[12px]"
                                >
                                    GRAND TOTAL
                                </td>
                                <td
                                    class="py-4 text-right font-black text-[16px]"
                                >
                                    Rp.{{ formatCurrency(sale.grand_total) }},-
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- Row for footer info (aligned left and right) -->
                <tr>
                    <td class="pt-10 align-bottom">
                        <div>
                            <p
                                class="font-bold text-slate-400 text-[9px] tracking-widest mb-1"
                            >
                                TERIMA KASIH ATAS
                            </p>
                            <p class="font-black text-[11px] uppercase">
                                KEPERCAYAAN & PEMBELIAN ANDA
                            </p>
                        </div>
                    </td>
                    <td class="pt-10 text-right align-bottom">
                        <div class="mb-0">
                            <p
                                class="font-signature text-[18pt] text-slate-800 leading-none whitespace-nowrap"
                            >
                                {{
                                    store?.signature_name ||
                                    "Febryana Nurudin Araniri"
                                }}
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</template>

<style>
@import url("https://fonts.googleapis.com/css2?family=Courgette&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap");

.font-signature {
    font-family: "Courgette", cursive !important;
}

@media print {
    /* RESET EVERYTHING FOR CLEAN PRINT */
    @page {
        size: auto;
        margin: 0mm;
    }

    html,
    body {
        height: auto !important;
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    /* Hide EVERYTHING from Dashboard layout */
    body * {
        visibility: hidden !important;
    }

    /* ONLY show the primary invoice area */
    #invoice-printable,
    #invoice-printable * {
        visibility: visible !important;
    }

    #invoice-printable {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 10mm !important;
        background: white !important;
        box-shadow: none !important;
        border: none !important;
    }

    /* Prevent page breaks inside key sections */
    table,
    tr,
    td,
    th {
        break-inside: avoid !important;
    }

    /* Force font for print */
    #invoice-printable,
    #invoice-printable p,
    #invoice-printable td,
    #invoice-printable th,
    #invoice-printable h1 {
        font-family: "Space Mono", monospace !important;
        color: black !important;
    }

    /* Fix image visibility in print */
    img {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
