import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

import LoginView from "../views/LoginView.vue";
import DashboardView from "../views/DashboardView.vue";
import DashboardHome from "../views/DashboardHome.vue";
import ProfileView from "../views/ProfileView.vue";
import HomeView from "@/views/HomeView.vue";

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: "/login",
            name: "login",
            component: LoginView,
            meta: { guest: true },
        },
        {
            path: "/forbidden",
            name: "forbidden",
            component: () => import("../views/errors/ForbiddenView.vue"),
        },
        {
            path: "/dashboard",
            component: DashboardView,
            meta: { requiresAuth: true },
            children: [
                {
                    path: "/",
                    name: "home",
                    component: HomeView,
                },
                {
                    path: "/dashboard",
                    name: "dashboard",
                    component: DashboardHome,
                },
                {
                    path: "profile",
                    name: "profile",
                    component: ProfileView,
                },
                // --- User Management (Admin+) ---
                {
                    path: "management/users",
                    name: "user-list",
                    component: () => import("../views/UserListView.vue"),
                    meta: { requiresAdmin: true },
                },
                {
                    path: "management/user/create",
                    name: "create-user",
                    component: () => import("../views/AdminUserCreateView.vue"),
                    meta: { requiresAdmin: true },
                },
                {
                    path: "management/roles",
                    name: "role-list",
                    component: () => import("../views/RoleManagerView.vue"),
                    meta: { requiresSuperAdmin: true },
                },
                // --- Master Data (Admin+) ---
                {
                    path: "master/products",
                    name: "master-product-list",
                    component: () =>
                        import("../views/masterdata/MasterProductListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                {
                    path: "master/brands",
                    name: "brand-list",
                    component: () =>
                        import("../views/masterdata/BrandListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                {
                    path: "master/service-brands",
                    name: "service-brand-list",
                    component: () =>
                        import("../views/masterdata/ServiceBrandListView.vue"),
                    meta: { requiresMasterDataView: true },
                },

                {
                    path: "master/grades",
                    name: "grade-list",
                    component: () =>
                        import("../views/masterdata/GradeListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                {
                    path: "master/units",
                    name: "unit-list",
                    component: () =>
                        import("../views/masterdata/UnitListView.vue"),
                    meta: { requiresSuperAdmin: true },
                },
                {
                    path: "master/sales-reps",
                    name: "sales-rep-list",
                    component: () =>
                        import("../views/masterdata/SalesRepListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                {
                    path: "master/technicians",
                    name: "technician-list",
                    component: () =>
                        import("../views/masterdata/TechnicianListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                {
                    path: "master/suppliers",
                    name: "supplier-list",
                    component: () =>
                        import("../views/masterdata/SupplierListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                {
                    path: "master/taxes",
                    name: "tax-list",
                    component: () =>
                        import("../views/masterdata/TaxListView.vue"),
                    meta: { requiresMasterDataView: true },
                },
                // --- Stok Barang (Admin+) ---
                // {
                //     path: "stock-summary",
                //     name: "stock-summary",
                //     component: () =>
                //         import("../views/purchase/StockSummaryView.vue"),
                //     meta: { requiresStockView: true },
                // },
                {
                    path: "item-lookup",
                    name: "item-lookup",
                    component: () =>
                        import("../views/product/ItemLookupView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "purchases",
                    name: "purchase-list",
                    component: () =>
                        import("../views/purchase/PurchaseListView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "purchase-items",
                    name: "purchase-item-list",
                    component: () =>
                        import("../views/purchase/PurchaseItemListView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "stock-wa",
                    name: "stock-wa",
                    component: () =>
                        import("../views/purchase/StockWaView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "sold-items",
                    name: "sold-item-list",
                    component: () =>
                        import("../views/purchase/SoldItemListView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "purchases/create",
                    name: "purchase-create",
                    component: () =>
                        import("../views/purchase/PurchaseCreateView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "purchases/:id/edit",
                    name: "purchase-edit",
                    component: () =>
                        import("../views/purchase/PurchaseCreateView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "purchases/:id",
                    name: "purchase-detail",
                    component: () =>
                        import("../views/purchase/PurchaseDetailView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "purchases/:id/barcode",
                    name: "purchase-barcode",
                    component: () =>
                        import("../views/purchase/BarcodePrintView.vue"),
                    meta: { requiresStockView: true },
                },
                {
                    path: "pos",
                    name: "pos",
                    component: () => import("../views/sales/PosView.vue"),
                },
                {
                    path: "sales",
                    name: "sales-list",
                    component: () => import("../views/sales/SalesListView.vue"),
                    meta: { requiresSalesView: true },
                },
                {
                    path: "service-transactions",
                    name: "service-transaction-list",
                    component: () => import("../views/sales/SalesListView.vue"),
                    meta: { requiresServiceView: true },
                },
                {
                    path: "pos/:id/invoice",
                    name: "pos-invoice",
                    component: () =>
                        import("../views/sales/InvoicePrintView.vue"),
                },
                {
                    path: "settings",
                    name: "store-settings",
                    component: () =>
                        import("../views/settings/StoreSettingView.vue"),
                    meta: { requiresAdmin: true },
                },

                // --- Servis HP ---
                {
                    path: "services",
                    name: "service-list",
                    component: () =>
                        import("../views/service/ServiceListView.vue"),
                },
                {
                    path: "services/create",
                    name: "service-create",
                    component: () =>
                        import("../views/service/ServiceCreateView.vue"),
                },
                {
                    path: "services/:id",
                    name: "service-detail",
                    component: () =>
                        import("../views/service/ServiceDetailView.vue"),
                },
                {
                    path: "services/:id/print",
                    name: "service-print",
                    component: () =>
                        import("../views/service/PrintServiceView.vue"),
                },
                {
                    path: "services/:id/edit",
                    name: "service-edit",
                    component: () =>
                        import("../views/service/ServiceEditView.vue"),
                },
                {
                    path: "report/sales",
                    name: "report-sales",
                    component: () =>
                        import("../views/report/SalesReportView.vue"),
                    meta: { requiresAdmin: true },
                },
                {
                    path: "report/purchases",
                    name: "report-purchases",
                    component: () =>
                        import("../views/report/PurchaseReportView.vue"),
                    meta: { requiresAdmin: true },
                },
                {
                    path: "report/profit",
                    name: "report-profit",
                    component: () =>
                        import("../views/report/ProfitReportView.vue"),
                    meta: { requiresAdmin: true },
                },
                {
                    path: "rekap/sales-detail",
                    name: "rekap-sales-detail",
                    component: () =>
                        import("../views/rekap/SalesDetailView.vue"),
                    meta: { requiresAdmin: true },
                },
                {
                    path: "rekap/purchases-detail",
                    name: "rekap-purchases-detail",
                    component: () =>
                        import("../views/rekap/PurchasesDetailView.vue"),
                    meta: { requiresAdmin: true },
                },
            ],
        },
    ],
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // On fresh page load: token exists but user not loaded yet — fetch first
    if (authStore.token && !authStore.user) {
        await authStore.fetchUser();
    }

    const isAuthenticated = authStore.isAuthenticated;

    if (to.meta.requiresAuth && !isAuthenticated) {
        return next("/login");
    }

    if (to.meta.guest && isAuthenticated) {
        return next("/");
    }

    if (to.meta.requiresAdmin && !authStore.isAdmin) {
        return next("/forbidden");
    }

    if (
        to.meta.requiresSalesView &&
        !authStore.isAdmin &&
        !authStore.canViewSales
    ) {
        return next("/forbidden");
    }

    if (
        to.meta.requiresServiceView &&
        !authStore.isAdmin &&
        !authStore.canViewServices
    ) {
        return next("/forbidden");
    }

    if (to.meta.requiresStockView && !authStore.canViewPurchases) {
        return next("/forbidden");
    }

    if (to.meta.requiresMasterDataView && !authStore.canViewMasterData) {
        return next("/forbidden");
    }

    if (to.meta.requiresSuperAdmin && !authStore.isSuperAdmin) {
        return next("/forbidden");
    }

    next();
});

// Handle 404 - route not found
router.addRoute({
    path: "/:pathMatch(.*)*",
    name: "not-found",
    component: () => import("../views/errors/NotFoundView.vue"),
});

export default router;
