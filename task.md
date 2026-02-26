# 📋 Roadmap & Task Progress — App Kasir

Status Proyek: **Batch 4 Completed (UX Polished) | Batch 5 Starting**
Terakhir diperbarui: 2026-02-25 19:40

---

## ✅ Batch 1: Fondasi Database & Model (Selesai)

- [x] Migrations (14 tabel inti: brands, products, purchases, sales, dll)
- [x] Models dengan Trait UUID & relasi lengkap
- [x] Role & Permission Seeder (Super Admin, Admin, Kasir)
- [x] Master Data Seeders (Brand, Category, Grade, Satuan, Pajak)

## ✅ Batch 2: Master Data CRUD (Selesai)

- [x] Backend API dengan Repository Pattern (7 Modul)
- [x] Frontend UI Master Data (Brand, Category, Grade, Unit, SalesRep, Supplier, Tax)
- [x] Komponen Reusable (DataTable, ConfirmDialog, QuickAddModal)

## ✅ Batch 3: Stok Barang (Pembelian) & Barcode (Selesai)

- [x] Header & Item Purchase CRUD
- [x] Barcode Automation (BG-YYYYMMDD-XXXXX)
- [x] Print Barcode A4 Layout
- [x] **Bug Fixes & Refinement**:
    - [x] Fix Image Upload (Multipart Boundary Issue)
    - [x] Reusable Image Modal (Lightbox)
    - [x] Form Request Validation (Purchase & Items)
    - [x] Timezone Date fix (Default hari ini)
    - [x] Column Alignment (Qty & Harga)

## ✅ Batch 4: Transaksi Penjualan (POS) & Management (Selesai)

- [x] Backend: SalesTransaction Repository & Controller (CRUD & Scopes)
- [x] Backend: Advanced Filtering (By Date, Cashier, Salesperson)
- [x] Backend: Real-time Stats API
- [x] Frontend: POS View (Cart system, Tax, Discount)
- [x] Frontend: Multi-Role POS (Admin can select Cashier, Kasir is locked)
- [x] Frontend: Sales List with Advanced Filter & Real-time Stats Update
- [x] Frontend: Print Nota Penjualan (Thermal & Standard)
- [x] UI: End-Date Validation (Min-date attribute & Watcher)
- [x] **UX Polish & Stability (Final Touch)**:
    - [x] Standardize "AKSI" labels & Right-alignment
    - [x] Fix Dashboard Sidebar layout (Sticky/Width)
    - [x] Refine POS UI (Compact Search & Cart)
    - [x] Fix Sold Items "Tanggal Terjual" & Add "No Transaksi Keluar"
    - [x] Fix Invoice Print View `router` error

## 🧾 Batch 5: Role & User Management Refinement (Selesai)

- [x] Implement **Owner** role (Permissions sync with Admin)
- [x] Filter User Management to use relevant roles (Super Admin, Owner, Admin, Kasir)
- [x] Secure System Roles from deletion (Super Admin, Admin, Owner, Kasir)
- [x] Dynamic Cashier filtering in POS & Sales List (Only show 'kasir' role)

## ⚒️ Batch 5: Service HP (Repair Order System)

- [ ] Backend: ServiceOrders Migration & Model
- [ ] Backend: ServiceStatus management (Waiting, Repairing, Completed, Cancelled)
- [ ] Backend: Sparepart tracking in Service
- [ ] Frontend: Service Order Form (Scanner integration for fast lookup)
- [ ] Frontend: Service List with Status Tracking & Filtering
- [ ] Frontend: Service Invoice Print

## ⏳ Batch 6-7: Future Tasks

- [ ] **Batch 6: Laporan & Dashboard** (Sales report, Profit/Loss, Charts)
- [ ] **Batch 7: Maintenance & Polish** (Final testing, Performance optimization)

---

_Gunakan file ini sebagai acuan utama progres pengerjaan kita._
