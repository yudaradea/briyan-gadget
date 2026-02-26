<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../api";
import { useToast } from "../composables/useToast";

const toast = useToast();
const loading = ref(true);
const summary = ref(null);

const colors = {
    sales: "from-sky-500 to-blue-600 shadow-sky-500/30",
    profit: "from-amber-400 to-orange-500 shadow-orange-500/30",
    purchases: "from-rose-400 to-red-500 shadow-rose-500/30",
};

const compact = new Intl.NumberFormat("id-ID", {
    notation: "compact",
    compactDisplay: "short",
    maximumFractionDigits: 1,
});

const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
});

const salesCards = computed(() => {
    const c = summary.value?.cards?.sales || {};
    return [
        { label: "Hari Ini", value: c.today || 0 },
        { label: "Bulan Ini", value: c.month || 0 },
        { label: "Tahun Ini", value: c.year || 0 },
        { label: "Total", value: c.total || 0 },
    ];
});

const profitCards = computed(() => {
    const c = summary.value?.cards?.profit || {};
    return [
        { label: "Hari Ini", value: c.today || 0 },
        { label: "Bulan Ini", value: c.month || 0 },
        { label: "Tahun Ini", value: c.year || 0 },
        { label: "Total", value: c.total || 0 },
    ];
});

const purchaseCards = computed(() => {
    const c = summary.value?.cards?.purchases || {};
    return [
        { label: "Hari Ini", value: c.today || 0 },
        { label: "Bulan Ini", value: c.month || 0 },
        { label: "Tahun Ini", value: c.year || 0 },
        { label: "Total", value: c.total || 0 },
    ];
});

const miniCards = computed(() => {
    const c = summary.value?.cards?.summary || {};
    return [
        { label: "Invoice Pembelian", value: c.purchase_invoices || 0 },
        { label: "Produk Katalog", value: c.catalog_products || 0 },
        { label: "Kategori Produk", value: c.categories || 0 },
        { label: "Grade Produk", value: c.grades || 0 },
        { label: "Sales Rep", value: c.sales_reps || 0 },
        { label: "Mitra Supplier", value: c.suppliers || 0 },
    ];
});

const dailyChart = computed(() => summary.value?.charts?.daily || null);
const monthlyChart = computed(() => summary.value?.charts?.monthly || null);

function chartRows(source) {
    if (!source?.labels?.length) return [];
    return source.labels.map((label, index) => ({
        label,
        sales: Number(source.sales?.[index] || 0),
        purchases: Number(source.purchases?.[index] || 0),
        profit: Number(source.profit?.[index] || 0),
    }));
}

const dailyRows = computed(() => chartRows(dailyChart.value));
const monthlyRows = computed(() => chartRows(monthlyChart.value));

function maxValue(rows) {
    return Math.max(
        1,
        ...rows.map((r) => Math.max(r.sales, r.purchases, r.profit))
    );
}

function barHeight(value, max) {
    return `${Math.max(2, (value / max) * 100)}%`; // minimal 2% agar bar yang isinya 0 tetap terlihat sedikit garis bawahnya
}

// Fungsi baru untuk menghasilkan angka Y-Axis dari atas (100%) ke bawah (0%)
function getYAxisTicks(max) {
    return [1, 0.75, 0.5, 0.25, 0].map((ratio) => compact.format(max * ratio));
}

async function fetchSummary() {
    loading.value = true;
    try {
        const { data } = await api.get("/dashboard/summary");
        summary.value = data.data;
    } catch (err) {
        toast.error(err.response?.data?.message || "Gagal memuat dashboard");
    } finally {
        loading.value = false;
    }
}

onMounted(fetchSummary);
</script>

<template>
    <section
        class="min-h-screen p-4 space-y-8 font-sans bg-slate-50 sm:p-6 lg:p-8"
    >
        <div
            class="relative p-8 overflow-hidden text-white shadow-2xl rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-black shadow-slate-900/20"
        >
            <div class="relative z-10">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-400"
                >
                    Ringkasan Bisnis
                </p>
                <h1
                    class="mt-3 text-3xl font-extrabold tracking-tight text-transparent sm:text-4xl bg-clip-text bg-gradient-to-r from-white to-slate-400"
                >
                    Dashboard Operasional
                </h1>
                <p class="max-w-xl mt-2 text-sm font-medium text-slate-400">
                    Pantau performa real-time penjualan, pembelian, laba, dan
                    matriks utama toko Anda dalam satu tampilan cerdas.
                </p>
            </div>
            <div
                class="absolute rounded-full -right-10 -top-24 h-96 w-96 bg-gradient-to-br from-blue-500/20 to-purple-500/20 blur-3xl"
            ></div>
        </div>

        <div
            v-if="loading"
            class="flex flex-col items-center justify-center py-24 bg-white shadow-sm rounded-3xl ring-1 ring-slate-100"
        >
            <div
                class="w-10 h-10 border-4 rounded-full animate-spin border-slate-100 border-t-sky-500"
            ></div>
            <p class="mt-4 text-sm font-medium animate-pulse text-slate-400">
                Menyiapkan data dashboard...
            </p>
        </div>

        <template v-else>
            <div class="space-y-10">
                <div
                    v-for="(section, idx) in [
                        {
                            title: 'Penjualan',
                            cards: salesCards,
                            color: colors.sales,
                        },
                        {
                            title: 'Laba Bersih',
                            cards: profitCards,
                            color: colors.profit,
                        },
                        {
                            title: 'Pembelian',
                            cards: purchaseCards,
                            color: colors.purchases,
                        },
                    ]"
                    :key="idx"
                    class="space-y-4"
                >
                    <h2
                        class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-slate-400"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-slate-400"
                        ></span>
                        {{ section.title }}
                    </h2>
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in section.cards"
                            :key="card.label"
                            class="p-5 transition-all duration-300 bg-white shadow-sm group rounded-2xl ring-1 ring-slate-200/60 hover:-translate-y-1 hover:shadow-lg"
                        >
                            <p
                                class="text-xs font-semibold tracking-wider uppercase text-slate-400"
                            >
                                {{ card.label }}
                            </p>
                            <div
                                class="inline-flex items-center px-4 py-2 mt-4 text-sm font-bold text-white transition-transform shadow-md rounded-xl bg-gradient-to-r group-hover:scale-105"
                                :class="section.color"
                            >
                                {{ currency.format(card.value) }}
                            </div>
                        </article>
                    </div>
                </div>

                <div class="space-y-4">
                    <h2
                        class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-slate-400"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-slate-400"
                        ></span>
                        Metrik Pendukung
                    </h2>
                    <div class="grid gap-5 sm:grid-cols-3 xl:grid-cols-6">
                        <article
                            v-for="card in miniCards"
                            :key="card.label"
                            class="p-5 transition-all duration-300 bg-white shadow-sm rounded-2xl ring-1 ring-slate-200/60 hover:shadow-md"
                        >
                            <p
                                class="text-[10px] font-bold uppercase tracking-wider text-slate-400"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-3xl font-black tracking-tight text-slate-700"
                            >
                                {{ compact.format(card.value) }}
                            </p>
                        </article>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section
                        v-for="(chart, idx) in [
                            {
                                title: 'Grafik 14 Hari Terakhir',
                                rows: dailyRows,
                            },
                            {
                                title: `Grafik Bulanan (${new Date().getFullYear()})`,
                                rows: monthlyRows,
                            },
                        ]"
                        :key="idx"
                        class="p-6 bg-white shadow-sm rounded-3xl ring-1 ring-slate-200/60"
                    >
                        <header
                            class="flex flex-wrap items-center justify-between gap-4 mb-8"
                        >
                            <h3 class="text-base font-extrabold text-slate-800">
                                {{ chart.title }}
                            </h3>
                            <div
                                class="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wide"
                            >
                                <span
                                    class="flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-100 px-3 py-1.5 text-sky-600"
                                >
                                    <span
                                        class="w-2 h-2 rounded-full bg-sky-500"
                                    ></span>
                                    Penjualan
                                </span>
                                <span
                                    class="flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-100 px-3 py-1.5 text-amber-600"
                                >
                                    <span
                                        class="w-2 h-2 rounded-full bg-amber-500"
                                    ></span>
                                    Laba
                                </span>
                                <span
                                    class="flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-100 px-3 py-1.5 text-rose-600"
                                >
                                    <span
                                        class="w-2 h-2 rounded-full bg-rose-500"
                                    ></span>
                                    Pembelian
                                </span>
                            </div>
                        </header>

                        <div class="flex h-[320px] w-full gap-4">
                            <div
                                class="flex flex-col justify-between pb-8 text-right text-[11px] font-semibold text-slate-400 w-12"
                            >
                                <span
                                    v-for="tick in getYAxisTicks(
                                        maxValue(chart.rows)
                                    )"
                                    :key="tick"
                                    >{{ tick }}</span
                                >
                            </div>

                            <div
                                class="relative flex-1 border-b border-l rounded-lg border-slate-200"
                            >
                                <div
                                    class="absolute inset-0 z-0 flex flex-col justify-between pb-8"
                                >
                                    <div
                                        v-for="i in 5"
                                        :key="i"
                                        class="w-full h-0 border-t border-dashed border-slate-200/70"
                                    ></div>
                                </div>

                                <div
                                    class="absolute inset-0 z-10 flex items-end gap-3 px-2 pb-8 overflow-x-auto scrollbar-hide"
                                >
                                    <div
                                        v-for="item in chart.rows"
                                        :key="item.label"
                                        class="group relative flex min-w-[48px] flex-col items-center gap-2 h-full justify-end"
                                    >
                                        <div
                                            class="flex h-full w-full items-end justify-center gap-[2px]"
                                        >
                                            <div
                                                class="w-3.5 rounded-t-md bg-gradient-to-t from-sky-600 to-sky-400 opacity-90 transition-all duration-300 hover:opacity-100 cursor-pointer"
                                                :style="{
                                                    height: barHeight(
                                                        item.sales,
                                                        maxValue(chart.rows)
                                                    ),
                                                }"
                                                :title="`Penjualan: ${currency.format(
                                                    item.sales
                                                )}`"
                                            ></div>
                                            <div
                                                class="w-3.5 rounded-t-md bg-gradient-to-t from-amber-500 to-amber-300 opacity-90 transition-all duration-300 hover:opacity-100 cursor-pointer"
                                                :style="{
                                                    height: barHeight(
                                                        item.profit,
                                                        maxValue(chart.rows)
                                                    ),
                                                }"
                                                :title="`Laba: ${currency.format(
                                                    item.profit
                                                )}`"
                                            ></div>
                                            <div
                                                class="w-3.5 rounded-t-md bg-gradient-to-t from-rose-600 to-rose-400 opacity-90 transition-all duration-300 hover:opacity-100 cursor-pointer"
                                                :style="{
                                                    height: barHeight(
                                                        item.purchases,
                                                        maxValue(chart.rows)
                                                    ),
                                                }"
                                                :title="`Pembelian: ${currency.format(
                                                    item.purchases
                                                )}`"
                                            ></div>
                                        </div>
                                        <span
                                            class="absolute -bottom-6 text-[11px] font-semibold text-slate-400 whitespace-nowrap"
                                            >{{ item.label }}</span
                                        >

                                        <div
                                            class="absolute top-0 bottom-0 hidden rounded-lg -inset-x-1 -z-10 bg-slate-50 group-hover:block"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </section>
</template>

<style scoped>
/* Opsional: Menyembunyikan scrollbar agar lebih bersih tapi tetap bisa di-scroll secara horizontal */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
