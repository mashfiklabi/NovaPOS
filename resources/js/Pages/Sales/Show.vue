<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { PageProps, Sale } from '@/types';
import { formatCurrency, formatDate } from '@/Composables/useFormatters';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppButton from '@/Components/AppButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';

const props = defineProps<{
    sale: Sale;
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.auth.permissions || page.props.auth.user?.permissions || []);
const roles = computed(() => page.props.auth.user?.roles || []);
const isSuperAdmin = computed(() => roles.value.includes('Super Admin'));

const hasPermission = (permission: string) => {
    if (isSuperAdmin.value) return true;
    return permissions.value.includes(permission);
};

const isPaymentModalOpen = ref(false);
const paymentForm = useForm({
    amount: Number(props.sale.due_amount),
    payment_method: 'cash',
    reference_number: '',
    notes: '',
});

const submitPayment = () => {
    paymentForm.post(`/sales/${props.sale.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => {
            isPaymentModalOpen.value = false;
        },
        onError: (err) => {
            if (err.error) alert(err.error);
        }
    });
};

const cancelSale = () => {
    if (confirm(`Are you sure you want to CANCEL sale invoice #${props.sale.invoice_number}?`)) {
        router.post(`/sales/${props.sale.id}/cancel`, {}, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const deleteSale = () => {
    if (confirm(`Are you sure you want to move sale invoice #${props.sale.invoice_number} to Trash?`)) {
        router.delete(`/sales/${props.sale.id}`);
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`Sale Invoice ${sale.invoice_number}`" />

        <PageHeader :title="`Invoice: ${sale.invoice_number}`" :breadcrumbs="[{ name: 'Sales', href: '/sales' }, { name: sale.invoice_number }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <Link
                        href="/sales"
                        class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                    >
                        Back to Sales
                    </Link>

                    <AppButton
                        v-if="Number(sale.due_amount) > 0 && sale.status !== 'cancelled' && hasPermission('sales.payment')"
                        variant="primary"
                        size="sm"
                        class="!bg-emerald-600 hover:!bg-emerald-500"
                        @click="isPaymentModalOpen = true"
                    >
                        Record Payment
                    </AppButton>

                    <AppButton
                        v-if="sale.status !== 'cancelled' && hasPermission('sales.cancel')"
                        variant="secondary"
                        size="sm"
                        class="!text-amber-600"
                        @click="cancelSale"
                    >
                        Cancel Invoice
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('sales.delete')"
                        variant="danger"
                        size="sm"
                        @click="deleteSale"
                    >
                        Move to Trash
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Details & Items -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Info -->
                <AppCard title="Customer & Transaction Details">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Customer</span>
                            <div class="font-bold text-gray-900 dark:text-gray-100 text-base">
                                {{ sale.customer ? sale.customer.name : 'Walk-in Customer' }}
                            </div>
                            <div v-if="sale.customer?.email" class="text-gray-500 text-xs">
                                {{ sale.customer.email }}
                            </div>
                            <div v-if="sale.customer?.phone" class="text-gray-500 text-xs">
                                {{ sale.customer.phone }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Dates & Creator</span>
                            <div class="text-gray-700 dark:text-gray-300">
                                <strong>Sale Date:</strong> {{ formatDate(sale.sale_date) }}
                            </div>
                            <div v-if="sale.reference_number" class="text-gray-700 dark:text-gray-300">
                                <strong>Ref #:</strong> {{ sale.reference_number }}
                            </div>
                            <div class="text-gray-500 text-xs mt-2">
                                Billed by {{ sale.user ? sale.user.name : 'Staff' }} on {{ formatDate(sale.created_at) }}
                            </div>
                        </div>
                    </div>
                </AppCard>

                <!-- Line Items Table -->
                <AppCard title="Purchased Items">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 px-3">Product</th>
                                    <th class="py-3 px-3 text-right">Qty</th>
                                    <th class="py-3 px-3 text-right">Unit Price</th>
                                    <th class="py-3 px-3 text-right">Discount</th>
                                    <th class="py-3 px-3 text-right">Tax</th>
                                    <th class="py-3 px-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="item in sale.items" :key="item.id">
                                    <td class="py-3 px-3 font-medium text-gray-900 dark:text-gray-100">
                                        <div>{{ item.product ? item.product.name : 'Product #' + item.product_id }}</div>
                                        <div v-if="item.product?.code" class="text-xs text-gray-400">Code: {{ item.product.code }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-700 dark:text-gray-300 font-semibold">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">
                                        {{ formatCurrency(item.unit_price) }}
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-500">
                                        {{ formatCurrency(item.discount_amount) }}
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-500">
                                        {{ formatCurrency(item.tax_amount) }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(item.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>

                <!-- Payment History Table -->
                <AppCard title="Payment Records History">
                    <div v-if="!sale.payments || sale.payments.length === 0" class="text-sm text-gray-500 py-2">
                        No payment records stored.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 px-3">Date</th>
                                    <th class="py-3 px-3">Method</th>
                                    <th class="py-3 px-3">Ref #</th>
                                    <th class="py-3 px-3">Recorded By</th>
                                    <th class="py-3 px-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="pay in sale.payments" :key="pay.id">
                                    <td class="py-3 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        {{ formatDate(pay.paid_at) }}
                                    </td>
                                    <td class="py-3 px-3 capitalize font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        {{ pay.payment_method }}
                                    </td>
                                    <td class="py-3 px-3 text-gray-500 whitespace-nowrap">
                                        {{ pay.reference_number || '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-gray-500 whitespace-nowrap">
                                        {{ pay.user ? pay.user.name : 'System' }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                        {{ formatCurrency(pay.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>

                <AppCard v-if="sale.notes" title="Notes">
                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ sale.notes }}</p>
                </AppCard>
            </div>

            <!-- Right 1 Col: Status & Financials -->
            <div class="space-y-6">
                <AppCard title="Invoice Status">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Sale Status:</span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold capitalize"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': sale.status === 'draft',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': sale.status === 'completed',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': sale.status === 'cancelled'
                                }"
                            >
                                {{ sale.status }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Status:</span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold capitalize"
                                :class="{
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': sale.payment_status === 'unpaid',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': sale.payment_status === 'partial',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': sale.payment_status === 'paid'
                                }"
                            >
                                {{ sale.payment_status }}
                            </span>
                        </div>
                    </div>
                </AppCard>

                <AppCard title="Financial Summary">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Subtotal:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(sale.subtotal) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Discount:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">-{{ formatCurrency(sale.discount_amount) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Tax:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">+{{ formatCurrency(sale.tax_amount) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Shipping Cost:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">+{{ formatCurrency(sale.shipping_cost) }}</span>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between font-bold text-base text-gray-900 dark:text-gray-100">
                            <span>Grand Total:</span>
                            <span class="text-indigo-600 dark:text-indigo-400">{{ formatCurrency(sale.grand_total) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Paid Amount:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(sale.paid_amount) }}</span>
                        </div>

                        <div class="flex justify-between font-bold text-red-600 dark:text-red-400 border-t border-gray-100 dark:border-gray-800 pt-2">
                            <span>Balance Due:</span>
                            <span>{{ formatCurrency(sale.due_amount) }}</span>
                        </div>
                    </div>
                </AppCard>
            </div>
        </div>

        <!-- Record Payment Modal -->
        <AppModal
            :show="isPaymentModalOpen"
            title="Record Customer Payment"
            @close="isPaymentModalOpen = false"
        >
            <form @submit.prevent="submitPayment" class="space-y-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                        Recording payment for Invoice <strong>#{{ sale.invoice_number }}</strong>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Total: <strong>{{ formatCurrency(sale.grand_total) }}</strong> | Paid: <strong>{{ formatCurrency(sale.paid_amount) }}</strong> | Balance Due: <strong class="text-red-600 dark:text-red-400">{{ formatCurrency(sale.due_amount) }}</strong>
                    </p>
                    <AppInput
                        label="Payment Amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="sale.due_amount"
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
                    <AppButton variant="secondary" @click="isPaymentModalOpen = false">Cancel</AppButton>
                    <AppButton variant="primary" :loading="paymentForm.processing" @click="submitPayment">Save Payment</AppButton>
                </div>
            </form>
        </AppModal>
    </AppLayout>
</template>