<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../api";
import { useAuthStore } from "../stores/auth";
import { useToast } from "../composables/useToast";
import ConfirmDialog from "../components/ConfirmDialog.vue";

const authStore = useAuthStore();
const toast = useToast();
const users = ref([]);
const loading = ref(false);
const error = ref(null);
const pagination = ref({});
const roles = ref([]);
const perPage = ref(10);
const searchQuery = ref("");
const showEditModal = ref(false);
const editingUser = ref(null);
const editForm = ref({
    name: "",
    username: "",
    role: "",
    password: "",
});
const editLoading = ref(false);
const editError = ref("");
const editSuccess = ref("");

// Delete confirmation
const showDeleteDialog = ref(false);
const deleteTarget = ref(null);
const deleteLoading = ref(false);

const fetchUsers = async (page = 1) => {
    loading.value = true;
    try {
        // Use the paginated endpoint
        const response = await api.get(
            `/user/all/paginated?page=${page}&per_page=${perPage.value}&search=${searchQuery.value}`
        );
        users.value = response.data.data.data;
        pagination.value = {
            current_page: response.data.data.current_page,
            last_page: response.data.data.last_page,
            per_page: response.data.data.per_page,
            total: response.data.data.total,
        };
    } catch (err) {
        error.value = "Failed to load users";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const fetchRoles = async () => {
    try {
        const response = await api.get("/roles");
        roles.value = response.data.data;
    } catch (err) {
        console.error("Failed to load roles", err);
    }
};

const openEditModal = (user) => {
    editingUser.value = user;
    editForm.value = {
        name: user.name,
        username: user.username || "",
        role: user.roles && user.roles.length > 0 ? user.roles[0].name : "",
        password: "",
    };
    editError.value = "";
    editSuccess.value = "";
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingUser.value = null;
    editForm.value = {
        name: "",
        username: "",
        role: "",
        password: "",
    };
    editError.value = "";
    editSuccess.value = "";
};

const updateUser = async () => {
    editLoading.value = true;
    editError.value = "";
    editSuccess.value = "";

    try {
        const payload = { ...editForm.value };
        if (!payload.password) delete payload.password;
        await api.put(`/user/${editingUser.value.id}`, payload);
        editSuccess.value = "User updated successfully!";

        // Refresh user list
        await fetchUsers(pagination.value.current_page);

        // Close modal after 1 second
        setTimeout(() => {
            closeEditModal();
        }, 1000);
    } catch (err) {
        editError.value =
            err.response?.data?.message || "Failed to update user";
    } finally {
        editLoading.value = false;
    }
};

const confirmDelete = (user) => {
    deleteTarget.value = user;
    showDeleteDialog.value = true;
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    deleteTarget.value = null;
};

const deleteUser = async () => {
    if (!deleteTarget.value) return;
    deleteLoading.value = true;
    try {
        await api.delete(`/user/${deleteTarget.value.id}`);
        toast.success("Pengguna berhasil dihapus");
        showDeleteDialog.value = false;
        deleteTarget.value = null;
        fetchUsers(pagination.value.current_page);
    } catch (err) {
        toast.error(err.response?.data?.message || "Gagal menghapus pengguna");
    } finally {
        deleteLoading.value = false;
    }
};

const getRoleBadgeColor = (roleName) => {
    const colors = {
        "super-admin": "bg-purple-100 text-purple-800",
        admin: "bg-blue-100 text-blue-800",
        owner: "bg-amber-100 text-amber-800",
        kasir: "bg-emerald-100 text-emerald-800",
    };
    return colors[roleName] || "bg-gray-100 text-gray-800";
};

watch(perPage, () => {
    fetchUsers(1);
});

let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchUsers(1);
    }, 500);
});

onMounted(() => {
    fetchUsers();
    fetchRoles();
});
</script>

<template>
    <div>
        <div class="overflow-hidden bg-white rounded-lg shadow">
            <div
                class="flex flex-col gap-4 p-4 border-b border-gray-200 sm:p-6 sm:flex-row sm:justify-between sm:items-center"
            >
                <h2 class="text-xl font-bold text-gray-800">Users List</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <!-- Tampilkan -->
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-wider"
                            >Tampilkan</label
                        >
                        <div class="relative">
                            <select
                                v-model="perPage"
                                class="appearance-none px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm outline-none w-20 pr-8"
                            >
                                <option :value="10">10</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                            <div
                                class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 pointer-events-none"
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
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-[10px] font-bold text-gray-400 uppercase tracking-wider"
                            >Cari User</label
                        >
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Nama atau email..."
                                class="pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm outline-none w-full sm:w-64"
                            />
                            <div
                                class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"
                            >
                                <svg
                                    class="w-4 h-4 text-gray-400"
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
                        </div>
                    </div>

                    <!-- Total -->
                    <!-- <div class="flex flex-col items-end pb-1">
                        <span
                            class="text-[10px] font-bold text-gray-400 uppercase"
                            >Total Data</span
                        >
                        <span
                            class="text-sm font-bold leading-none text-indigo-600"
                            >{{ pagination.total || 0 }}</span
                        >
                    </div> -->
                    <!-- tambah user -->
                    <div
                        class="flex flex-col items-end px-3 py-1.5 bg-blue-600 text-white rounded-lg shadow-lg"
                    >
                        <router-link
                            to="/dashboard/management/user/create"
                            class="text-sm btn btn-primary"
                            >+ Tambah User</router-link
                        >
                    </div>
                </div>
            </div>

            <div v-if="loading" class="p-6 text-center text-gray-500">
                Loading users...
            </div>

            <div v-else-if="error" class="p-6 text-center text-red-500">
                {{ error }}
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <table class="table-fixed-layout">
                    <thead class="table-header">
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th class="w-64">Nama</th>
                            <th class="w-40">Username</th>
                            <th class="w-48">Role</th>
                            <th class="w-40">Joined</th>
                            <th
                                v-if="authStore.isAdmin"
                                class="w-32 text-center"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr
                            v-for="(user, index) in users.filter(
                                (u) =>
                                    !u.roles?.some(
                                        (r) => r.name === 'super-admin'
                                    )
                            )"
                            :key="user.id"
                            class="table-row group"
                        >
                            <td
                                class="table-cell font-medium text-center text-slate-500"
                            >
                                {{
                                    (pagination.current_page - 1) *
                                        pagination.per_page +
                                    index +
                                    1
                                }}
                            </td>
                            <td class="table-cell">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 h-8">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-full shadow-sm bg-gradient-to-br from-indigo-500 to-purple-600"
                                        >
                                            {{
                                                user.name
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div
                                            class="text-sm font-semibold text-slate-800"
                                        >
                                            {{ user.name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="table-cell font-mono text-xs text-slate-600"
                            >
                                {{ user.username || "-" }}
                            </td>
                            <td class="table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.name"
                                        :class="getRoleBadgeColor(role.name)"
                                        class="px-2 py-0.5 inline-flex text-[10px] leading-4 font-bold rounded-full border"
                                    >
                                        {{ role.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="table-cell text-slate-500">
                                {{
                                    new Date(
                                        user.created_at
                                    ).toLocaleDateString()
                                }}
                            </td>
                            <td
                                v-if="authStore.isAdmin"
                                class="table-cell text-center"
                            >
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <button
                                        @click="openEditModal(user)"
                                        class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                                        title="Edit"
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
                                        @click="confirmDelete(user)"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                        title="Hapus"
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

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6"
            >
                <div class="flex justify-between flex-1 sm:hidden">
                    <button
                        @click="fetchUsers(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Previous
                    </button>
                    <button
                        @click="fetchUsers(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Next
                    </button>
                </div>
                <div
                    class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm text-gray-700">
                            Page
                            <span class="font-medium">{{
                                pagination.current_page
                            }}</span>
                            of
                            <span class="font-medium">{{
                                pagination.last_page
                            }}</span>
                        </p>
                    </div>
                    <div>
                        <nav
                            class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm"
                            aria-label="Pagination"
                        >
                            <button
                                @click="fetchUsers(pagination.current_page - 1)"
                                :disabled="pagination.current_page === 1"
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50"
                            >
                                <span class="sr-only">Previous</span>
                                &larr;
                            </button>
                            <button
                                @click="fetchUsers(pagination.current_page + 1)"
                                :disabled="
                                    pagination.current_page ===
                                    pagination.last_page
                                "
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50"
                            >
                                <span class="sr-only">Next</span>
                                &rarr;
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation -->
        <ConfirmDialog
            :show="showDeleteDialog"
            title="Hapus Pengguna"
            :message="`Apakah Anda yakin ingin menghapus pengguna &quot;${deleteTarget?.name}&quot;? Tindakan ini tidak dapat dibatalkan.`"
            :loading="deleteLoading"
            @confirm="deleteUser"
            @cancel="cancelDelete"
        />

        <!-- Edit User Modal -->
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-10 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <!-- Background overlay -->
                <div
                    class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                    aria-hidden="true"
                    @click="closeEditModal"
                ></div>

                <!-- Center modal -->
                <span
                    class="hidden sm:inline-block sm:align-middle sm:h-screen"
                    aria-hidden="true"
                    >&#8203;</span
                >

                <div
                    class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                >
                    <form @submit.prevent="updateUser">
                        <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="w-full mt-3 text-center sm:mt-0 sm:text-left"
                                >
                                    <h3
                                        class="mb-4 text-lg font-medium leading-6 text-gray-900"
                                        id="modal-title"
                                    >
                                        Edit User
                                    </h3>

                                    <div
                                        v-if="editSuccess"
                                        class="px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded"
                                    >
                                        {{ editSuccess }}
                                    </div>
                                    <div
                                        v-if="editError"
                                        class="px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded"
                                    >
                                        {{ editError }}
                                    </div>

                                    <div class="space-y-4">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Nama</label
                                            >
                                            <input
                                                v-model="editForm.name"
                                                type="text"
                                                required
                                                class="block w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Username</label
                                            >
                                            <input
                                                v-model="editForm.username"
                                                type="text"
                                                required
                                                class="block w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="Hanya huruf dan angka"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Password Baru
                                                <span
                                                    class="font-normal text-gray-400"
                                                    >(kosongkan jika tidak
                                                    diubah)</span
                                                ></label
                                            >
                                            <input
                                                v-model="editForm.password"
                                                type="password"
                                                class="block w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="Minimal 3 karakter"
                                                autocomplete="new-password"
                                            />
                                        </div>

                                        <div
                                            v-if="
                                                authStore.isSuperAdmin ||
                                                authStore.isAdmin
                                            "
                                        >
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Role</label
                                            >
                                            <select
                                                v-model="editForm.role"
                                                required
                                                class="block w-full p-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">
                                                    Select a role
                                                </option>
                                                <option
                                                    v-for="role in roles.filter(r => authStore.isSuperAdmin || r.name !== 'super-admin')"
                                                    :key="role.id"
                                                    :value="role.name"
                                                >
                                                    {{ role.name }} ({{
                                                        role.permissions_count
                                                    }}
                                                    permissions)
                                                </option>
                                            </select>
                                            <p
                                                class="mt-1 text-xs text-gray-500"
                                            >
                                                Only super-admin can assign
                                                roles
                                            </p>
                                        </div>
                                        <div v-else>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Role</label
                                            >
                                            <input
                                                :value="editForm.role"
                                                type="text"
                                                disabled
                                                class="block w-full p-2 mt-1 bg-gray-100 border border-gray-300 rounded-md shadow-sm cursor-not-allowed sm:text-sm"
                                            />
                                            <p
                                                class="mt-1 text-xs text-gray-500"
                                            >
                                                You don't have permission to
                                                change roles
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse"
                        >
                            <button
                                type="submit"
                                :disabled="editLoading"
                                class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ editLoading ? "Saving..." : "Save Changes" }}
                            </button>
                            <button
                                type="button"
                                @click="closeEditModal"
                                :disabled="editLoading"
                                class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
