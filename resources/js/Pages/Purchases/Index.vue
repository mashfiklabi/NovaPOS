<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppTable from '@/Components/AppTable.vue';
import AppPagination from '@/Components/AppPagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AppButton from '@/Components/AppButton.vue';
import AppSelect from '@/Components/AppSelect.vue';

interface Supplier {
    id: number;
    name: string;
    company_name: string | null;
}

interface Purchase {
    id: number;
    uuid: string;
    po_number: string;
    supplier_id: number;
    purchase_date: string;
    expected_delivery_date: string | null;
    status: 'draft' | 'received' | 'cancelled';
    payment_status: 'unpaid' | 'partial' | 'paid';
    subtotal: string | number;
    discount_amount: string | number;
    tax_amount: string | number;
    shipping_cost: string | number;
    grand_total: string | number;
    paid_amount: string | number;
    due_amount: string | number;
    supplier?: Supplier | null;
    deleted_at?: string | null;
}

const props = defineProps<{
    purchases: {
        data: Purchase[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
        status: string | null;
        payment_status: string | null;
        trash?: string;
    };
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const paymentStatusFilter = ref(props.filters.payment_status || '');
const activeTab = ref(props.filters.trash || 'active'); // active, trash

const updateFilters = () => {
    router.get('/purchases', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        payment_status: paymentStatusFilter.value || undefined,
        trash: activeTab.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([search, statusFilter, paymentStatusFilter], () => {
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
    return props.purchases.data.length > 0 && selectedIds.value.length === props.purchases.data.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < props.purchases.data.length;
});

watch([selectedIds, () => props.purchases.data], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = isPartiallySelected.value;
    }
}, { deep: true });

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.purchases.data.map(p => p.id);
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

const receivePurchase = (purchase: Purchase) => {
    if (confirm(`Are you sure you want to receive purchase order "${purchase.po_number}"? This will update stock levels.`)) {
        router.post(`/purchases/${purchase.id}/receive`, {}, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const cancelPurchase = (purchase: Purchase) => {
    if (confirm(`Are you sure you want to cancel purchase order "${purchase.po_number}"?`)) {
        router.post(`/purchases/${purchase.id}/cancel`, {}, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const deletePurchase = (purchase: Purchase) => {
    if (confirm(`Are you sure you want to move purchase "${purchase.po_number}" to Trash?`)) {
        router.delete(`/purchases/${purchase.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restorePurchase = (purchase: Purchase) => {
    if (confirm(`Are you sure you want to restore purchase "${purchase.po_number}"?`)) {
        router.post(`/purchases/${purchase.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to move ${selectedIds.value.length} selected purchase orders to Trash?`)) {
        router.post('/purchases/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected purchase orders?`)) {
        router.post('/purchases/bulk-restore', {
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
    window.location.href = '/purchases/export';
};

const formatCurrency = (amount: string | number) => {
    return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <AppLayout>
        <Head title="Purchase Orders Management" />

        <PageHeader title="Purchase Orders" :breadcrumbs="[{ name: 'Purchases' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search PO # or Supplier..." />

                    <AppButton
                        v-if="hasPermission('purchases.view')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <Link
                        v-if="hasPermission('purchases.create')"
                        href="/purchases/create"
                        class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none transition-colors"
                    >
                        Create Purchase Order
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Filters & Tabs -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-2">
            <!-- Tabs & Filter selects -->
            <div class="flex flex-wrap items-center space-x-4">
                <button
                    @click="switchTab('active')"
                    class="pb-2 text-sm font-semibold transition-colors relative"
                    :class="[
                        activeTab === 'active'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Active Purchases
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

                <div class="h-4 w-px bg-gray-300 dark:bg-gray-700"></div>

                <div class="w-36">
                    <AppSelect
                        v-model="statusFilter"
                        :options="[
                            { value: '', label: 'All Statuses' },
                            { value: 'draft', label: 'Draft' },
                            { value: 'received', label: 'Received' },
                            { value: 'cancelled', label: 'Cancelled' }
                        ]"
                    />
                </div>

                <div class="w-36">
                    <AppSelect
                        v-model="paymentStatusFilter"
                        :options="[
                            { value: '', label: 'All Payments' },
                            { value: 'unpaid', label: 'Unpaid' },
                            { value: 'partial', label: 'Partial' },
                            { value: 'paid', label: 'Paid' }
                        ]"
                    />
                </div>
            </div>

            <!-- Bulk actions toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="activeTab === 'active' && hasPermission('purchases.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Move to Trash
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('purchases.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="purchases.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted purchase orders' : 'No purchase orders recorded'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Create purchase orders to restock inventory from suppliers.'"
                >
                    <template #actions>
                        <Link
                            v-if="activeTab !== 'trash' && hasPermission('purchases.create')"
                            href="/purchases/create"
                            class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500"
                        >
                            Create First Purchase Order
                        </Link>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'PO Number', 'Supplier', 'Order Date', 'Grand Total', 'Paid / Due', 'Order Status', 'Payment Status', 'Actions']">
                    <tr v-for="purchase in purchases.data" :key="purchase.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(purchase.id)"
                                @change="toggleSelectOne(purchase.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                            <Link :href="`/purchases/${purchase.id}`" class="hover:underline">
                                {{ purchase.po_number }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ purchase.supplier ? purchase.supplier.name : 'Unknown' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ purchase.purchase_date }}
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            ${{ formatCurrency(purchase.grand_total) }}
                        </td>
                        <td class="px-6 py-4 text-xs whitespace-nowrap text-gray-500">
                            <div>Paid: <span class="font-semibold text-gray-700 dark:text-gray-300">${{ formatCurrency(purchase.paid_amount) }}</span></div>
                            <div>Due: <span class="font-semibold text-red-600 dark:text-red-400">${{ formatCurrency(purchase.due_amount) }}</span></div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': purchase.status === 'draft',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': purchase.status === 'received',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': purchase.status === 'cancelled'
                                }"
                            >
                                {{ purchase.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="{
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': purchase.payment_status === 'unpaid',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': purchase.payment_status === 'partial',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': purchase.payment_status === 'paid'
                                }"
                            >
                                {{ purchase.payment_status }}
                            </span>
                        </td>
                        <!-- Actions -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-2">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('purchases.restore')"
                                    @click="restorePurchase(purchase)"
                                    class="text-xs font-semibold text-green-600 hover:text-green-500 dark:text-green-400"
                                >
                                    Restore
                                </button>
                            </template>
                            <template v-else>
                                <Link
                                    :href="`/purchases/${purchase.id}`"
                                    class="text-xs font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    View
                                </Link>

                                <button
                                    v-if="purchase.status === 'draft' && hasPermission('purchases.receive')"
                                    @click="receivePurchase(purchase)"
                                    class="text-xs font-semibold text-green-600 hover:text-green-500 dark:text-green-400"
                                >
                                    Receive
                                </button>

                                <button
                                    v-if="purchase.status === 'draft' && hasPermission('purchases.cancel')"
                                    @click="cancelPurchase(purchase)"
                                    class="text-xs font-semibold text-yellow-600 hover:text-yellow-500 dark:text-yellow-400"
                                >
                                    Cancel
                                </button>

                                <button
                                    v-if="hasPermission('purchases.delete')"
                                    @click="deletePurchase(purchase)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Trash
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
                <AppPagination :links="purchases.links" />
            </div>
        </AppCard>
    </AppLayout>
</template>
