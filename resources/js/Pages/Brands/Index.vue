<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppTable from '@/Components/AppTable.vue';
import AppPagination from '@/Components/AppPagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import AppDrawer from '@/Components/AppDrawer.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';

interface Brand {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    logo: string | null;
    status: string;
    deleted_at?: string | null;
}

const props = defineProps<{
    brands: {
        data: Brand[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
        status?: string;
        sort_by?: string;
        sort_dir?: string;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = ref(props.filters.status === 'trash' ? 'trash' : 'active'); // tab: active or trash
const statusFilter = ref(props.filters.status && props.filters.status !== 'trash' ? props.filters.status : 'all'); // active, inactive, all
const sortBy = ref(props.filters.sort_by || 'id');
const sortDir = ref(props.filters.sort_dir || 'desc');

// Sync filters with router
const updateFilters = () => {
    router.get('/brands', {
        search: search.value || undefined,
        status: activeTab.value === 'trash' ? 'trash' : statusFilter.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    updateFilters();
});

const switchTab = (tab: 'active' | 'trash') => {
    activeTab.value = tab;
    selectedIds.value = [];
    statusFilter.value = 'all';
    updateFilters();
};

const handleStatusFilterChange = (val: string) => {
    statusFilter.value = val;
    activeTab.value = 'active';
    updateFilters();
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    updateFilters();
};

// Check permissions
const hasPermission = (permission: string) => {
    const auth = (usePage().props.auth as any) || {};
    const user = auth.user || {};
    const perms = user.permissions || [];
    const roles = user.roles || [];
    if (roles.includes('Super Admin')) {
        return true;
    }
    return perms.includes(permission);
};

// Checklist select handling
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    return props.brands.data.length > 0 && selectedIds.value.length === props.brands.data.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.brands.data.map(b => b.id);
    }
};

const toggleSelectOne = (id: number) => {
    const index = selectedIds.value.indexOf(id);
    if (index > -1) {
        selectedIds.value.splice(index, 1);
    } else {
        selectedIds.value.push(id);
    }
};

// Drawer state & CRUD
const isDrawerOpen = ref(false);
const editingBrand = ref<Brand | null>(null);

const form = useForm({
    _method: 'POST',
    name: '',
    description: '',
    status: 'active',
    logo: null as File | null,
});

const handleFile = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    if (files && files.length > 0) {
        form.logo = files[0];
    }
};

const openCreateDrawer = () => {
    editingBrand.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.status = 'active';
    isDrawerOpen.value = true;
};

const openEditDrawer = (brand: Brand) => {
    editingBrand.value = brand;
    form.clearErrors();
    form._method = 'POST'; // support file updates with POST method spoofing
    form.name = brand.name;
    form.description = brand.description || '';
    form.status = brand.status;
    form.logo = null;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingBrand.value = null;
    form.reset();
};

const submit = () => {
    const url = editingBrand.value ? `/brands/${editingBrand.value.id}` : '/brands';

    if (editingBrand.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT'
        })).post(url, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
                form.setError(errors);
            }
        });
    } else {
        form.post(url, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
                form.setError(errors);
            }
        });
    }
};

const deleteBrand = (brand: Brand) => {
    if (confirm(`Are you sure you want to soft delete brand "${brand.name}"?`)) {
        router.delete(`/brands/${brand.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreBrand = (brand: Brand) => {
    if (confirm(`Are you sure you want to restore brand "${brand.name}"?`)) {
        router.post(`/brands/${brand.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const permanentlyDeleteBrand = (brand: Brand) => {
    if (confirm(`WARNING: You are about to PERMANENTLY delete brand "${brand.name}". This will delete its file assets and cannot be undone. Proceed?`)) {
        router.delete(`/brands/${brand.id}/force-delete`, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to soft delete ${selectedIds.value.length} selected brands?`)) {
        router.post('/brands/bulk-delete', {
            ids: selectedIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const bulkRestore = () => {
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected brands?`)) {
        router.post('/brands/bulk-restore', {
            ids: selectedIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkForceDelete = () => {
    if (confirm(`WARNING: You are about to PERMANENTLY delete ${selectedIds.value.length} selected brands. This cannot be undone. Proceed?`)) {
        router.post('/brands/bulk-force-delete', {
            ids: selectedIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const exportCSV = () => {
    window.location.href = '/brands/export';
};
</script>

<template>
    <AppLayout>
        <Head title="Manufacturer Brands Management" />

        <PageHeader title="Brands" :breadcrumbs="[{ name: 'Brands' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search brands..." />

                    <AppButton
                        v-if="hasPermission('brands.export')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('brands.create')"
                        variant="primary"
                        @click="openCreateDrawer"
                    >
                        Add Brand
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- Status Tabs & Bulk Bar -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-2">
            <!-- Tabs -->
            <div class="flex space-x-4">
                <button
                    @click="switchTab('active')"
                    class="pb-2 text-sm font-semibold transition-colors relative"
                    :class="[
                        activeTab === 'active'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Active Brands
                </button>
                <button
                    @click="switchTab('trash')"
                    class="pb-2 text-sm font-semibold transition-colors relative"
                    :class="[
                        activeTab === 'trash'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Trash / Deleted
                </button>
            </div>

            <!-- Proper Status Filter Dropdown -->
            <div v-if="activeTab === 'active'" class="flex items-center space-x-2">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Status:</span>
                <select
                    :value="statusFilter"
                    @change="handleStatusFilterChange(($event.target as HTMLSelectElement).value)"
                    class="rounded-md border-gray-300 text-xs py-1 pl-2 pr-8 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100"
                >
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- Bulk Toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="activeTab === 'active' && hasPermission('brands.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Bulk Soft Delete
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('brands.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('brands.delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkForceDelete"
                >
                    Bulk Delete Permanently
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="brands.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted brands' : 'No manufacturer brands recorded'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Group your retail inventory items by manufacturers and product lines.'"
                >
                    <template #actions>
                        <AppButton v-if="activeTab !== 'trash' && hasPermission('brands.create')" variant="primary" @click="openCreateDrawer">
                            Create First Brand
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Brand Logo', 'Brand Name', 'Description', 'Status', 'Actions']">
                    <!-- Column sorting headers -->
                    <template #header-tr-content>
                        <th class="w-10 pl-6 py-3 text-left">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Brand Logo
                        </th>
                        <th @click="toggleSort('name')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Brand Name
                            <span v-if="sortBy === 'name'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th @click="toggleSort('status')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Status
                            <span v-if="sortBy === 'status'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </template>

                    <tr v-for="brand in brands.data" :key="brand.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(brand.id)"
                                @change="toggleSelectOne(brand.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="h-10 w-16 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded flex items-center justify-center overflow-hidden">
                                <!-- Secure authorized stream logo download link -->
                                <img v-if="brand.logo" :src="route('brands.logo', brand.id)" class="h-full w-full object-contain" />
                                <span v-else class="text-xs font-bold text-gray-400 uppercase">{{ brand.name.substring(0, 3) }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ brand.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                            {{ brand.description || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="[
                                    brand.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ brand.status }}
                            </span>
                        </td>
                        <!-- Actions column -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('brands.restore')"
                                    @click="restoreBrand(brand)"
                                    class="text-xs font-semibold text-green-600 hover:text-green-500 dark:text-green-400"
                                >
                                    Restore
                                </button>
                                <button
                                    v-if="hasPermission('brands.delete')"
                                    @click="permanentlyDeleteBrand(brand)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Delete Permanently
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    v-if="hasPermission('brands.update')"
                                    @click="openEditDrawer(brand)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="hasPermission('brands.delete')"
                                    @click="deleteBrand(brand)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Delete
                                </button>
                            </template>
                        </td>
                    </tr>
                </AppTable>
                <AppPagination :links="brands.links" />
            </div>
        </AppCard>

        <!-- Brand Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingBrand ? 'Edit Brand' : 'Create Brand'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <AppInput label="Brand Name" v-model="form.name" :error="form.errors.name" required />
                </div>

                <div>
                    <AppSelect
                        label="Status"
                        v-model="form.status"
                        :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' }
                        ]"
                        :error="form.errors.status"
                        required
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand Logo Image</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleFile"
                        class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <p v-if="form.errors.logo" class="mt-1 text-xs text-red-500">{{ form.errors.logo }}</p>
                </div>

                <div>
                    <AppTextarea label="Description (Optional)" v-model="form.description" :error="form.errors.description" :rows="4" />
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingBrand ? 'Save Changes' : 'Create Brand' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
