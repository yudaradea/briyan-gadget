<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "../../api";
import { useRouter, useRoute } from "vue-router";
import QuickAddModal from "../../components/QuickAddModal.vue";
import ConfirmDialog from "../../components/ConfirmDialog.vue";
import CurrencyInput from "../../components/CurrencyInput.vue";
import SearchableSelect from "../../components/SearchableSelect.vue";
import ImageModal from "../../components/ImageModal.vue";
import PurchaseItemRow from "../../components/PurchaseItemRow.vue";
import { useToast } from "../../composables/useToast";
import { storageUrl } from "../../utils/storage";

const router = useRouter();
const route = useRoute();
const toast = useToast();

const isEditMode = computed(() => !!route.params.id);

// Purchase header
const today = new Date();
const localToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;

const form = ref({
    no_invoice: "",
    tanggal: localToday,
    supplier_id: "",
    keterangan: "",
});
const purchaseId = ref(null);
const headerSaved = ref(false);
const savingHeader = ref(false);
const headerError = ref("");
const editingHeader = ref(false);

// Item form
const itemForm = ref({
    master_product_id: null,
    brand_id: "",
    category_id: "",
    grade_id: "",
    unit_id: "",
    imei1: "",
    imei2: "",
    harga_jual: "",
    harga_beli: "",
    qty: 1,
    barcode: "",
    product_id: null,
});

const masterProducts = ref([]);
const filteredBrands = computed(() => brands.value);

const filteredGrades = computed(() => grades.value);

const filteredMasterProducts = computed(() => masterProducts.value);

const selectedMasterProduct = computed(() =>
    masterProducts.value.find((m) => m.id === itemForm.value.master_product_id),
);

const selectedIdentifierType = computed(
    () => selectedMasterProduct.value?.identifier_type || "none",
);

const isSerializedProduct = computed(() =>
    ["imei1", "imei2", "serial"].includes(selectedIdentifierType.value),
);

function onMasterProductChange() {
    const selected = selectedMasterProduct.value;
    if (selected) {
        itemForm.value.brand_id = selected.brand_id || "";
        itemForm.value.category_id = selected.category_id || "";
        itemForm.value.grade_id = selected.grade_id || "";
        itemForm.value.unit_id = selected.unit_id || "";
        itemForm.value.imei1 = "";
        itemForm.value.imei2 = "";
        itemForm.value.barcode = "";
        itemForm.value.qty = 1;
    }
}

function clearSelection() {
    resetItemForm();
}

const itemFoto = ref(null);
const fotoPreview = ref(null);
const addingItem = ref(false);
const itemError = ref("");

// Items list
const items = ref([]);
const purchaseTotal = ref(0);

// Delete item
const showDeleteItem = ref(false);
const deleteItemId = ref(null);
const deletingItem = ref(false);

// Inline edit
const editingItemId = ref(null);
const editForm = ref({});
const isEditNonGadget = computed(() => {
    const cat = categories.value.find(
        (c) => c.id === editForm.value.category_id,
    );
    return (
        cat &&
        (cat.nama.toLowerCase() === "sparepart" ||
            cat.nama.toLowerCase() === "aksesoris" ||
            cat.nama.toLowerCase() === "charger" ||
            cat.nama.toLowerCase() === "casing" ||
            cat.nama.toLowerCase() === "earphone")
    );
});
const editFoto = ref(null);
const editFotoPreview = ref(null);
const savingEdit = ref(false);

// Image modal
const modalSrc = ref(null);
const showModal = ref(false);
function openModal(url) {
    if (!url) return;
    modalSrc.value = url;
    showModal.value = true;
}

// Dropdowns
const suppliers = ref([]);
const brands = ref([]);
const categories = ref([]);
const grades = ref([]);
const units = ref([]);

// Quick add modals
const showQuickSupplier = ref(false);
const showQuickBrand = ref(false);
const showQuickCategory = ref(false);
const showQuickGrade = ref(false);
const showQuickMasterProduct = ref(false);
const creatingMasterProduct = ref(false);
const quickMasterError = ref("");
const quickMasterForm = ref({
    nama: "",
    brand_id: "",
    keterangan: "",
});

function formatCurrency(val) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(val || 0);
}

async function loadDropdowns() {
    const [s, b, c, g, u, m] = await Promise.all([
        api.get("/suppliers/all"),
        api.get("/brands/all"),
        api.get("/categories/all"),
        api.get("/grades/all"),
        api.get("/units/all"),
        api.get("/master-products/all"),
    ]);
    suppliers.value = s.data.data;
    brands.value = b.data.data;
    categories.value = c.data.data;
    grades.value = g.data.data;
    units.value = u.data.data;
    masterProducts.value = m.data.data;
}

async function generateInvoice() {
    const { data } = await api.get("/purchases/generate-invoice");
    form.value.no_invoice = data.data.no_invoice;
}

async function loadExistingPurchase() {
    try {
        const { data } = await api.get(`/purchases/${route.params.id}`);
        const p = data.data;
        purchaseId.value = p.id;
        form.value = {
            no_invoice: p.no_invoice,
            tanggal: p.tanggal,
            supplier_id: p.supplier?.id || "",
            keterangan: p.keterangan || "",
        };
        items.value = p.items || [];
        purchaseTotal.value = p.total;
        headerSaved.value = true;
    } catch (err) {
        toast.error("Gagal memuat data invoice");
        router.push("/dashboard/purchases");
    }
}

async function saveHeader() {
    savingHeader.value = true;
    headerError.value = "";
    try {
        if (purchaseId.value) {
            await api.put(`/purchases/${purchaseId.value}`, form.value);
            editingHeader.value = false;
            toast.success("Info invoice berhasil diupdate");
        } else {
            const { data } = await api.post("/purchases", form.value);
            purchaseId.value = data.data.id;
            toast.success("Invoice berhasil dibuat, silakan tambah barang");
        }
        headerSaved.value = true;
    } catch (err) {
        headerError.value = err.response?.data?.message || "Gagal menyimpan";
        toast.error(headerError.value);
    } finally {
        savingHeader.value = false;
    }
}

function enableHeaderEdit() {
    editingHeader.value = true;
}
function cancelHeaderEdit() {
    editingHeader.value = false;
    if (isEditMode.value) loadExistingPurchase();
}
const headerDisabled = computed(
    () => headerSaved.value && !editingHeader.value,
);

// Handle foto upload
function onFotoChange(e) {
    const file = e.target.files[0];
    if (file) {
        itemFoto.value = file;
        fotoPreview.value = URL.createObjectURL(file);
    }
}

function clearFoto() {
    itemFoto.value = null;
    fotoPreview.value = null;
}

async function addItem() {
    if (!purchaseId.value) return;
    addingItem.value = true;
    itemError.value = "";
    try {
        const fd = new FormData();
        Object.entries(itemForm.value).forEach(([k, v]) => {
            if (v !== "" && v !== null && v !== undefined) fd.append(k, v);
        });
        if (itemFoto.value) fd.append("foto", itemFoto.value);

        // PENTING: jangan set Content-Type secara manual saat pakai FormData.
        // Browser harus auto-generate multipart boundary.
        const { data } = await api.post(
            `/purchases/${purchaseId.value}/items`,
            fd,
            { headers: { "Content-Type": undefined } },
        );
        items.value.unshift(data.data.item);
        purchaseTotal.value = data.data.purchase_total;
        resetItemForm();
        toast.success("Barang berhasil ditambahkan");
    } catch (err) {
        itemError.value = err.response?.data?.message || "Gagal menambah item";
        toast.error(itemError.value);
    } finally {
        addingItem.value = false;
    }
}

function resetItemForm() {
    itemForm.value = {
        master_product_id: null,
        brand_id: "",
        category_id: "",
        grade_id: "",
        unit_id: "",
        imei1: "",
        imei2: "",
        harga_jual: "",
        harga_beli: "",
        qty: 1,
        barcode: "",
        product_id: null,
    };
    itemFoto.value = null;
    fotoPreview.value = null;
}

// Inline edit functions
function startEdit(item) {
    editingItemId.value = item.id;
    editForm.value = {
        nama: item.product?.nama || "",
        brand_id: item.product?.brand_id || "",
        category_id: item.product?.category_id || "",
        grade_id: item.product?.grade_id || "",
        unit_id: item.product?.unit_id || "",
        imei1: item.product?.imei1 || "",
        imei2: item.product?.imei2 || "",
        harga_jual: item.product?.harga_jual || 0,
        qty: item.qty || 1,
        barcode: item.product?.barcode || "",
    };
    editFoto.value = null;
    editFotoPreview.value = storageUrl(item.product?.foto);
}

function cancelEdit() {
    editingItemId.value = null;
    editForm.value = {};
    editFoto.value = null;
    editFotoPreview.value = null;
}

function onEditFotoChange(e) {
    const file = e.target.files[0];
    if (file) {
        editFoto.value = file;
        editFotoPreview.value = URL.createObjectURL(file);
    }
}

async function saveEdit(itemId) {
    savingEdit.value = true;
    try {
        const fd = new FormData();
        Object.entries(editForm.value).forEach(([k, v]) => {
            if (v !== "" && v !== null && v !== undefined) fd.append(k, v);
        });
        if (editFoto.value) fd.append("foto", editFoto.value);

        // Use POST with _method for FormData
        // PENTING: jangan set Content-Type secara manual saat pakai FormData.
        fd.append("_method", "PUT");
        const { data } = await api.post(
            `/purchases/${purchaseId.value}/items/${itemId}`,
            fd,
            { headers: { "Content-Type": undefined } },
        );

        // Update the item in the list
        const idx = items.value.findIndex((i) => i.id === itemId);
        if (idx !== -1) items.value[idx] = data.data.item;
        purchaseTotal.value = data.data.purchase_total;

        cancelEdit();
        toast.success("Item berhasil diupdate");
    } catch (err) {
        toast.error(err.response?.data?.message || "Gagal mengupdate item");
    } finally {
        savingEdit.value = false;
    }
}

function confirmRemoveItem(itemId) {
    deleteItemId.value = itemId;
    showDeleteItem.value = true;
}

async function doRemoveItem() {
    deletingItem.value = true;
    try {
        const { data } = await api.delete(
            `/purchases/${purchaseId.value}/items/${deleteItemId.value}`,
        );
        items.value = items.value.filter((i) => i.id !== deleteItemId.value);
        purchaseTotal.value = data.data.purchase_total;
        showDeleteItem.value = false;
        toast.success("Item berhasil dihapus");
    } catch (err) {
        toast.error("Gagal menghapus item");
    } finally {
        deletingItem.value = false;
    }
}

async function quickSupplierCreated(result) {
    await loadDropdowns();
    form.value.supplier_id = result.data.id;
    toast.success("Supplier berhasil ditambahkan");
}
async function quickBrandCreated(result) {
    await loadDropdowns();
    itemForm.value.brand_id = result.data.id;
    quickMasterForm.value.brand_id = result.data.id;
    toast.success("Merk berhasil ditambahkan");
}
async function quickCategoryCreated(result) {
    await loadDropdowns();
    itemForm.value.category_id = result.data.id;
    toast.success("Kategori berhasil ditambahkan");
}

async function quickGradeCreated(result) {
    await loadDropdowns();
    itemForm.value.grade_id = result.data.id;
    toast.success("Grade berhasil ditambahkan");
}

function openQuickMasterProduct() {
    quickMasterError.value = "";
    quickMasterForm.value = {
        nama: "",
        brand_id: "",
        keterangan: "",
    };
    showQuickMasterProduct.value = true;
}

async function submitQuickMasterProduct() {
    if (!quickMasterForm.value.brand_id) {
        quickMasterError.value =
            "Pilih merk sebelum menambahkan produk katalog.";
        return;
    }

    creatingMasterProduct.value = true;
    quickMasterError.value = "";

    try {
        const payload = {
            nama: quickMasterForm.value.nama,
            brand_id: quickMasterForm.value.brand_id,
            keterangan: quickMasterForm.value.keterangan || null,
        };

        const res = await api.post("/master-products", payload);
        await loadDropdowns();
        itemForm.value.master_product_id = res.data.data.id;
        onMasterProductChange();
        showQuickMasterProduct.value = false;
        toast.success("Produk katalog berhasil ditambahkan");
    } catch (err) {
        quickMasterError.value =
            err.response?.data?.message || "Gagal menambahkan produk katalog";
    } finally {
        creatingMasterProduct.value = false;
    }
}

onMounted(() => {
    loadDropdowns();
    if (isEditMode.value) {
        loadExistingPurchase();
    } else {
        generateInvoice();
    }
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <button
                @click="router.push('/dashboard/purchases')"
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
                <h1 class="text-2xl font-bold text-slate-800">
                    {{
                        isEditMode
                            ? "Tambah Stok ke Invoice"
                            : "Tambah Stok Barang"
                    }}
                </h1>
                <p class="text-sm text-slate-400 mt-0.5">
                    {{
                        isEditMode
                            ? `Tambah item ke invoice ${form.no_invoice}`
                            : "Buat invoice baru dan tambah barang"
                    }}
                </p>
            </div>
        </div>

        <!-- Step 1: Invoice Header -->
        <div
            class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-5"
        >
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2.5">
                    <span
                        class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold"
                        :class="
                            headerSaved && !editingHeader
                                ? 'bg-emerald-500 text-white'
                                : 'bg-blue-600 text-white'
                        "
                        >1</span
                    >
                    <h2 class="font-semibold text-slate-800">Info Invoice</h2>
                    <span
                        v-if="headerSaved && !editingHeader"
                        class="text-emerald-500 text-xs font-medium flex items-center gap-1"
                    >
                        <svg
                            class="w-3.5 h-3.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        Tersimpan
                    </span>
                </div>
                <button
                    v-if="headerSaved && !editingHeader"
                    @click="enableHeaderEdit"
                    class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg border border-amber-200 hover:bg-amber-100 transition flex items-center gap-1.5"
                >
                    <svg
                        class="w-3.5 h-3.5"
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
                    Edit
                </button>
            </div>

            <form
                @submit.prevent="saveHeader"
                :class="headerDisabled ? 'opacity-50 pointer-events-none' : ''"
            >
                <div
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
                >
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >No. Invoice</label
                        >
                        <input
                            v-model="form.no_invoice"
                            type="text"
                            required
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Tanggal</label
                        >
                        <input
                            v-model="form.tanggal"
                            type="date"
                            required
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Supplier *</label
                        >
                        <div class="flex gap-1.5">
                            <select
                                v-model="form.supplier_id"
                                required
                                class="flex-1 px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                            >
                                <option value="">Pilih Supplier</option>
                                <option
                                    v-for="s in suppliers"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.nama }}
                                </option>
                            </select>
                            <button
                                type="button"
                                @click="showQuickSupplier = true"
                                class="px-2.5 py-2.5 bg-slate-100 hover:bg-slate-200 rounded-xl transition"
                                title="Tambah cepat"
                            >
                                <svg
                                    class="w-4 h-4 text-slate-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Keterangan</label
                        >
                        <input
                            v-model="form.keterangan"
                            type="text"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                            placeholder="Opsional"
                        />
                    </div>
                </div>
                <p v-if="headerError" class="text-red-500 text-sm mt-3">
                    {{ headerError }}
                </p>
                <div
                    v-if="!headerSaved || editingHeader"
                    class="flex gap-2 mt-5"
                >
                    <button
                        type="submit"
                        :disabled="savingHeader"
                        class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold rounded-xl hover:shadow-lg hover:shadow-blue-500/25 disabled:opacity-50 transition-all"
                    >
                        {{
                            savingHeader
                                ? "Menyimpan..."
                                : editingHeader
                                  ? "Update Info"
                                  : "Simpan & Lanjut"
                        }}
                    </button>
                    <button
                        v-if="editingHeader"
                        type="button"
                        @click="cancelHeaderEdit"
                        class="px-4 py-2.5 text-slate-500 text-sm border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- Step 2: Add Items -->
        <div
            v-if="headerSaved"
            class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-5"
        >
            <div class="flex items-center gap-2.5 mb-5">
                <span
                    class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center text-xs font-bold"
                    >2</span
                >
                <h2 class="font-semibold text-slate-800">Tambah Barang</h2>
            </div>

            <form @submit.prevent="addItem">
                <div
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
                >
                    <!-- Pencarian dihapus sesuai request, langsung ke input detail -->

                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Nama Produk (Katalog) *</label
                        >
                        <SearchableSelect
                            v-model="itemForm.master_product_id"
                            :options="filteredMasterProducts"
                            placeholder="Pilih Produk"
                            :required="true"
                            @change="onMasterProductChange"
                        />
                        <button
                            type="button"
                            @click="openQuickMasterProduct"
                            class="mt-1.5 text-xs text-blue-600 hover:text-blue-700 disabled:text-slate-400 disabled:cursor-not-allowed"
                        >
                            + Tambah produk katalog baru
                        </button>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Merk</label
                        >
                        <select
                            v-model="itemForm.brand_id"
                            disabled
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-100 opacity-75 transition"
                        >
                            <option value="">Pilih Merk</option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.nama }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Satuan *</label
                        >
                        <select
                            v-model="itemForm.unit_id"
                            required
                            class="w-full px-3.5 py-2.5 border cursor-pointer border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                        >
                            <option value="">Pilih Satuan</option>
                            <option
                                v-for="u in units"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.nama }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Grade</label
                        >
                        <select
                            v-model="itemForm.grade_id"
                            class="w-full px-3.5 cursor-pointer py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                        >
                            <option value="">Pilih Grade</option>
                            <option
                                v-for="g in grades"
                                :key="g.id"
                                :value="g.id"
                            >
                                {{ g.nama }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >IMEI 1 (opsional)</label
                        >
                        <input
                            v-model="itemForm.imei1"
                            type="text"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition font-mono"
                            placeholder="15 digit"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >IMEI 2 (opsional)</label
                        >
                        <input
                            v-model="itemForm.imei2"
                            type="text"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition font-mono"
                            placeholder="15 digit"
                        />
                    </div>
                    <!-- <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Kuantitas (Qty) *</label
                        >
                        <input
                            v-model.number="itemForm.qty"
                            type="number"
                            min="1"
                            required
                            :readonly="isSerializedProduct"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition"
                            :class="{
                                'opacity-60 cursor-not-allowed bg-slate-100':
                                    isSerializedProduct,
                            }"
                            placeholder="Jumlah barang"
                        />
                    </div> -->
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Harga Beli *</label
                        >
                        <CurrencyInput
                            v-model="itemForm.harga_beli"
                            :required="true"
                            :allow-thousands="true"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Harga Jual</label
                        >
                        <CurrencyInput
                            v-model="itemForm.harga_jual"
                            :allow-thousands="true"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1.5"
                            >Foto Barang
                            <span class="text-slate-300"
                                >(opsional)</span
                            ></label
                        >
                        <div class="flex items-center gap-3">
                            <label
                                class="flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 rounded-xl cursor-pointer transition text-sm text-slate-600"
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
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                {{ itemFoto ? "Ganti Foto" : "Pilih Foto" }}
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="onFotoChange"
                                />
                            </label>
                            <div
                                v-if="fotoPreview"
                                class="flex items-center gap-2"
                            >
                                <img
                                    :src="fotoPreview"
                                    class="w-10 h-10 rounded-lg object-cover border border-slate-200"
                                />
                                <button
                                    type="button"
                                    @click="clearFoto"
                                    class="text-red-400 hover:text-red-600 text-xs"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="itemError" class="text-red-500 text-sm mt-3">
                    {{ itemError }}
                </p>
                <button
                    type="submit"
                    :disabled="addingItem"
                    class="mt-4 px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-green-600 text-white text-sm font-semibold rounded-xl hover:shadow-lg hover:shadow-emerald-500/25 disabled:opacity-50 transition-all flex items-center gap-2"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ addingItem ? "Menambahkan..." : "Tambah ke Invoice" }}
                </button>
            </form>
        </div>

        <!-- Items Table with Inline Edit -->
        <div
            v-if="headerSaved && items.length > 0"
            class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5"
        >
            <div
                class="px-6 py-4 bg-gradient-to-r from-slate-50 to-white flex justify-between items-center border-b border-slate-100"
            >
                <h3 class="font-semibold text-slate-800">
                    Daftar Barang
                    <span class="text-slate-400 font-normal"
                        >({{ items.length }} item)</span
                    >
                </h3>
                <span class="text-lg font-bold text-emerald-600"
                    >Total: {{ formatCurrency(purchaseTotal) }}</span
                >
            </div>

            <!-- Items as Cards for better inline edit UX -->
            <div class="divide-y divide-slate-100">
                <div
                    v-for="(item, idx) in items"
                    :key="item.id"
                    :class="
                        editingItemId === item.id ? 'p-4 bg-blue-50/10' : ''
                    "
                >
                    <!-- View Mode -->
                    <PurchaseItemRow
                        v-if="editingItemId !== item.id"
                        :item="item"
                        :index="idx + 1"
                        :show-actions="true"
                        @edit="startEdit"
                        @remove="confirmRemoveItem"
                        @image-click="openModal"
                    />

                    <!-- Edit Mode -->
                    <div
                        v-else
                        class="bg-blue-50/50 p-4 rounded-xl border border-blue-200"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <span
                                class="text-xs font-semibold text-blue-600 uppercase tracking-wider"
                                >Mode Edit — {{ item.product?.barcode }}</span
                            >
                            <div class="flex gap-1.5">
                                <button
                                    @click="saveEdit(item.id)"
                                    :disabled="savingEdit"
                                    class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition flex items-center gap-1"
                                >
                                    <svg
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    {{ savingEdit ? "Saving..." : "Simpan" }}
                                </button>
                                <button
                                    @click="cancelEdit"
                                    class="px-3 py-1.5 text-slate-500 text-xs border border-slate-300 rounded-lg hover:bg-white transition"
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3"
                        >
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Nama Produk</label
                                >
                                <input
                                    v-model="editForm.nama"
                                    type="text"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 transition"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Merk</label
                                >
                                <select
                                    v-model="editForm.brand_id"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 transition"
                                >
                                    <option value="">Pilih Merk</option>
                                    <option
                                        v-for="b in brands"
                                        :key="b.id"
                                        :value="b.id"
                                    >
                                        {{ b.nama }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Grade</label
                                >
                                <select
                                    v-model="editForm.grade_id"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 transition"
                                >
                                    <option value="">Pilih Grade</option>
                                    <option
                                        v-for="g in grades"
                                        :key="g.id"
                                        :value="g.id"
                                    >
                                        {{ g.nama }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Satuan</label
                                >
                                <select
                                    v-model="editForm.unit_id"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 transition"
                                >
                                    <option value="">Pilih Satuan</option>
                                    <option
                                        v-for="u in units"
                                        :key="u.id"
                                        :value="u.id"
                                    >
                                        {{ u.nama }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >IMEI 1</label
                                >
                                <input
                                    v-model="editForm.imei1"
                                    type="text"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white font-mono focus:ring-2 focus:ring-blue-500 transition"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >IMEI 2</label
                                >
                                <input
                                    v-model="editForm.imei2"
                                    type="text"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white font-mono focus:ring-2 focus:ring-blue-500 transition"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Qty *</label
                                >
                                <input
                                    v-model.number="editForm.qty"
                                    type="number"
                                    min="1"
                                    required
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 transition"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Harga Beli</label
                                >
                                <CurrencyInput
                                    v-model="editForm.harga_beli"
                                    :allow-thousands="true"
                                    class="!rounded-xl"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-medium text-slate-500 mb-1"
                                    >Foto</label
                                >
                                <div class="flex items-center gap-2">
                                    <label
                                        class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg cursor-pointer transition text-xs text-slate-600"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        Pilih
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @change="onEditFotoChange"
                                        />
                                    </label>
                                    <img
                                        v-if="editFotoPreview"
                                        :src="editFotoPreview"
                                        class="w-8 h-8 rounded object-cover border"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Actions -->
        <div v-if="headerSaved && items.length > 0" class="flex gap-3">
            <router-link
                :to="`/dashboard/purchases/${purchaseId}`"
                class="px-5 py-2.5 bg-white text-slate-700 text-sm font-medium rounded-xl border border-slate-200 hover:bg-slate-50 transition flex items-center gap-2"
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
                Lihat Invoice
            </router-link>
            <router-link
                :to="`/dashboard/purchases/${purchaseId}/barcode`"
                class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white text-sm font-semibold rounded-xl hover:shadow-lg hover:shadow-purple-500/25 transition-all flex items-center gap-2"
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
                    />
                </svg>
                Cetak Barcode
            </router-link>
        </div>

        <!-- Quick Add Modals -->
        <QuickAddModal
            :show="showQuickSupplier"
            title="Tambah Supplier Cepat"
            :submit-function="
                (d) => api.post('/suppliers/quick', d).then((r) => r.data)
            "
            @created="quickSupplierCreated"
            @close="showQuickSupplier = false"
        />
        <QuickAddModal
            :show="showQuickBrand"
            title="Tambah Merk Cepat"
            :submit-function="
                (d) => api.post('/brands/quick', d).then((r) => r.data)
            "
            @created="quickBrandCreated"
            @close="showQuickBrand = false"
        />
        <QuickAddModal
            :show="showQuickCategory"
            title="Tambah Kategori Cepat"
            :submit-function="
                (d) => api.post('/categories/quick', d).then((r) => r.data)
            "
            @created="quickCategoryCreated"
            @close="showQuickCategory = false"
        />
        <QuickAddModal
            :show="showQuickGrade"
            title="Tambah Grade Cepat"
            :submit-function="
                (d) => api.post('/grades/quick', d).then((r) => r.data)
            "
            @created="quickGradeCreated"
            @close="showQuickGrade = false"
        />

        <Teleport to="body">
            <div
                v-if="showQuickMasterProduct"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="showQuickMasterProduct = false"
            >
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 z-10"
                >
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">
                        Tambah Produk Katalog
                    </h3>
                    <form @submit.prevent="submitQuickMasterProduct">
                        <div class="space-y-3">
                            <div>
                                <label
                                    class="block text-sm font-medium text-slate-600 mb-1"
                                    >Nama Produk *</label
                                >
                                <input
                                    v-model="quickMasterForm.nama"
                                    type="text"
                                    required
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-slate-600 mb-1"
                                    >Merk *</label
                                >
                                <div class="flex gap-1.5">
                                    <select
                                        v-model="quickMasterForm.brand_id"
                                        required
                                        class="flex-1 px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">Pilih Merk</option>
                                        <option
                                            v-for="b in brands"
                                            :key="b.id"
                                            :value="b.id"
                                        >
                                            {{ b.nama }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="showQuickBrand = true"
                                        class="px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg transition"
                                        title="Tambah cepat"
                                    >
                                        <svg
                                            class="w-4 h-4 text-slate-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 4v16m8-8H4"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-slate-600 mb-1"
                                    >Keterangan</label
                                >
                                <textarea
                                    v-model="quickMasterForm.keterangan"
                                    rows="2"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                        </div>
                        <p
                            v-if="quickMasterError"
                            class="text-red-500 text-sm mt-2"
                        >
                            {{ quickMasterError }}
                        </p>
                        <div class="flex gap-2 mt-4">
                            <button
                                type="button"
                                @click="showQuickMasterProduct = false"
                                class="flex-1 px-4 py-2 text-sm bg-slate-100 rounded-lg hover:bg-slate-200"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="creatingMasterProduct"
                                class="flex-1 px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{
                                    creatingMasterProduct
                                        ? "Menyimpan..."
                                        : "Simpan Produk"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ConfirmDialog
            :show="showDeleteItem"
            :loading="deletingItem"
            @confirm="doRemoveItem"
            @cancel="showDeleteItem = false"
        />

        <!-- Image Modal -->
        <ImageModal
            :show="showModal"
            :src="modalSrc"
            @close="showModal = false"
        />
    </div>
</template>
