<script setup>
import { ref, onMounted } from "vue";
import api from "../../api";
import { useToast } from "../../composables/useToast";

const toast = useToast();
const isLoading = ref(false);
const isSaving = ref(false);
const logoPreview = ref(null);
const qrisPreview = ref(null);

const form = ref({
    name: "",
    email: "",
    phone: "",
    address: "",
    footer_text: "",
    logo: null,
    bank_name: "",
    bank_account: "",
    bank_account_name: "",
    signature_name: "",
    service_terms: "",
    qris_image: null,
});

const fetchSettings = async () => {
    isLoading.value = true;
    try {
        const res = await api.get("/store-settings");
        const data = res.data.data;
        form.value.name = data.name || "";
        form.value.email = data.email || "";
        form.value.phone = data.phone || "";
        form.value.address = data.address || "";
        form.value.footer_text = data.footer_text || "";
        form.value.bank_name = data.bank_name || "";
        form.value.bank_account = data.bank_account || "";
        form.value.bank_account_name = data.bank_account_name || "";
        form.value.signature_name = data.signature_name || "";
        form.value.service_terms = data.service_terms || "";
        if (data.logo_url) {
            logoPreview.value = data.logo_url;
        }
        if (data.qris_image_url) {
            qrisPreview.value = data.qris_image_url;
        }
    } catch (e) {
        toast.error("Gagal memuat pengaturan toko");
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchSettings();
});

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.value.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handleQrisChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.value.qris_image = file;
        qrisPreview.value = URL.createObjectURL(file);
    }
};

const submitForm = async () => {
    isSaving.value = true;
    try {
        const formData = new FormData();
        formData.append("name", form.value.name);
        formData.append("email", form.value.email);
        formData.append("phone", form.value.phone);
        formData.append("address", form.value.address);
        formData.append("footer_text", form.value.footer_text);
        formData.append("bank_name", form.value.bank_name);
        formData.append("bank_account", form.value.bank_account);
        formData.append("bank_account_name", form.value.bank_account_name);
        formData.append("signature_name", form.value.signature_name);
        formData.append("service_terms", form.value.service_terms);

        if (form.value.logo) {
            formData.append("logo", form.value.logo);
        }
        if (form.value.qris_image) {
            formData.append("qris_image", form.value.qris_image);
        }

        await api.post("/store-settings", formData);
        toast.success("Pengaturan toko berhasil diperbarui");
        fetchSettings();
    } catch (e) {
        toast.error("Gagal memperbarui pengaturan toko");
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div class="max-w-4xl mx-auto py-6">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Informasi Toko
                </h1>
                <p class="text-slate-500 mt-1">
                    Kelola identitas toko yang akan tampil di invoice dan
                    laporan.
                </p>
            </div>
        </div>

        <div v-if="isLoading" class="flex justify-center items-center py-20">
            <div
                class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"
            ></div>
        </div>

        <form v-else @submit.prevent="submitForm" class="space-y-6">
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
            >
                <div class="p-6 md:p-8 space-y-6">
                    <!-- Logo Upload -->
                    <div class="flex flex-col md:flex-row gap-8 items-start">
                        <div class="shrink-0">
                            <label
                                class="block text-sm font-semibold text-slate-700 mb-3"
                                >Logo Toko</label
                            >
                            <div class="relative group">
                                <div
                                    class="w-32 h-32 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden transition group-hover:border-blue-400 group-hover:bg-blue-50/30"
                                >
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        class="w-full h-full object-contain p-2"
                                    />
                                    <svg
                                        v-else
                                        class="w-8 h-8 text-slate-300"
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
                                </div>
                                <input
                                    type="file"
                                    @change="handleLogoChange"
                                    class="absolute inset-0 opacity-0 cursor-pointer"
                                    accept="image/*"
                                />
                                <div
                                    class="mt-2 text-[10px] text-center text-slate-400"
                                >
                                    Klik untuk ganti logo
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-6 w-full"
                        >
                            <div class="col-span-2 md:col-span-1">
                                <label
                                    class="block text-sm font-semibold text-slate-700 mb-1.5"
                                    >Nama Toko
                                    <span class="text-red-500">*</span></label
                                >
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="Contoh: Bryan Gadget"
                                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                />
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label
                                    class="block text-sm font-semibold text-slate-700 mb-1.5"
                                    >Email Toko</label
                                >
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="toko@example.com"
                                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                />
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label
                                    class="block text-sm font-semibold text-slate-700 mb-1.5"
                                    >No. Telepon / WhatsApp</label
                                >
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    placeholder="0812xxxx"
                                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                />
                            </div>

                            <div class="col-span-2">
                                <label
                                    class="block text-sm font-semibold text-slate-700 mb-1.5"
                                    >Alamat Lengkap</label
                                >
                                <textarea
                                    v-model="form.address"
                                    rows="3"
                                    placeholder="Jl. Raya Nomor..."
                                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition resize-none"
                                ></textarea>
                            </div>

                            <div class="col-span-2">
                                <label
                                    class="block text-sm font-semibold text-slate-700 mb-1.5"
                                    >Pesan Kaki (Footer Text)</label
                                >
                                <input
                                    v-model="form.footer_text"
                                    type="text"
                                    placeholder="Terima kasih atas kunjungan Anda"
                                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                />
                                <p class="mt-1.5 text-xs text-slate-400">
                                    Teks ini akan muncul di bagian paling bawah
                                    pada invoice.
                                </p>
                            </div>

                            <!-- Bank Info Section -->
                            <div
                                class="col-span-2 pt-6 border-t border-slate-100"
                            >
                                <h3
                                    class="text-sm font-bold text-slate-800 uppercase tracking-widest mb-4"
                                >
                                    Informasi Rekening & QRIS
                                </h3>
                                <div
                                    class="grid grid-cols-1 md:grid-cols-3 gap-4"
                                >
                                    <div>
                                        <label
                                            class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5"
                                            >Nama Bank / ATM</label
                                        >
                                        <input
                                            v-model="form.bank_name"
                                            type="text"
                                            placeholder="Contoh: BCA"
                                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5"
                                            >Nomor Rekening</label
                                        >
                                        <input
                                            v-model="form.bank_account"
                                            type="text"
                                            placeholder="XXXX-XXXX-XXXX"
                                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5"
                                            >Atas Nama (A/N)</label
                                        >
                                        <input
                                            v-model="form.bank_account_name"
                                            type="text"
                                            placeholder="Nama Pemilik Rekening"
                                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                        />
                                    </div>
                                    <div class="col-span-1 md:col-span-3 mt-2">
                                        <label
                                            class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5"
                                            >Nama Untuk Tanda Tangan
                                            Invoice</label
                                        >
                                        <input
                                            v-model="form.signature_name"
                                            type="text"
                                            placeholder="Contoh: Febryana Nurudin Araniri"
                                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                                        />
                                        <p
                                            class="mt-1 text-[10px] text-slate-400 italic"
                                        >
                                            Nama ini akan muncul secara otomatis
                                            di bagian tanda tangan invoice.
                                        </p>
                                    </div>

                                    <div class="col-span-1 md:col-span-3 mt-4">
                                        <label
                                            class="block text-sm font-semibold text-slate-700 mb-1.5"
                                            >Syarat & Ketentuan Servis</label
                                        >
                                        <textarea
                                            v-model="form.service_terms"
                                            rows="6"
                                            placeholder="Masukkan syarat & ketentuan servis..."
                                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition resize-none"
                                        ></textarea>
                                        <p
                                            class="mt-1.5 text-xs text-slate-400"
                                        >
                                            Gunakan baris baru untuk setiap poin
                                            syarat dan ketentuan.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- QRIS Upload -->
                            <div class="col-span-2 pt-4">
                                <label
                                    class="block text-[11px] font-bold text-slate-500 uppercase mb-3"
                                    >Barcode QRIS (Opsional)</label
                                >
                                <div class="relative group w-48">
                                    <div
                                        class="w-48 h-48 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden transition group-hover:border-blue-400 group-hover:bg-blue-50/30"
                                    >
                                        <img
                                            v-if="qrisPreview"
                                            :src="qrisPreview"
                                            class="w-full h-full object-contain p-2"
                                        />
                                        <div v-else class="text-center p-4">
                                            <svg
                                                class="w-10 h-10 text-slate-300 mx-auto mb-2"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h3m-3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >Pilih Barcode QRIS</span
                                            >
                                        </div>
                                    </div>
                                    <input
                                        type="file"
                                        @change="handleQrisChange"
                                        class="absolute inset-0 opacity-0 cursor-pointer"
                                        accept="image/*"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="px-8 py-4 bg-slate-50 border-t border-slate-200 flex justify-end"
                >
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition flex items-center gap-2 disabled:bg-slate-400"
                    >
                        <svg
                            v-if="isSaving"
                            class="animate-spin h-4 w-4 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ isSaving ? "Menyimpan..." : "Simpan Perubahan" }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
