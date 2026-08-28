<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
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
import Heroicon from '@/Components/Heroicon.vue';

interface Supplier {
    id: number;
    uuid: string;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    tax_number: string | null;
    status: string;
    deleted_at?: string | null;
}

const props = defineProps<{
    suppliers: {
        data: Supplier[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
        status?: string;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = ref(props.filters.status || 'active'); // active, trash

const updateFilters = () => {
    router.get('/suppliers', {
        search: search.value || undefined,
        status: activeTab.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    updateFilters();
});

const switchTab = (tab: string) => {
    activeTab.value = tab;
    selectedIds.value = [];
    updateFilters();
};

const page = usePage<PageProps>();
const permissions = computed(() => page.props.auth.permissions || page.props.auth.user?.permissions || []);
const roles = computed(() => page.props.auth.user?.roles || []);
const isSuperAdmin = computed(() => roles.value.includes('Super Admin'));

const hasPermission = (permission: string) => {
    if (isSuperAdmin.value) return true;
    return permissions.value.includes(permission);
};

// Multi-select handling
const selectedIds = ref<number[]>([]);
const selectAllRef = ref<HTMLInputElement | null>(null);

const isAllSelected = computed(() => {
    return props.suppliers.data.length > 0 && selectedIds.value.length === props.suppliers.data.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < props.suppliers.data.length;
});

watch([selectedIds, () => props.suppliers.data], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = isPartiallySelected.value;
    }
}, { deep: true });

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.suppliers.data.map(s => s.id);
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

// Actions
const isDrawerOpen = ref(false);
const editingSupplier = ref<Supplier | null>(null);

const form = useForm({
    name: '',
    company_name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    tax_number: '',
    status: 'active',
});

const openCreateDrawer = () => {
    editingSupplier.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    isDrawerOpen.value = true;
};

const openEditDrawer = (supplier: Supplier) => {
    editingSupplier.value = supplier;
    form.clearErrors();
    form.name = supplier.name;
    form.company_name = supplier.company_name || '';
    form.email = supplier.email || '';
    form.phone = supplier.phone || '';
    form.address = supplier.address || '';
    form.city = supplier.city || '';
    form.state = supplier.state || '';
    form.postal_code = supplier.postal_code || '';
    form.country = supplier.country || '';
    form.tax_number = supplier.tax_number || '';
    form.status = supplier.status;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingSupplier.value = null;
    form.reset();
};

const submit = () => {
    if (editingSupplier.value) {
        form.put(`/suppliers/${editingSupplier.value.id}`, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
            },
        });
    } else {
        form.post('/suppliers', {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
            },
        });
    }
};

const deleteSupplier = (supplier: Supplier) => {
    if (confirm(`Are you sure you want to move supplier "${supplier.name}" to Trash?`)) {
        router.delete(`/suppliers/${supplier.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreSupplier = (supplier: Supplier) => {
    if (confirm(`Are you sure you want to restore supplier "${supplier.name}"?`)) {
        router.post(`/suppliers/${supplier.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to move ${selectedIds.value.length} selected suppliers to Trash?`)) {
        router.post('/suppliers/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected suppliers?`)) {
        router.post('/suppliers/bulk-restore', {
            ids: selectedIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const exportCSV = () => {
    window.location.href = '/suppliers/export';
};
</script>

<template>
    <AppLayout>
        <Head title="Supplier Management" />

        <PageHeader title="Suppliers" :breadcrumbs="[{ name: 'Suppliers' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search suppliers..." />

                    <AppButton
                        v-if="hasPermission('suppliers.view')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('suppliers.create')"
                        variant="primary"
                        @click="openCreateDrawer"
                    >
                        Add Supplier
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- Status Filter Tabs & Bulk Actions Bar -->
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
                    Active Suppliers
                </button>
                <button
                    @click="switchTab('trash')"
                    class="pb-2 text-sm font-semibold transition-colors relative flex items-center gap-1.5"
                    :class="[
                        activeTab === 'trash'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Trash / Deleted
                </button>
            </div>

            <!-- Bulk actions toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="activeTab === 'active' && hasPermission('suppliers.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Move to Trash
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('suppliers.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="suppliers.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted suppliers' : 'No suppliers registered'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Add your vendor/supplier contacts to manage purchasing orders efficiently.'"
                >
                    <template #actions>
                        <AppButton v-if="activeTab !== 'trash' && hasPermission('suppliers.create')" variant="primary" @click="openCreateDrawer">
                            Add First Supplier
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Contact Name', 'Company Name', 'Email', 'Phone', 'City/Country', 'Status', 'Actions']">
                    <tr v-for="supplier in suppliers.data" :key="supplier.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(supplier.id)"
                                @change="toggleSelectOne(supplier.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ supplier.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ supplier.company_name || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ supplier.email || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ supplier.phone || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ [supplier.city, supplier.country].filter(Boolean).join(', ') || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="[
                                    supplier.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ supplier.status }}
                            </span>
                        </td>
                        <!-- Actions column -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-2">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('suppliers.restore')"
                                    @click="restoreSupplier(supplier)"
                                    class="p-1 text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 rounded-md hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
                                    title="Restore Supplier"
                                >
                                    <Heroicon name="ArrowPathIcon" class="h-4 w-4" />
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    v-if="hasPermission('suppliers.update')"
                                    @click="openEditDrawer(supplier)"
                                    class="p-1 text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors"
                                    title="Edit Supplier"
                                >
                                    <Heroicon name="PencilIcon" class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="hasPermission('suppliers.delete')"
                                    @click="deleteSupplier(supplier)"
                                    class="p-1 text-red-600 hover:text-red-500 dark:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors"
                                    title="Move to Trash"
                                >
                                    <Heroicon name="TrashIcon" class="h-4 w-4" />
                                </button>
                            </template>
                        </td>
                    </tr>

                    <template #header-prepend>
                        <th class="w-10 pl-6 py-3 text-left">
                            <input
                                ref="selectAllRef"
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </th>
                    </template>
                </AppTable>
                <AppPagination :links="suppliers.links" />
            </div>
        </AppCard>

        <!-- Supplier Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingSupplier ? 'Edit Supplier' : 'Add Supplier'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <AppInput label="Contact Name" v-model="form.name" :error="form.errors.name" required />
                </div>

                <div>
                    <AppInput label="Company Name" v-model="form.company_name" :error="form.errors.company_name" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <AppInput label="Email Address" type="email" v-model="form.email" :error="form.errors.email" />
                    <AppInput label="Phone Number" v-model="form.phone" :error="form.errors.phone" />
                </div>

                <div>
                    <AppTextarea label="Address" v-model="form.address" :error="form.errors.address" :rows="2" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <AppInput label="City" v-model="form.city" :error="form.errors.city" />
                    <AppInput label="State" v-model="form.state" :error="form.errors.state" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <AppInput label="Postal Code" v-model="form.postal_code" :error="form.errors.postal_code" />
                    <AppInput label="Country" v-model="form.country" :error="form.errors.country" />
                </div>

                <div>
                    <AppInput label="Tax Registration Number" v-model="form.tax_number" :error="form.errors.tax_number" />
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
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingSupplier ? 'Save Changes' : 'Create Supplier' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
