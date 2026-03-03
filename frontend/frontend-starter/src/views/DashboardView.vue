<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useAuthStore } from "../stores/auth";
import { useRouter } from "vue-router";
import { useToast } from "../composables/useToast";
import api from "../api";

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();

const masterDataOpen = ref(false);
const userMgmtOpen = ref(false);
const profileDropdownOpen = ref(false);
const mobileMenuOpen = ref(false);

const storeProfile = ref({
    name: "App Kasir",
    logo_url: null,
});

const handleLogout = async () => {
    await authStore.logout();
    toast.success("Berhasil logout");
    router.push("/login");
};

const userInitial = computed(() => {
    return authStore.user?.name?.charAt(0)?.toUpperCase() || "U";
});

const photoUrl = computed(() => {
    return authStore.user?.profile?.avatar_url;
});

const roleBadge = computed(() => {
    if (authStore.isSuperAdmin)
        return {
            label: "Super Admin",
            class: "bg-purple-100 text-purple-700 border-purple-200",
        };
    if (authStore.isAdmin)
        return {
            label: "Admin",
            class: "bg-blue-100 text-blue-700 border-blue-200",
        };
    return {
        label: "Kasir",
        class: "bg-green-100 text-green-700 border-green-200",
    };
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
        // Keep fallback
    }
}

// Close dropdowns on outside click
const closeDropdowns = (e) => {
    if (
        !e.target.closest(".dropdown-trigger") &&
        !e.target.closest(".nav-dropdown")
    ) {
        masterDataOpen.value = false;
        userMgmtOpen.value = false;
        profileDropdownOpen.value = false;
    }
};

onMounted(() => {
    fetchStoreProfile();
    window.addEventListener("click", closeDropdowns);
});

onUnmounted(() => {
    window.removeEventListener("click", closeDropdowns);
});
</script>

<template>
    <div class="flex flex-col min-h-screen bg-slate-50">
        <!-- Top Navigation Bar -->
        <header
            class="sticky top-0 z-50 bg-white border-b shadow-sm border-slate-200 backdrop-blur-md bg-white/90"
        >
            <div
                class="flex items-center justify-between w-full h-16 gap-4 px-4 mx-auto sm:px-6 lg:px-8 xl:px-10 2xl:px-12"
            >
                <!-- Left: Logo -->
                <router-link
                    to="/dashboard"
                    class="flex items-center gap-3 shrink-0"
                >
                    <div
                        class="flex items-center justify-center shadow-lg w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 shadow-blue-500/20"
                    >
                        <img
                            v-if="storeProfile.logo_url"
                            :src="storeProfile.logo_url"
                            alt="Logo"
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
                                stroke-width="2.5"
                                d="M13 10V3L4 14h7v7l9-11h-7z"
                            />
                        </svg>
                    </div>
                    <span
                        class="hidden text-xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-slate-900 to-slate-700 sm:block"
                    >
                        {{ storeProfile.name }}
                    </span>
                </router-link>

                <!-- Center: Desktop Menu -->
                <nav
                    class="items-center justify-center flex-1 hidden max-w-4xl gap-1 lg:flex"
                >
                    <!-- Dashboard -->
                    <router-link
                        to="/dashboard"
                        class="nav-link"
                        :class="{
                            'nav-link-active': $route.name === 'dashboard',
                        }"
                    >
                        Dashboard
                    </router-link>

                    <!-- Gudang & Stok Dropdown (if admin) -->
                    <div
                        v-if="authStore.isSuperAdmin || authStore.isAdmin"
                        class="relative group"
                    >
                        <button
                            class="nav-link flex items-center gap-1.5"
                            :class="{
                                'nav-link-active':
                                    $route.path.includes(
                                        '/dashboard/purchases'
                                    ) ||
                                    $route.path.includes('/dashboard/stock'),
                            }"
                        >
                            Stok Barang
                            <svg
                                class="w-3.5 h-3.5 opacity-60"
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
                        <div class="dropdown-menu">
                            <router-link
                                to="/dashboard/purchases"
                                class="dropdown-item"
                                >Pembelian (Invoice)</router-link
                            >
                            <router-link
                                to="/dashboard/stock-summary"
                                class="dropdown-item"
                                >Stok Barang</router-link
                            >
                            <router-link
                                to="/dashboard/purchase-items"
                                class="dropdown-item"
                                >Mutasi Stok</router-link
                            >
                        </div>
                    </div>

                    <!-- Penjualan Dropdown -->
                    <div class="relative group">
                        <button
                            class="nav-link flex items-center gap-1.5"
                            :class="{
                                'nav-link-active':
                                    $route.path.includes('/pos') ||
                                    $route.path.includes('dashboard/sales'),
                            }"
                        >
                            Transaksi Penjualan
                            <svg
                                class="w-3.5 h-3.5 opacity-60"
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
                        <div class="dropdown-menu">
                            <router-link
                                to="/dashboard/pos"
                                class="flex items-center gap-2 dropdown-item"
                            >
                                Input Penjualan
                            </router-link>
                            <router-link
                                to="/dashboard/sales"
                                class="dropdown-item"
                                >Riwayat Transaksi
                            </router-link>
                        </div>
                    </div>

                    <!-- Servis HP -->
                    <!-- <div class="relative group">
                        <button
                            class="nav-link flex items-center gap-1.5"
                            :class="{
                                'nav-link-active':
                                    $route.path.includes('/services'),
                            }"
                        >
                            Servis HP
                            <svg
                                class="w-3.5 h-3.5 opacity-60"
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
                        <div class="dropdown-menu">
                            <router-link
                                to="/dashboard/services"
                                class="dropdown-item"
                                >Data Servis Masuk</router-link
                            >
                            <router-link
                                to="/dashboard/service-transactions"
                                class="dropdown-item"
                                >Transaksi Servis</router-link
                            >
                        </div>
                    </div> -->

                    <!-- Laporan (if authorized) -->
                    <div
                        v-if="
                            authStore.isSuperAdmin ||
                            authStore.isAdmin ||
                            authStore.canViewReports
                        "
                        class="relative group"
                    >
                        <button
                            class="nav-link flex items-center gap-1.5"
                            :class="{
                                'nav-link-active':
                                    $route.path.includes('/report'),
                            }"
                        >
                            Laporan
                            <svg
                                class="w-3.5 h-3.5 opacity-60"
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
                        <div class="dropdown-menu">
                            <router-link
                                to="/dashboard/report/sales"
                                class="dropdown-item"
                                >Laporan Penjualan</router-link
                            >
                            <router-link
                                to="/dashboard/report/purchases"
                                class="dropdown-item"
                                >Laporan Pembelian</router-link
                            >
                            <router-link
                                to="/dashboard/report/profit"
                                class="dropdown-item"
                                >Laba Rugi</router-link
                            >
                        </div>
                    </div>

                    <!-- Master Data Dropdown (if admin) -->
                    <div
                        v-if="authStore.isSuperAdmin || authStore.isAdmin"
                        class="relative group"
                    >
                        <button
                            class="nav-link flex items-center gap-1.5"
                            :class="{
                                'nav-link-active':
                                    $route.path.includes('/master'),
                            }"
                        >
                            Master
                            <svg
                                class="w-3.5 h-3.5 opacity-60"
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
                        <div class="dropdown-menu grid grid-cols-2 w-[400px]">
                            <router-link
                                to="/dashboard/master/products"
                                class="dropdown-item"
                                >Katalog Produk</router-link
                            >
                            <router-link
                                to="/dashboard/master/brands"
                                class="dropdown-item"
                                >Merk</router-link
                            >
                            <!-- <router-link
                                to="/dashboard/master/service-brands"
                                class="dropdown-item"
                                >Merk HP Servis</router-link
                            > -->

                            <router-link
                                to="/dashboard/master/grades"
                                class="dropdown-item"
                                >Grade</router-link
                            >
                            <router-link
                                to="/dashboard/master/units"
                                class="dropdown-item"
                                >Satuan</router-link
                            >
                            <!-- <router-link
                                to="/dashboard/master/sales-reps"
                                class="dropdown-item"
                                >Sales</router-link
                            > -->
                            <!-- <router-link
                                to="/dashboard/master/technicians"
                                class="dropdown-item"
                                >Teknisi</router-link
                            > -->
                            <router-link
                                to="/dashboard/master/suppliers"
                                class="dropdown-item"
                                >Supplier</router-link
                            >
                            <router-link
                                to="/dashboard/master/taxes"
                                class="dropdown-item"
                                >Pajak</router-link
                            >
                        </div>
                    </div>
                    <!-- user management -->
                    <div
                        v-if="authStore.isSuperAdmin || authStore.isAdmin"
                        class="relative group"
                    >
                        <button
                            class="nav-link flex items-center gap-1.5"
                            :class="{
                                'nav-link-active':
                                    $route.path.includes('/management'),
                            }"
                        >
                            User Management
                            <svg
                                class="w-3.5 h-3.5 opacity-60"
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
                        <div class="dropdown-menu grid grid-cols-2 w-[400px]">
                            <router-link
                                to="/dashboard/management/users"
                                class="dropdown-item"
                                >User List</router-link
                            >
                            <router-link
                                to="/dashboard/management/user/create"
                                class="dropdown-item"
                                >Tambah User</router-link
                            >
                            <router-link
                                to="/dashboard/management/roles"
                                class="dropdown-item"
                                >Role</router-link
                            >
                        </div>
                    </div>
                </nav>

                <!-- Right: User Profile & Dropdown -->
                <div class="flex items-center gap-2">
                    <!-- Mobile Menu Toggle -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="p-2 lg:hidden text-slate-500 hover:bg-slate-100 rounded-xl"
                    >
                        <svg
                            class="w-6 h-6"
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

                    <div class="relative dropdown-trigger">
                        <button
                            @click="profileDropdownOpen = !profileDropdownOpen"
                            class="flex items-center gap-2 p-1.5 hover:bg-slate-100 rounded-2xl transition-all duration-300"
                        >
                            <div
                                class="overflow-hidden border-2 border-white shadow-inner w-9 h-9 rounded-xl ring-1 ring-slate-200"
                            >
                                <img
                                    v-if="photoUrl"
                                    :src="photoUrl"
                                    class="object-cover w-full h-full"
                                />
                                <div
                                    v-else
                                    class="flex items-center justify-center w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600"
                                >
                                    <span class="font-bold text-white">{{
                                        userInitial
                                    }}</span>
                                </div>
                            </div>
                            <div class="hidden mr-1 text-left md:block">
                                <p
                                    class="text-xs font-bold leading-none text-slate-800"
                                >
                                    {{ authStore.user?.name }}
                                </p>
                                <span
                                    class="text-[10px] text-slate-500 font-medium"
                                    >{{ roleBadge.label }}</span
                                >
                            </div>
                            <svg
                                class="hidden w-4 h-4 text-slate-400 md:block"
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

                        <!-- Profile Dropdown -->
                        <div
                            v-show="profileDropdownOpen"
                            class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-[60] animate-in-fade-slide overflow-hidden font-medium"
                        >
                            <div
                                class="px-4 py-3 mb-1 border-b border-slate-100 lg:hidden"
                            >
                                <p class="text-sm font-bold text-slate-800">
                                    {{ authStore.user?.name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ roleBadge.label }}
                                </p>
                            </div>
                            <router-link
                                to="/dashboard/profile"
                                class="dropdown-item flex items-center gap-3 py-2.5"
                            >
                                <svg
                                    class="w-4 h-4 text-slate-400 group-hover:text-blue-500"
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
                                Edit Profile
                            </router-link>
                            <router-link
                                v-if="
                                    authStore.isAdmin || authStore.isSuperAdmin
                                "
                                to="/dashboard/settings"
                                class="dropdown-item flex items-center gap-3 py-2.5"
                            >
                                <svg
                                    class="w-4 h-4 text-slate-400 group-hover:text-blue-500"
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
                                </svg>
                                Pengaturan Toko
                            </router-link>
                            <div class="h-px mx-2 my-1 bg-slate-100"></div>
                            <button
                                @click="handleLogout"
                                class="w-full text-left dropdown-item flex items-center gap-3 py-2.5 text-rose-600 hover:bg-rose-50"
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
                                Keluar Aplikasi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Navigation Drawer -->
        <transition name="mobile-menu">
            <div
                v-show="mobileMenuOpen"
                class="fixed inset-0 z-[100] lg:hidden"
            >
                <div
                    @click="mobileMenuOpen = false"
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
                ></div>
                <div
                    class="fixed top-0 bottom-0 left-0 w-[80%] max-w-sm bg-white shadow-2xl flex flex-col p-6 overflow-y-auto"
                >
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex items-center justify-center w-8 h-8 bg-blue-600 rounded-lg"
                            >
                                <svg
                                    class="w-4 h-4 text-white"
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
                            <span class="font-bold text-slate-800"
                                >Menu Navigasi</span
                            >
                        </div>
                        <button
                            @click="mobileMenuOpen = false"
                            class="p-2 rounded-lg bg-slate-100"
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
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-6">
                        <router-link
                            @click="mobileMenuOpen = false"
                            to="/dashboard"
                            class="mobile-link"
                            >Dashboard</router-link
                        >

                        <div>
                            <p class="section-badge">Manajemen Stok</p>
                            <div class="mt-2 space-y-1">
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/purchases"
                                    class="mobile-sublink"
                                    >Pembelian (Invoice)</router-link
                                >
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/stock-summary"
                                    class="mobile-sublink"
                                    >Stok Barang</router-link
                                >
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/purchase-items"
                                    class="mobile-sublink"
                                    >Mutasi Stok</router-link
                                >
                            </div>
                        </div>

                        <div>
                            <p class="section-badge">Penjualan & Kasir</p>
                            <div class="mt-2 space-y-1">
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/pos"
                                    class="font-bold text-blue-600 mobile-sublink"
                                    >POS Kasir</router-link
                                >
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/sales"
                                    class="mobile-sublink"
                                    >Riwayat Transaksi</router-link
                                >
                            </div>
                        </div>

                        <!-- <div>
                            <p class="section-badge">Servis HP</p>
                            <div class="mt-2 space-y-1">
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/services"
                                    class="mobile-sublink"
                                    >Servis Masuk</router-link
                                >
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/service-transactions"
                                    class="mobile-sublink"
                                    >Transaksi Servis</router-link
                                >
                            </div>
                        </div> -->

                        <div v-if="authStore.isAdmin || authStore.isSuperAdmin">
                            <p class="section-badge">Master Data</p>
                            <div class="grid grid-cols-1 mt-2 space-y-1">
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/master/products"
                                    class="mobile-sublink"
                                    >Katalog Produk</router-link
                                >
                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/master/brands"
                                    class="mobile-sublink"
                                    >Merk</router-link
                                >

                                <router-link
                                    @click="mobileMenuOpen = false"
                                    to="/dashboard/users"
                                    class="mobile-sublink"
                                    >User Management</router-link
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Main Page Content -->
        <main
            class="flex-1 w-full px-4 py-4 mx-auto sm:px-6 lg:px-8 xl:px-10 2xl:px-12 md:py-6 lg:py-8 animate-in-fade"
        >
            <router-view v-slot="{ Component }">
                <transition name="page" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>

        <!-- Footer -->
        <footer class="px-6 py-4 mt-auto text-center border-t border-slate-200">
            <p
                class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"
            >
                &copy; 2026 {{ storeProfile.name }}. Developed with &hearts;
            </p>
        </footer>
    </div>
</template>

<style scoped>
.nav-link {
    @apply px-4 py-2 text-sm font-semibold text-slate-600 rounded-xl transition-all duration-300 hover:text-blue-600 hover:bg-blue-50/50 relative whitespace-nowrap;
}
.nav-link-active {
    @apply text-blue-700 bg-blue-50 shadow-sm shadow-blue-500/5 ring-1 ring-blue-100/50;
}
.dropdown-menu {
    @apply absolute top-full left-0 mt-0 hidden group-hover:block bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-[60] animate-in-fade-slide min-w-[220px];
}
.group:hover .dropdown-menu,
.dropdown-menu:hover {
    @apply block;
}
.dropdown-item {
    @apply px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-blue-700 hover:bg-blue-50/70 transition-all duration-150 flex items-center;
}
.mobile-link {
    @apply block text-lg font-bold text-slate-800 hover:text-blue-600 transition-colors;
}
.mobile-sublink {
    @apply block py-1.5 text-sm font-medium text-slate-600 hover:text-blue-600;
}
.section-badge {
    @apply text-[10px] font-black text-slate-400 uppercase tracking-[0.2em];
}

/* Animations */
@keyframes fadeInSlide {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-in-fade-slide {
    animation: fadeInSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
.animate-in-fade {
    animation: fadeIn 0.4s ease-out forwards;
}

.page-enter-active,
.page-leave-active {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.page-enter-from {
    opacity: 0;
    transform: translateY(5px);
}
.page-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

.mobile-menu-enter-active,
.mobile-menu-leave-active {
    transition: all 0.3s ease;
}
.mobile-menu-enter-from,
.mobile-menu-leave-to {
    opacity: 0;
}
.mobile-menu-enter-from > div:last-child {
    transform: translateX(-100%);
}
.mobile-menu-enter-to > div:last-child {
    transform: translateX(0);
}
.mobile-menu-leave-to > div:last-child {
    transform: translateX(-100%);
}
</style>
