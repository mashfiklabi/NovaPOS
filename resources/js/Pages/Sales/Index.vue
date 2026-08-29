<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { PageProps, Sale, PaginatedData } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppTable from '@/Components/AppTable.vue';
import AppPagination from '@/Components/AppPagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AppButton from '@/Components/AppButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppModal from '@/Components/AppModal.vue';
import AppInput from '@/Components/AppInput.vue';
import Heroicon from '@/Components/Heroicon.vue';

const props = defineProps<{
    sales: PaginatedData<Sale>;
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
    router.get('/sales', {
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
    return props.sales.data.length > 0 && selectedIds.value.length === props.sales.data.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < props.sales.data.length;
});

watch([selectedIds, () => props.sales.data], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = isPartiallySelected.value;
    }
}, { deep: true });

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.sales.data.map(s => s.id);
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

const cancelSale = (sale: Sale) => {
    if (confirm(`Are you sure you want to cancel sale invoice "${sale.invoice_number}"?`)) {
        router.post(`/sales/${sale.id}/cancel`, {}, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const deleteSale = (sale: Sale) => {
    if (confirm(`Are you sure you want to move sale "${sale.invoice_number}" to Trash?`)) {
        router.delete(`/sales/${sale.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreSale = (sale: Sale) => {
    if (confirm(`Are you sure you want to restore sale "${sale.invoice_number}"?`)) {
        router.post(`/sales/${sale.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to move ${selectedIds.value.length} selected sales to Trash?`)) {
        router.post('/sales/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected sales?`)) {
        router.post('/sales/bulk-restore', {
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
    window.location.href = '/sales/export';
};

const formatCurrency = (amount: string | number) => {
    return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Payment Modal State
const paymentSale = ref<Sale | null>(null);
const paymentForm = useForm({
    amount: 0,
    payment_method: 'cash',
    reference_number: '',
    notes: '',
});

const openPaymentModal = (sale: Sale) => {
    paymentSale.value = sale;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.amount = Number(sale.due_amount);
};

const submitPayment = () => {
    if (!paymentSale.value) return;
    paymentForm.post(`/sales/${paymentSale.value.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => {
            paymentSale.value = null;
            paymentForm.reset();
        },
        onError: (err) => {
            if (err.error) alert(err.error);
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Sales & POS Management" />

        <PageHeader title="Sales History" :breadcrumbs="[{ name: 'Sales' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search Invoice # or Customer..." />

                    <AppButton
                        v-if="hasPermission('sales.view')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>
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
                    Active Sales
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
                            { value: 'completed', label: 'Completed' },
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
                    v-if="activeTab === 'active' && hasPermission('sales.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Move to Trash
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('sales.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="sales.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted sales' : 'No sales recorded'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Completed sales transactions will appear here.'"
                />
            </div>

            <div v-else>
                <AppTable :headers="['', 'Invoice #', 'Customer', 'Sale Date', 'Grand Total', 'Paid / Due', 'Sale Status', 'Payment Status', 'Actions']">
                    <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(sale.id)"
                                @change="toggleSelectOne(sale.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                            <Link :href="`/sales/${sale.id}`" class="hover:underline">
                                {{ sale.invoice_number }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ sale.customer ? sale.customer.name : 'Walk-in Customer' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ sale.sale_date }}
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            ${{ formatCurrency(sale.grand_total) }}
                        </td>
                        <td class="px-6 py-4 text-xs whitespace-nowrap text-gray-500">
                            <div>Paid: <span class="font-semibold text-gray-700 dark:text-gray-300">${{ formatCurrency(sale.paid_amount) }}</span></div>
                            <div>Due: <span class="font-semibold text-red-600 dark:text-red-400">${{ formatCurrency(sale.due_amount) }}</span></div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': sale.status === 'draft',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': sale.status === 'completed',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': sale.status === 'cancelled'
                                }"
                            >
                                {{ sale.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="{
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': sale.payment_status === 'unpaid',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': sale.payment_status === 'partial',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': sale.payment_status === 'paid'
                                }"
                            >
                                {{ sale.payment_status }}
                            </span>
                        </td>
                        <!-- Actions -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-2">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('sales.restore')"
                                    @click="restoreSale(sale)"
                                    class="p-1 text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 rounded-md hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
                                    title="Restore Sale"
                                >
                                    <Heroicon name="ArrowPathIcon" class="h-4 w-4" />
                                </button>
                            </template>
                            <template v-else>
                                <Link
                                    :href="`/sales/${sale.id}`"
                                    class="inline-block p-1 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                    title="View Invoice Details"
                                >
                                    <Heroicon name="EyeIcon" class="h-4 w-4" />
                                </Link>

                                <button
                                    v-if="Number(sale.due_amount) > 0 && sale.status !== 'cancelled' && hasPermission('sales.payment')"
                                    @click="openPaymentModal(sale)"
                                    class="p-1 text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 rounded-md hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
                                    title="Record Customer Payment"
                                >
                                    <Heroicon name="BanknotesIcon" class="h-4 w-4" />
                                </button>

                                <button
                                    v-if="sale.status !== 'cancelled' && hasPermission('sales.cancel')"
                                    @click="cancelSale(sale)"
                                    class="p-1 text-amber-600 hover:text-amber-500 dark:text-amber-400 rounded-md hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors"
                                    title="Cancel Sale"
                                >
                                    <Heroicon name="XCircleIcon" class="h-4 w-4" />
                                </button>

                                <button
                                    v-if="hasPermission('sales.delete')"
                                    @click="deleteSale(sale)"
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
                <AppPagination :links="sales.links" />
            </div>
        </AppCard>

        <!-- Record Payment Modal -->
        <AppModal
            :show="paymentSale !== null"
            title="Record Customer Payment"
            @close="paymentSale = null"
        >
            <form @submit.prevent="submitPayment" class="space-y-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                        Recording payment for Invoice <strong>#{{ paymentSale?.invoice_number }}</strong>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Total: <strong>${{ formatCurrency(paymentSale?.grand_total || 0) }}</strong> | Paid: <strong>${{ formatCurrency(paymentSale?.paid_amount || 0) }}</strong> | Outstanding Due: <strong class="text-red-600 dark:text-red-400">${{ formatCurrency(paymentSale?.due_amount || 0) }}</strong>
                    </p>
                    <AppInput
                        label="Payment Amount ($)"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="paymentSale?.due_amount"
                        v-model.number="paymentForm.amount"
                        :error="paymentForm.errors.amount"
                        required
                    />
                </div>
                <div>
                    <AppSelect
                        label="Payment Method"
                        v-model="paymentForm.payment_method"
                        :options="[
                            { value: 'cash', label: 'Cash' },
                            { value: 'card', label: 'Credit/Debit Card' },
                            { value: 'bank_transfer', label: 'Bank Transfer' },
                            { value: 'other', label: 'Other' }
                        ]"
                        required
                    />
                </div>
                <div>
                    <AppInput
                        label="Reference / Transaction #"
                        v-model="paymentForm.reference_number"
                        :error="paymentForm.errors.reference_number"
                    />
                </div>
                <div>
                    <AppInput
                        label="Payment Notes"
                        v-model="paymentForm.notes"
                        :error="paymentForm.errors.notes"
                    />
                </div>
                <div class="flex justify-end space-x-2 pt-3">
                    <AppButton variant="secondary" @click="paymentSale = null">Cancel</AppButton>
                    <AppButton variant="primary" :loading="paymentForm.processing" @click="submitPayment">Save Payment</AppButton>
                </div>
            </form>
        </AppModal>
    </AppLayout>
</template>