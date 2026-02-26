<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api";
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import debounce from "lodash-es/debounce";
import ConfirmDialog from "../../components/ConfirmDialog.vue";

const router = useRouter();
const toast = useToast();

const services = ref([]);
const isLoading = ref(false);
const searchQuery = ref("");
const perPage = ref(10);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 10,
});

const filters = ref({
    status: "",
    start_date: "",
    end_date: "",
});

onMounted(() => {
    fetchServices();
});

async function fetchServices(page = 1) {
    isLoading.value = true;
    try {
        const { data } = await api.get("/services", {
            params: {
                page,
                per_page: perPage.value,
                search: searchQuery.value,
                status: filters.value.status,
                start_date: filters.value.start_date,
                end_date: filters.value.end_date,
            },
        });

        services.value = data.data.data;
        pagination.value = {
            current_page: data.data.current_page,
            last_page: data.data.last_page,
            total: data.data.total,
            per_page: data.data.per_page,
        };
    } catch (err) {
        toast.error("Gagal memuat data servis");
    } finally {
        isLoading.value = false;
    }
}

const throttledSearch = debounce(() => {
    fetchServices(1);
}, 500);

watch(searchQuery, throttledSearch);
watch(perPage, () => fetchServices(1));
watch(
    () => filters.value,
    () => fetchServices(1),
    { deep: true },
);

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 0,
    }).format(val || 0);
}

function formatDate(dateStr) {
    if (!dateStr) return "-";
    const [y, m, d] = dateStr.split("-");
    return `${d}-${m}-${y}`;
}

const showDelete = ref(false);
const deleteId = ref(null);
const deleting = ref(false);

function confirmDelete(id) {
    deleteId.value = id;
    showDelete.value = true;
}

async function doDelete() {
    if (!deleteId.value) return;
    deleting.value = true;
    try {
        await api.delete(`/services/${deleteId.value}`);
        toast.success("Data servis berhasil dihapus");
        fetchServices(pagination.value.current_page);
    } catch (err) {
        toast.error(
            err.response?.data?.message || "Gagal menghapus data servis",
        );
    } finally {
        deleting.value = false;
        showDelete.value = false;
        deleteId.value = null;
    }
}

const statusBadges = {
    pending: "bg-slate-100 text-slate-500 border-slate-200",
    dikerjakan: "bg-blue-50 text-blue-600 border-blue-100",
    selesai: "bg-emerald-50 text-emerald-600 border-emerald-100",
    diambil: "bg-purple-50 text-purple-600 border-purple-100",
    batal: "bg-rose-50 text-rose-600 border-rose-100",
};

const statusLabels = {
    pending: "Pending",
    dikerjakan: "Proses",
    selesai: "Selesai",
    batal: "Batal",
};
</script>

<template>
    <div class="px-4 md:px-8 mx-auto py-6 space-y-6 max-w-[1400px]">
        <!-- Header Section -->
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4"
        >
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">
                    Data Servis HP
                </h1>
                <p class="text-slate-500 text-sm font-medium">
                    Kelola antrian dan progres perbaikan unit
                </p>
            </div>
            <router-link
                to="/dashboard/services/create"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold transition-all shadow-xl shadow-blue-500/20 active:scale-95"
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
                        stroke-width="2.5"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Service Masuk Baru
            </router-link>
        </div>

        <div
            class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
        >
            <!-- Filter Bar -->
            <div
                class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row gap-6 justify-between items-start md:items-center"
            >
                <!-- Left: Advanced Filters -->
                <div
                    class="grid grid-cols-2 md:grid-cols-3 gap-4 w-full md:w-auto"
                >
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest"
                            >Status</label
                        >
                        <select
                            v-model="filters.status"
                            class="px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition bg-white min-w-[140px] shadow-sm"
                        >
                            <option value="">Semua Status</option>
                            <option value="dikerjakan">Proses</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest"
                            >Mulai</label
                        >
                        <input
                            type="date"
                            v-model="filters.start_date"
                            class="px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition bg-white shadow-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest"
                            >Sampai</label
                        >
                        <input
                            type="date"
                            v-model="filters.end_date"
                            class="px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition bg-white shadow-sm"
                        />
                    </div>
                </div>

                <!-- Right: Pagination & Search -->
                <div class="flex flex-row items-end gap-3 w-full md:w-auto">
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest"
                            >Tampilkan</label
                        >
                        <select
                            v-model="perPage"
                            class="block w-20 px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition bg-white shadow-sm"
                        >
                            <option :value="10">10</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5 grow md:grow-0">
                        <label
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest"
                            >Search</label
                        >
                        <div class="relative">
                            <input
                                type="text"
                                v-model="searchQuery"
                                placeholder="Cari pelanggan/unit..."
                                class="block w-full md:w-64 pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition shadow-sm"
                            />
                            <div
                                class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"
                            >
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2.5"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th
                                class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-20"
                            >
                                No
                            </th>
                            <th
                                class="px-4 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]"
                            >
                                Pelanggan
                            </th>
                            <th
                                class="px-4 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]"
                            >
                                Unit / IMEI
                            </th>
                            <th
                                class="px-4 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]"
                            >
                                Kerusakan
                            </th>
                            <th
                                class="px-4 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-32"
                            >
                                Perbaikan
                            </th>
                            <th
                                class="px-4 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-32"
                            >
                                Pengambilan
                            </th>
                            <th
                                class="px-4 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-32"
                            >
                                Tgl Masuk
                            </th>
                            <th
                                class="px-4 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-40"
                            >
                                Estimasi
                            </th>
                            <th
                                class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-20 bg-slate-50/80 sticky right-0 border-l border-slate-100"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="isLoading">
                            <td
                                colspan="9"
                                class="px-6 py-20 text-center text-slate-500"
                            >
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="w-10 h-10 border-4 border-blue-50 border-t-blue-500 rounded-full animate-spin"
                                    ></div>
                                    <span
                                        class="text-xs font-black uppercase tracking-widest text-slate-400"
                                        >Memuat Data...</span
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="services.length === 0">
                            <td
                                colspan="9"
                                class="px-6 py-20 text-center text-slate-400 italic font-medium"
                            >
                                Tidak ada data servis ditemukan.
                            </td>
                        </tr>
                        <tr
                            v-for="(item, index) in services"
                            :key="item.id"
                            class="hover:bg-blue-50/30 transition-colors group"
                        >
                            <td
                                class="px-6 py-4 text-center text-xs font-black text-slate-400 group-hover:text-blue-500"
                            >
                                {{
                                    (pagination.current_page - 1) *
                                        pagination.per_page +
                                    index +
                                    1
                                }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-800 text-sm">
                                    {{ item.nama_pelanggan }}
                                </div>
                                <div
                                    class="text-[10px] font-bold text-slate-400 mt-0.5"
                                >
                                    {{ item.no_hp_pelanggan || "-" }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-700 text-sm">
                                    {{ item.merk_hp }} {{ item.tipe_hp }}
                                </div>
                                <div
                                    class="text-[10px] font-mono text-slate-400 mt-0.5 uppercase tracking-tighter"
                                >
                                    {{ item.imei_hp || "-" }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p
                                    class="text-slate-500 line-clamp-1 italic text-xs font-medium"
                                >
                                    "{{ item.kerusakan }}"
                                </p>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span
                                    :class="statusBadges[item.status]"
                                    class="inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border shadow-sm"
                                >
                                    {{ statusLabels[item.status] }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span
                                    v-if="
                                        item.status_pengambilan ===
                                        'sudah_diambil'
                                    "
                                    class="bg-purple-50 text-purple-600 border-purple-100 inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border shadow-sm"
                                >
                                    SUDAH DIAMBIL
                                </span>
                                <span
                                    v-else
                                    class="bg-slate-50 text-slate-400 border-slate-100 inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border shadow-sm"
                                >
                                    BELUM DIAMBIL
                                </span>
                            </td>
                            <td
                                class="px-4 py-4 text-center text-slate-500 text-[11px] font-bold"
                            >
                                {{ formatDate(item.tanggal_masuk) }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase mr-1"
                                    >Rp</span
                                >
                                <span
                                    class="text-sm font-black text-slate-800 tracking-tight"
                                >
                                    {{ formatCurrency(item.grand_total) }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-center sticky right-0 bg-white group-hover:bg-blue-50/30 border-l border-slate-50 transition-colors"
                            >
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <button
                                        @click="
                                            router.push(
                                                `/dashboard/services/${item.id}`,
                                            )
                                        "
                                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-all"
                                        title="Detail Service"
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
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="
                                            router.push(
                                                `/dashboard/services/${item.id}/edit`,
                                            )
                                        "
                                        class="p-2 text-amber-500 hover:bg-amber-50 rounded-lg transition-all"
                                        title="Edit Data"
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
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="confirmDelete(item.id)"
                                        class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition-all"
                                        title="Hapus Data"
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
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Section -->
            <div
                v-if="pagination.last_page > 1"
                class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30"
            >
                <div
                    class="text-xs font-bold text-slate-400 uppercase tracking-widest"
                >
                    Halaman
                    <span class="text-slate-800">{{
                        pagination.current_page
                    }}</span>
                    dari
                    <span class="text-slate-800">{{
                        pagination.last_page
                    }}</span>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="fetchServices(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-all shadow-sm"
                    >
                        Prev
                    </button>
                    <button
                        @click="fetchServices(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-all shadow-sm"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
        <ConfirmDialog
            :show="showDelete"
            :loading="deleting"
            title="Konfirmasi Hapus"
            message="Hapus data servis ini secara permanen?"
            confirmText="Hapus"
            loadingText="Menghapus..."
            @confirm="doDelete"
            @cancel="showDelete = false"
        />
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
