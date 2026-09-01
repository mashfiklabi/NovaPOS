<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import { formatCurrency, formatDate } from '@/Composables/useFormatters';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppButton from '@/Components/AppButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppInput from '@/Components/AppInput.vue';

interface Product {
    id: number;
    name: string;
    sku: string;
}

interface PurchaseItem {
    id: number;
    product_id: number;
    quantity: string | number;
    unit_cost: string | number;
    discount_amount: string | number;
    tax_amount: string | number;
    total: string | number;
    product?: Product | null;
}

interface Supplier {
    id: number;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
}

interface User {
    id: number;
    name: string;
}

interface Purchase {
    id: number;
    uuid: string;
    po_number: string;
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
    notes: string | null;
    created_at: string;
    supplier?: Supplier | null;
    creator?: User | null;
    items: PurchaseItem[];
}

const props = defineProps<{
    purchase: Purchase;
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
    amount: Number(props.purchase.due_amount),
});

const submitPayment = () => {
    paymentForm.post(`/purchases/${props.purchase.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => {
            isPaymentModalOpen.value = false;
        },
        onError: (err) => {
            if (err.error) alert(err.error);
        }
    });
};

const receivePurchase = () => {
    if (confirm(`Are you sure you want to mark PO #${props.purchase.po_number} as RECEIVED? Stock levels will be incremented.`)) {
        router.post(`/purchases/${props.purchase.id}/receive`, {}, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const cancelPurchase = () => {
    if (confirm(`Are you sure you want to CANCEL PO #${props.purchase.po_number}?`)) {
        router.post(`/purchases/${props.purchase.id}/cancel`, {}, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const deletePurchase = () => {
    if (confirm(`Are you sure you want to move PO #${props.purchase.po_number} to Trash?`)) {
        router.delete(`/purchases/${props.purchase.id}`);
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`Purchase Order ${purchase.po_number}`" />

        <PageHeader :title="`Purchase Order: ${purchase.po_number}`" :breadcrumbs="[{ name: 'Purchases', href: '/purchases' }, { name: purchase.po_number }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <Link
                        href="/purchases"
                        class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                    >
                        Back to List
                    </Link>

                    <AppButton
                        v-if="Number(purchase.due_amount) > 0 && hasPermission('purchases.update')"
                        variant="primary"
                        size="sm"
                        class="!bg-emerald-600 hover:!bg-emerald-500"
                        @click="isPaymentModalOpen = true"
                    >
                        Record Payment
                    </AppButton>

                    <Link
                        v-if="purchase.status === 'draft' && hasPermission('purchases.update')"
                        :href="`/purchases/${purchase.id}/edit`"
                        class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500"
                    >
                        Edit Order
                    </Link>

                    <AppButton
                        v-if="purchase.status === 'draft' && hasPermission('purchases.receive')"
                        variant="primary"
                        size="sm"
                        class="!bg-green-600 hover:!bg-green-500"
                        @click="receivePurchase"
                    >
                        Mark Received
                    </AppButton>

                    <AppButton
                        v-if="purchase.status === 'draft' && hasPermission('purchases.cancel')"
                        variant="secondary"
                        size="sm"
                        class="!text-yellow-600"
                        @click="cancelPurchase"
                    >
                        Cancel PO
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('purchases.delete')"
                        variant="danger"
                        size="sm"
                        @click="deletePurchase"
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
                <!-- Supplier Info -->
                <AppCard title="Supplier & Delivery Details">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Supplier</span>
                            <div class="font-bold text-gray-900 dark:text-gray-100 text-base">
                                {{ purchase.supplier ? purchase.supplier.name : 'Unknown Supplier' }}
                            </div>
                            <div v-if="purchase.supplier?.company_name" class="text-gray-600 dark:text-gray-400">
                                {{ purchase.supplier.company_name }}
                            </div>
                            <div v-if="purchase.supplier?.email" class="text-gray-500 text-xs">
                                {{ purchase.supplier.email }}
                            </div>
                            <div v-if="purchase.supplier?.phone" class="text-gray-500 text-xs">
                                {{ purchase.supplier.phone }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Dates & Creator</span>
                            <div class="text-gray-700 dark:text-gray-300">
                                <strong>Order Date:</strong> {{ formatDate(purchase.purchase_date) }}
                            </div>
                            <div class="text-gray-700 dark:text-gray-300">
                                <strong>Expected Delivery:</strong> {{ formatDate(purchase.expected_delivery_date) }}
                            </div>
                            <div class="text-gray-500 text-xs mt-2">
                                Created by {{ purchase.creator ? purchase.creator.name : 'System' }} on {{ formatDate(purchase.created_at) }}
                            </div>
                        </div>
                    </div>
                </AppCard>

                <!-- Items Table -->
                <AppCard title="Purchased Items">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 px-3">Product</th>
                                    <th class="py-3 px-3 text-right">Qty</th>
                                    <th class="py-3 px-3 text-right">Unit Cost</th>
                                    <th class="py-3 px-3 text-right">Discount</th>
                                    <th class="py-3 px-3 text-right">Tax</th>
                                    <th class="py-3 px-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="item in purchase.items" :key="item.id">
                                    <td class="py-3 px-3 font-medium text-gray-900 dark:text-gray-100">
                                        <div>{{ item.product ? item.product.name : 'Product #' + item.product_id }}</div>
                                        <div v-if="item.product?.sku" class="text-xs text-gray-400">SKU: {{ item.product.sku }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-700 dark:text-gray-300 font-semibold">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">
                                        {{ formatCurrency(item.unit_cost) }}
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

                <AppCard v-if="purchase.notes" title="Order Notes">
                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ purchase.notes }}</p>
                </AppCard>
            </div>

            <!-- Right 1 Col: Status & Financials -->
            <div class="space-y-6">
                <AppCard title="Order Status">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Status:</span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold capitalize"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': purchase.status === 'draft',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': purchase.status === 'received',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': purchase.status === 'cancelled'
                                }"
                            >
                                {{ purchase.status }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Status:</span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold capitalize"
                                :class="{
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': purchase.payment_status === 'unpaid',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': purchase.payment_status === 'partial',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': purchase.payment_status === 'paid'
                                }"
                            >
                                {{ purchase.payment_status }}
                            </span>
                        </div>
                    </div>
                </AppCard>

                <AppCard title="Financial Summary">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Items Subtotal:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(purchase.subtotal) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Header Discount:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">-{{ formatCurrency(purchase.discount_amount) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Header Tax:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">+{{ formatCurrency(purchase.tax_amount) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Shipping Cost:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">+{{ formatCurrency(purchase.shipping_cost) }}</span>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between font-bold text-base text-gray-900 dark:text-gray-100">
                            <span>Grand Total:</span>
                            <span class="text-indigo-600 dark:text-indigo-400">{{ formatCurrency(purchase.grand_total) }}</span>
                        </div>

                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Paid Amount:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(purchase.paid_amount) }}</span>
                        </div>

                        <div class="flex justify-between font-bold text-red-600 dark:text-red-400 border-t border-gray-100 dark:border-gray-800 pt-2">
                            <span>Balance Due:</span>
                            <span>{{ formatCurrency(purchase.due_amount) }}</span>
                        </div>
                    </div>
                </AppCard>
            </div>
        </div>

        <!-- Record Payment Modal -->
        <AppModal
            :show="isPaymentModalOpen"
            title="Record Purchase Payment"
            @close="isPaymentModalOpen = false"
        >
            <form @submit.prevent="submitPayment" class="space-y-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                        Recording payment for PO <strong>#{{ purchase.po_number }}</strong>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Total: <strong>{{ formatCurrency(purchase.grand_total) }}</strong> | Paid: <strong>{{ formatCurrency(purchase.paid_amount) }}</strong> | Balance Due: <strong class="text-red-600 dark:text-red-400">{{ formatCurrency(purchase.due_amount) }}</strong>
                    </p>
                    <AppInput
                        label="Payment Amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="purchase.due_amount"
                        v-model.number="paymentForm.amount"
                        :error="paymentForm.errors.amount"
                        required
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
