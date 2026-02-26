# 🗺️ Perjalanan Pengembangan — App Kasir

Halaman ini mencatat "perjalanan" pengembangan aplikasi ini dari awal hingga saat ini.

---

## 🏁 Destinasi Akhir

Membangun aplikasi POS (Point of Sale) modern untuk toko ponsel menggunakan **Laravel 12 + Vue.js 3**.

---

## 🛤️ Log Perjalanan (Batch demi Batch)

### 📦 Batch 1: Fondasi Bangunan

Membangun struktur data inti.

- **Outcome**: 14 tabel database siap pakai.
- **Teknologi**: UUID as Primary Key untuk keamanan data.
- **Pencapaian**: Sistem role & permission (Super Admin, Admin, Kasir) aktif.

### 💎 Batch 2: Master Data (The Core Parts)

Membuat input data dasar seperti Merk, Kategori, Supplier, dll.

- **Outcome**: 7 modul CRUD selesai (Backend & Frontend).
- **Inovasi**: Komponen `DataTable.vue` reusable yang bisa search & filter di semua halaman.
- **Fitur Cepat**: Lahirnya `QuickAddModal.vue` agar bisa tambah data supplier/merk sambil mengisi form lain.

### 🧾 Batch 3: Stok Barang & Barcode (The Heart of Inventory)

Menangani input stok barang masuk dan penomeran otomatis.

- **Outcome**: Sistem invoice pembelian selesai.
- **Inovasi**: Auto-barcode generator (misal: `BG-20260225-00001`) yang unik untuk setiap barang.
- **Refinement (The Final Touch)**:
    - **Image Modal**: Foto produk sekarang bisa diklik untuk preview (Lightbox).
    - **Fix Upload**: Mengatasi masalah "file tidak masuk database" dengan perbaikan interceptor API.
    - **UI Alignment**: Detail barang sekarang rapi (Qty & Harga sejajar).
    - **Timezone Fix**: Tanggal otomatis menyesuaikan waktu lokal Indonesia.

### 💹 Batch 4: Penjualan (POS) & Kendali User

Transformasi dari pengelolaan stok ke mesin uang (transaksi).

- **Outcome**: Sistem POS yang cerdas dan Dashboard Penjualan yang informatif.

- **Fitur Unggulan**:
    - **Smart POS**: Admin & Owner bisa pilih kasir, sementara Kasir asli terkunci pada akunnya sendiri.
    - **Advanced Filtering**: Cari data penjualan berdasarkan rentang tanggal, kasir, atau sales secara instan.
    - **Real-time Stats**: Statistik penjualan langsung diperbarui saat ada transaksi dihapus tanpa refresh halaman.

- **Role Owner & Kasir**: Pengaturan hak akses yang lebih detail untuk Owner dan Kasir (Pilihan kasir hanya muncul di role tertentu).

- **UX Polish (The Professional Look)**:
    - Re-labeling "Opsi" menjadi "AKSI" dan right-alignment di semua tabel.
    - Perbaikan Sidebar Dashboard agar tidak menabrak konten (Sticky & Width fix).
    - Desain POS yang lebih compact (Search kecil, Cart pendek, Layout 1400px) untuk kecepatan kerja.
    - Sold Items Tracking: Penambahan kolom "No Transaksi Keluar" dan perbaikan "Tanggal Terjual".

- **Stability**: Perbaikan error `router.back()` pada Invoice Print View.
  63:
  64: ### 🏗️ Batch 6: Evolusi Master Produk & Refaktor Pembelian
  65:
  66: Memisahkan identitas barang (Katalog) dengan stok fisik untuk pendataan yang lebih akurat.
  67:
  68: - **Outcome**: Struktur database dua tingkat (Master Product + Produk/Stok).
  69: - **Inovasi**:
  70: - **Cascading Selection**: Input pembelian tidak lagi mencari manual, tapi memilih bertahap (Kategori -> Merk -> Grade -> Nama Produk) sehingga data seragam.
  71: - **Sistem Grade**: Penambahan identitas Grade (New, Like New, Grade A, dll) pada setiap stok barang.
  72: - **Flexible Barcode**: Dukungan penuh untuk Serial Number (SN) manual pada Gadget namun tetap otomatis untuk Sparepart/Aksesoris.
  73: - **UX Polish**:
  74: - Form pembelian lebih cerdas, otomatis menyembunyikan/menampilkan field IMEI berdasarkan kategori barang yang dipilih.
  75: - Integrasi pencarian dari Katalog yang langsung mengisi detail barang secara otomatis.

---

## 🚀 Apa Yang Sedang Kita Kerjakan Sekarang?

### ⚒️ Batch 5: Service HP (Repair Order System)

- [x] Backend: ServiceRepository & Resource
- [x] Backend: ServiceController with Status Management
- [x] Backend: Sparepart tracking with Stock Sync
- [x] Frontend: Service List with Status Tracking & Filtering
- [x] Frontend: Service Detail & Status Update
- [x] Frontend: Sparsepart addition UX
- [x] Frontend: Service Receipt & Invoice Print (Auto-print layout)
- [x] Refaktif: Purchase Input Flow (Katalog Based Selection)
- [ ] Frontend: Service Order Form (Scanner integration for fast lookup)

---

## 📋 Checklist Task Saat Ini

| Task                         | Status         | Note                        |
| ---------------------------- | -------------- | --------------------------- |
| POS System & Cart            | 🟢 Done        | Completed with Tax/Discount |
| Multi-role Cashier Selection | 🟢 Done        | Admin check implemented     |
| Advanced Sales Filter        | 🟢 Done        | Date/User/Sales filters     |
| Real-time Stats Updates      | 🟢 Done        | Auto-refresh totals         |
| Role Owner Implementation    | 🟢 Done        | Permissions synced          |
| Master Product Refactoring   | 🟢 Done        | Katalog vs Stock separation |
| Service Order System         | 🟡 In Progress | Menuju Batch 5              |

---

_Dokumentasi ini akan terus diperbarui seiring berjalannya proyek._
