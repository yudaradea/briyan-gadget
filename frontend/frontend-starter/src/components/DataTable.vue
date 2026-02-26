<template>
    <div>
        <!-- Search & Controls Bar -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4"
        >
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-initial">
                    <input
                        type="text"
                        :placeholder="searchPlaceholder"
                        v-model="searchQuery"
                        @input="onSearchDebounced"
                        class="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    />
                    <svg
                        class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"
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
                <select
                    v-model="perPage"
                    @change="fetchData"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                >
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select>
            </div>
            <slot name="actions"></slot>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"
            ></div>
        </div>

        <!-- Table Section -->
        <div
            v-else
            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden"
        >
            <div class="table-container">
                <table class="table-fixed-layout">
                    <thead class="table-header">
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="table-cell"
                                :class="col.class || ''"
                            >
                                {{ col.label }}
                            </th>
                            <th
                                v-if="$slots.rowActions"
                                class="table-cell text-right w-32 px-6"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-if="data.length === 0">
                            <td
                                :colspan="
                                    columns.length + ($slots.rowActions ? 2 : 1)
                                "
                                class="px-6 py-12 text-center text-slate-500 italic"
                            >
                                Tidak ada data ditemukan.
                            </td>
                        </tr>
                        <tr
                            v-for="(row, index) in data"
                            :key="row.id"
                            class="table-row group"
                        >
                            <td
                                class="table-cell text-slate-500 text-center font-medium"
                            >
                                {{ startNumber + index }}
                            </td>
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="table-cell text-slate-700"
                                :class="col.cellClass || ''"
                            >
                                <slot
                                    :name="'cell-' + col.key"
                                    :row="row"
                                    :value="getCellValue(row, col.key)"
                                >
                                    {{ getCellValue(row, col.key) || "-" }}
                                </slot>
                            </td>
                            <td
                                v-if="$slots.rowActions"
                                class="table-cell text-center"
                            >
                                <slot name="rowActions" :row="row"></slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination"
                class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6"
            >
                <div class="hidden sm:flex sm:items-center">
                    <p class="text-sm text-gray-700">
                        Menampilkan
                        <span class="font-medium">{{
                            pagination.from || 0
                        }}</span>
                        -
                        <span class="font-medium">{{
                            pagination.to || 0
                        }}</span>
                        dari
                        <span class="font-medium">{{ pagination.total }}</span>
                        data
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage <= 1"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ‹
                    </button>
                    <template v-for="(page, idx) in visiblePages">
                        <span
                            v-if="page === '...'"
                            :key="'dots-' + idx"
                            class="px-2 py-1.5 text-sm text-gray-500"
                            >...</span
                        >
                        <button
                            v-else
                            :key="page"
                            @click="goToPage(page)"
                            :class="[
                                'px-3 py-1.5 text-sm border rounded-md',
                                page === currentPage
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'border-gray-300 hover:bg-gray-50',
                            ]"
                        >
                            {{ page }}
                        </button>
                    </template>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage >= lastPage"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ›
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";

const props = defineProps({
    columns: { type: Array, required: true },
    fetchFunction: { type: Function, required: true },
    searchPlaceholder: { type: String, default: "Cari..." },
    initialPerPage: { type: Number, default: 10 },
});

const emit = defineEmits(["loaded"]);

const data = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const perPage = ref(props.initialPerPage);
const currentPage = ref(1);
const lastPage = ref(1);
const pagination = ref(null);

let debounceTimer = null;

const startNumber = computed(() => {
    return (currentPage.value - 1) * perPage.value + 1;
});

const visiblePages = computed(() => {
    const pages = [];
    const total = lastPage.value;
    const current = currentPage.value;

    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        pages.push(1);
        if (current > 3) pages.push("...");
        for (
            let i = Math.max(2, current - 1);
            i <= Math.min(total - 1, current + 1);
            i++
        ) {
            pages.push(i);
        }
        if (current < total - 2) pages.push("...");
        pages.push(total);
    }
    return pages;
});

async function fetchData() {
    loading.value = true;
    try {
        const result = await props.fetchFunction({
            page: currentPage.value,
            per_page: perPage.value,
            search: searchQuery.value,
        });

        if (result?.data) {
            data.value = result.data;
            currentPage.value = result.current_page;
            lastPage.value = result.last_page;
            pagination.value = result;
        }
        emit("loaded", result);
    } catch (error) {
        console.error("DataTable fetch error:", error);
    } finally {
        loading.value = false;
    }
}

function onSearchDebounced() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        currentPage.value = 1;
        fetchData();
    }, 300);
}

function goToPage(page) {
    if (page < 1 || page > lastPage.value) return;
    currentPage.value = page;
    fetchData();
}

function refresh() {
    fetchData();
}

function getCellValue(row, key) {
    if (!row || !key) return null;
    if (!key.includes(".")) return row[key];

    return key.split(".").reduce((acc, part) => {
        if (acc === null || acc === undefined) return null;
        return acc[part];
    }, row);
}

fetchData();

defineExpose({ refresh, fetchData });
</script>
