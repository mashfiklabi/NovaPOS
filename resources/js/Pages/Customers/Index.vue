<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { PageProps, Customer, PaginatedData } from '@/types';
import { formatCurrency, formatDate } from '@/Composables/useFormatters';
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

const props = defineProps<{
    customers: PaginatedData<Customer>;
    filters: {
        search: string | null;
        status?: string;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = ref(props.filters.status || 'active'); // active, trash

const updateFilters = () => {
    router.get('/customers', {
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
    return props.customers.data.length > 0 && selectedIds.value.length === props.customers.data.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < props.customers.data.length;
});

watch([selectedIds, () => props.customers.data], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = isPartiallySelected.value;
    }
}, { deep: true });

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.customers.data.map(c => c.id);
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
const editingCustomer = ref<Customer | null>(null);

const form = useForm({
    name: '',
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
    editingCustomer.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    isDrawerOpen.value = true;
};

const openEditDrawer = (customer: Customer) => {
    editingCustomer.value = customer;
    form.clearErrors();
    form.name = customer.name;
    form.email = customer.email || '';
    form.phone = customer.phone || '';
    form.address = customer.address || '';
    form.city = customer.city || '';
    form.state = customer.state || '';
    form.postal_code = customer.postal_code || '';
    form.country = customer.country || '';
    form.tax_number = customer.tax_number || '';
    form.status = customer.status;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingCustomer.value = null;
    form.reset();
};

const submit = () => {
    if (editingCustomer.value) {
        form.put(`/customers/${editingCustomer.value.id}`, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
            },
        });
    } else {
        form.post('/customers', {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
            },
        });
    }
};

const deleteCustomer = (customer: Customer) => {
    if (confirm(`Are you sure you want to move customer "${customer.name}" to Trash?`)) {
        router.delete(`/customers/${customer.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreCustomer = (customer: Customer) => {
    if (confirm(`Are you sure you want to restore customer "${customer.name}"?`)) {
        router.post(`/customers/${customer.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to move ${selectedIds.value.length} selected customers to Trash?`)) {
        router.post('/customers/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected customers?`)) {
        router.post('/customers/bulk-restore', {
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
    window.location.href = '/customers/export';
};
</script>

<template>
    <AppLayout>
        <Head title="Customer Management" />

        <PageHeader title="Customers" :breadcrumbs="[{ name: 'Customers' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search customers..." />

                    <AppButton
                        v-if="hasPermission('customers.view')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('customers.create')"
                        variant="primary"
                        @click="openCreateDrawer"
                    >
                        Add Customer
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
                    Active Customers
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
                    v-if="activeTab === 'active' && hasPermission('customers.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Move to Trash
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('customers.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="customers.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted customers' : 'No customers registered'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Add your customer records to manage POS sales and billing history.'"
                >
                    <template #actions>
                        <AppButton v-if="activeTab !== 'trash' && hasPermission('customers.create')" variant="primary" @click="openCreateDrawer">
                            Add First Customer
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Customer Name', 'Email', 'Phone', 'Store Credit', 'Tax Number', 'Status', 'Actions']">
                    <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(customer.id)"
                                @change="toggleSelectOne(customer.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ customer.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ customer.email || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ customer.phone || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                            {{ formatCurrency((customer as any).store_credit_balance || 0) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ customer.tax_number || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="[
                                    customer.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ customer.status }}
                            </span>
                        </td>
                        <!-- Actions column -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-2">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('customers.restore')"
                                    @click="restoreCustomer(customer)"
                                    class="p-1 text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 rounded-md hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
                                    title="Restore Customer"
                                >
                                    <Heroicon name="ArrowPathIcon" class="h-4 w-4" />
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    v-if="hasPermission('customers.update')"
                                    @click="openEditDrawer(customer)"
                                    class="p-1 text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors"
                                    title="Edit Customer"
                                >
                                    <Heroicon name="PencilIcon" class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="hasPermission('customers.delete')"
                                    @click="deleteCustomer(customer)"
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
                <AppPagination :links="customers.links" />
            </div>
        </AppCard>

        <!-- Customer Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingCustomer ? 'Edit Customer' : 'Add Customer'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <AppInput label="Customer Name" v-model="form.name" :error="form.errors.name" required />
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
                    {{ editingCustomer ? 'Save Changes' : 'Create Customer' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>