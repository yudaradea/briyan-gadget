<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "../stores/auth";
import { useRouter } from "vue-router";
import { useToast } from "../composables/useToast";
import api from "../api";

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();

const sidebarOpen = ref(true);
const masterDataOpen = ref(false);
const userMgmtOpen = ref(false);
const mobileSidebarOpen = ref(false);
const storeProfile = ref({
    name: "App Kasir",
    logo_url: null,
});

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const handleLogout = async () => {
    await authStore.logout();
    toast.success("Berhasil logout");
    router.push("/login");
};

const userInitial = computed(() => {
    return authStore.user?.name?.charAt(0)?.toUpperCase() || "U";
});

const roleBadge = computed(() => {
    if (authStore.isSuperAdmin)
        return { label: "Super Admin", class: "bg-purple-100 text-purple-700" };
    if (authStore.isAdmin)
        return { label: "Admin", class: "bg-blue-100 text-blue-700" };
    return { label: "Kasir", class: "bg-green-100 text-green-700" };
});

async function fetchStoreProfile() {
    try {
        const { data } = await api.get("/store-settings");
        if (data?.data) {
            storeProfile.value = {
                name: data.data.name || "App Kasir",
                logo_url: data.data.logo_url || null,
            };
        }
    } catch (error) {
        // Keep fallback when settings are not available.
    }
}

onMounted(fetchStoreProfile);
</script>

<template>
    <div class="flex min-h-screen bg-slate-50">
        <!-- Mobile Overlay -->
        <div
            v-if="mobileSidebarOpen"
            @click="mobileSidebarOpen = false"
            class="fixed inset-0 z-40 transition-opacity bg-black/40 md:hidden backdrop-blur-sm"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'z-50 flex flex-col transition-all duration-300 ease-in-out shrink-0 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white shadow-2xl h-screen',
                sidebarOpen ? 'w-64' : 'w-[70px]',
                mobileSidebarOpen
                    ? 'fixed translate-x-0'
                    : 'fixed md:sticky top-0 -translate-x-full md:translate-x-0',
            ]"
        >
            <!-- Logo -->
            <div
                class="flex items-center gap-3 px-4 py-5 border-b border-white/10"
            >
                <div
                    class="flex items-center justify-center shadow-lg w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 shadow-blue-500/25 shrink-0"
                >
                    <img
                        v-if="storeProfile.logo_url"
                        :src="storeProfile.logo_url"
                        alt="Logo Toko"
                        class="object-cover w-full h-full rounded-xl"
                    />
                    <svg
                        v-else
                        class="w-5 h-5 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"
                        />
                    </svg>
                </div>
                <transition name="fade">
                    <span
                        v-if="sidebarOpen"
                        class="text-lg font-bold tracking-tight"
                        >{{ storeProfile.name }}</span
                    >
                </transition>
            </div>

            <!-- Navigation -->
            <nav
                class="flex-1 px-3 py-4 space-y-1 overflow-y-auto"
                :class="{ 'px-2': !sidebarOpen }"
            >
                <!-- Dashboard -->
                <router-link
                    to="/dashboard"
                    class="nav-item"
                    :class="
                        $route.name === 'dashboard'
                            ? 'nav-active'
                            : 'nav-inactive'
                    "
                >
                    <svg
                        class="w-5 h-5 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                        />
                    </svg>
                    <span v-if="sidebarOpen">Dashboard</span>
                </router-link>

                <router-link
                    to="/dashboard/profile"
                    class="nav-item"
                    :class="
                        $route.name === 'profile'
                            ? 'nav-active'
                            : 'nav-inactive'
                    "
                >
                    <svg
                        class="w-5 h-5 shrink-0"
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
                    <span v-if="sidebarOpen">Profil Saya</span>
                </router-link>

                <div v-if="authStore.isSuperAdmin || authStore.isAdmin">
                    <div v-if="sidebarOpen" class="section-label">
                        Gudang & Stok
                    </div>
                    <div v-else class="w-8 h-px mx-auto my-3 bg-white/20"></div>

                    <router-link
                        to="/dashboard/purchases"
                        class="nav-item"
                        :class="
                            $route.path === '/dashboard/purchases'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                            />
                        </svg>
                        <span v-if="sidebarOpen"
                            >Pembelian (Input Invoice Supplier)</span
                        >
                    </router-link>

                    <router-link
                        to="/dashboard/stock-summary"
                        class="mt-1 nav-item"
                        :class="
                            $route.path === '/dashboard/stock-summary'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                        <span v-if="sidebarOpen"
                            >Stok Barang (Ringkasan per SKU)</span
                        >
                    </router-link>

                    <router-link
                        to="/dashboard/purchase-items"
                        class="mt-1 nav-item"
                        :class="
                            $route.path === '/dashboard/purchase-items'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                            />
                        </svg>
                        <span v-if="sidebarOpen">Mutasi Stok</span>
                    </router-link>
                </div>

                <div>
                    <div v-if="sidebarOpen" class="section-label">
                        Penjualan
                    </div>
                    <div v-else class="w-8 h-px mx-auto my-3 bg-white/20"></div>

                    <router-link
                        to="/dashboard/pos"
                        class="nav-item"
                        :class="
                            $route.path.startsWith('/dashboard/pos')
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                        <span v-if="sidebarOpen">POS</span>
                    </router-link>

                    <router-link
                        v-if="authStore.isSuperAdmin || authStore.isAdmin"
                        to="/dashboard/sales"
                        class="nav-item"
                        :class="
                            $route.path === '/dashboard/sales'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            ></path>
                        </svg>
                        <span v-if="sidebarOpen">Transaksi Penjualan</span>
                    </router-link>
                </div>

                <!-- Servis HP Section -->
                <div>
                    <div v-if="sidebarOpen" class="section-label">
                        Servis HP
                    </div>
                    <div v-else class="w-8 h-px mx-auto my-3 bg-white/20"></div>

                    <router-link
                        to="/dashboard/services"
                        class="nav-item"
                        :class="
                            $route.path.startsWith('/dashboard/services')
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span v-if="sidebarOpen">Data Servis Masuk</span>
                    </router-link>

                    <router-link
                        v-if="authStore.isSuperAdmin || authStore.isAdmin"
                        to="/dashboard/service-transactions"
                        class="mt-1 nav-item"
                        :class="
                            $route.path === '/dashboard/service-transactions'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            ></path>
                        </svg>
                        <span v-if="sidebarOpen">Transaksi Service</span>
                    </router-link>
                </div>

                <div v-if="authStore.isSuperAdmin || authStore.isAdmin">
                    <div v-if="sidebarOpen" class="section-label">Laporan</div>
                    <div v-else class="w-8 h-px mx-auto my-3 bg-white/20"></div>

                    <router-link
                        to="/dashboard/report/sales"
                        class="nav-item"
                        :class="
                            $route.name === 'report-sales'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M11 5h6a2 2 0 012 2v12M5 7h4m0 0h6m-6 0v12m0-12H5a2 2 0 00-2 2v10a2 2 0 002 2h4"
                            ></path>
                        </svg>
                        <span v-if="sidebarOpen"
                            >Laporan Penjualan & Service</span
                        >
                    </router-link>

                    <router-link
                        to="/dashboard/report/purchases"
                        class="mt-1 nav-item"
                        :class="
                            $route.name === 'report-purchases'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8c-2.21 0-4 1.343-4 3v6h8v-6c0-1.657-1.79-3-4-3zM5 14h2m4-4h3m-3 4h3"
                            ></path>
                        </svg>
                        <span v-if="sidebarOpen">Laporan Pembelian</span>
                    </router-link>

                    <router-link
                        to="/dashboard/report/profit"
                        class="mt-1 nav-item"
                        :class="
                            $route.name === 'report-profit'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m-6.5-1.5h3a1.5 1.5 0 013 0H18M5 3h14a2 2 0 012 2v4a2 2 0 01-2 2h-3.5a2 2 0 00-2 2v5a2 2 0 01-2 2h-3a2 2 0 01-2-2v-6a2 2 0 00-2-2H5a2 2 0 01-2-2V5a2 2 0 012-2z"
                            ></path>
                        </svg>
                        <span v-if="sidebarOpen">Laba Rugi / HPP</span>
                    </router-link>
                </div>
                <!-- Master Data Section -->
                <div v-if="authStore.isSuperAdmin || authStore.isAdmin">
                    <div v-if="sidebarOpen" class="section-label">
                        Master Data
                    </div>
                    <div v-else class="w-8 h-px mx-auto my-3 bg-white/20"></div>

                    <button
                        @click="masterDataOpen = !masterDataOpen"
                        class="justify-between w-full nav-item nav-inactive"
                    >
                        <span class="flex items-center gap-3">
                            <svg
                                class="w-5 h-5 shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"
                                />
                            </svg>
                            <span v-if="sidebarOpen">Master Data</span>
                        </span>
                        <svg
                            v-if="sidebarOpen"
                            :class="masterDataOpen ? 'rotate-180' : ''"
                            class="w-4 h-4 transition-transform duration-200"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <transition name="slide">
                        <div
                            v-show="masterDataOpen && sidebarOpen"
                            class="ml-3 pl-3 border-l border-white/10 space-y-0.5 mt-1"
                        >
                            <router-link
                                v-for="item in [
                                    {
                                        to: '/dashboard/master/products',
                                        name: 'master-product-list',
                                        label: 'Katalog Produk',
                                    },
                                    {
                                        to: '/dashboard/master/brands',
                                        name: 'brand-list',
                                        label: 'Merk',
                                    },
                                    {
                                        to: '/dashboard/master/categories',
                                        name: 'category-list',
                                        label: 'Kategori',
                                    },
                                    {
                                        to: '/dashboard/master/grades',
                                        name: 'grade-list',
                                        label: 'Grade',
                                    },
                                    {
                                        to: '/dashboard/master/units',
                                        name: 'unit-list',
                                        label: 'Satuan',
                                    },
                                    {
                                        to: '/dashboard/master/sales-reps',
                                        name: 'sales-rep-list',
                                        label: 'Sales',
                                    },
                                    {
                                        to: '/dashboard/master/suppliers',
                                        name: 'supplier-list',
                                        label: 'Supplier',
                                    },
                                    {
                                        to: '/dashboard/master/taxes',
                                        name: 'tax-list',
                                        label: 'Pajak',
                                    },
                                ]"
                                :key="item.name"
                                :to="item.to"
                                class="sub-nav-item"
                                :class="
                                    $route.name === item.name
                                        ? 'text-white bg-white/10'
                                        : 'text-slate-400 hover:text-white hover:bg-white/5'
                                "
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="
                                        $route.name === item.name
                                            ? 'bg-blue-400'
                                            : 'bg-slate-500'
                                    "
                                ></span>
                                {{ item.label }}
                            </router-link>
                        </div>
                    </transition>
                </div>

                <!-- Pengaturan Section -->
                <div v-if="authStore.isSuperAdmin || authStore.isAdmin">
                    <div v-if="sidebarOpen" class="section-label">
                        Pengaturan
                    </div>
                    <div v-else class="w-8 h-px mx-auto my-3 bg-white/20"></div>

                    <button
                        @click="userMgmtOpen = !userMgmtOpen"
                        class="justify-between w-full nav-item nav-inactive"
                    >
                        <span class="flex items-center gap-3">
                            <svg
                                class="w-5 h-5 shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                />
                            </svg>
                            <span v-if="sidebarOpen">User Management</span>
                        </span>
                        <svg
                            v-if="sidebarOpen"
                            :class="userMgmtOpen ? 'rotate-180' : ''"
                            class="w-4 h-4 transition-transform duration-200"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <transition name="slide">
                        <div
                            v-show="userMgmtOpen && sidebarOpen"
                            class="ml-3 pl-3 border-l border-white/10 space-y-0.5 mt-1"
                        >
                            <router-link
                                v-for="item in [
                                    {
                                        to: '/dashboard/users',
                                        name: 'user-list',
                                        label: 'Daftar User',
                                    },
                                    {
                                        to: '/dashboard/users/create',
                                        name: 'create-user',
                                        label: 'Tambah User',
                                    },
                                    {
                                        to: '/dashboard/roles',
                                        name: 'role-list',
                                        label: 'Role & Hak Akses',
                                    },
                                ]"
                                :key="item.name"
                                :to="item.to"
                                class="sub-nav-item"
                                :class="
                                    $route.name === item.name
                                        ? 'text-white bg-white/10'
                                        : 'text-slate-400 hover:text-white hover:bg-white/5'
                                "
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="
                                        $route.name === item.name
                                            ? 'bg-blue-400'
                                            : 'bg-slate-500'
                                    "
                                ></span>
                                {{ item.label }}
                            </router-link>
                        </div>
                    </transition>

                    <router-link
                        to="/dashboard/settings"
                        class="mt-1 nav-item"
                        :class="
                            $route.path === '/dashboard/settings'
                                ? 'nav-active'
                                : 'nav-inactive'
                        "
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                        <span v-if="sidebarOpen">Info Toko</span>
                    </router-link>
                </div>
            </nav>

            <!-- User Card at Bottom -->
            <div class="p-3 border-t border-white/10">
                <div
                    class="flex items-center gap-3"
                    :class="{ 'justify-center': !sidebarOpen }"
                >
                    <div
                        class="flex items-center justify-center text-sm font-bold shadow-lg w-9 h-9 rounded-xl bg-gradient-to-br from-blue-400 to-indigo-500 shrink-0"
                    >
                        {{ userInitial }}
                    </div>
                    <div v-if="sidebarOpen" class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">
                            {{ authStore.user?.name }}
                        </p>
                        <span
                            :class="roleBadge.class"
                            class="text-[10px] font-semibold px-1.5 py-0.5 rounded-md inline-block mt-0.5"
                            >{{ roleBadge.label }}</span
                        >
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div
            class="flex flex-col flex-1 min-w-0 min-h-screen transition-all duration-300"
        >
            <!-- Top Bar -->
            <header
                class="sticky top-0 z-30 bg-white border-b shadow-sm border-slate-200"
            >
                <div
                    class="flex items-center justify-between h-16 px-4 md:px-6"
                >
                    <div class="flex items-center gap-3">
                        <!-- Sidebar Toggle -->
                        <button
                            @click="sidebarOpen = !sidebarOpen"
                            class="hidden p-2 transition md:flex rounded-xl hover:bg-slate-100 text-slate-500"
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
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                        <!-- Mobile Menu -->
                        <button
                            @click="mobileSidebarOpen = true"
                            class="p-2 transition md:hidden rounded-xl hover:bg-slate-100 text-slate-500"
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
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            :class="roleBadge.class"
                            class="text-xs font-semibold px-2.5 py-1 rounded-lg hidden sm:inline-block"
                            >{{ roleBadge.label }}</span
                        >
                        <span
                            class="hidden text-sm font-medium text-slate-600 sm:inline-block"
                            >{{ authStore.user?.name }}</span
                        >
                        <button
                            @click="handleLogout"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition text-slate-500 hover:text-red-500 rounded-xl hover:bg-red-50"
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
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 md:p-6 lg:p-8">
                <router-view />
            </main>
        </div>
    </div>
</template>

<style scoped>
.nav-item {
    @apply flex items-center gap-3 py-2.5 px-3 rounded-xl transition-all duration-200 text-sm font-medium;
}
.nav-active {
    @apply bg-gradient-to-r from-blue-600/90 to-indigo-600/80 text-white shadow-lg shadow-blue-500/20;
}
.nav-inactive {
    @apply text-slate-300 hover:bg-white/10 hover:text-white;
}
.sub-nav-item {
    @apply flex items-center gap-2.5 py-2 px-3 rounded-lg text-sm transition-all duration-150;
}
.section-label {
    @apply text-[11px] font-semibold text-slate-500 uppercase tracking-widest px-3 mt-5 mb-2;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-enter-active {
    transition: all 0.25s ease-out;
}
.slide-leave-active {
    transition: all 0.2s ease-in;
}
.slide-enter-from {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}
.slide-enter-to {
    max-height: 500px;
    opacity: 1;
}
.slide-leave-from {
    max-height: 500px;
    opacity: 1;
}
.slide-leave-to {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}
</style>
