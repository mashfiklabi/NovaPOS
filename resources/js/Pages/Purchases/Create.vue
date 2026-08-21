<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppButton from '@/Components/AppButton.vue';

interface SupplierOption {
    id: number;
    name: string;
    company_name: string | null;
}

interface ProductOption {
    id: number;
    name: string;
    sku: string;
    cost_price: string | number;
    current_stock: string | number;
    allow_decimal: boolean;
    tax_type: string;
    tax_rate: string | number;
}

interface PurchaseItemRow {
    product_id: number;
    product_name: string;
    allow_decimal: boolean;
    quantity: number;
    unit_cost: number;
    discount_amount: number;
    tax_amount: number;
}

const props = defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
}>();

const selectedProductId = ref<string | number>('');

const form = useForm({
    supplier_id: '' as string | number,
    purchase_date: new Date().toISOString().substring(0, 10),
    expected_delivery_date: '',
    status: 'draft',
    items: [] as PurchaseItemRow[],
    discount_amount: 0,
    tax_amount: 0,
    shipping_cost: 0,
    paid_amount: 0,
    notes: '',
});

const addItem = () => {
    if (!selectedProductId.value) return;

    const prod = props.products.find(p => p.id === Number(selectedProductId.value));
    if (!prod) return;

    // Avoid duplicate item row or increment qty
    const existingIndex = form.items.findIndex(item => item.product_id === prod.id);
    if (existingIndex > -1) {
        form.items[existingIndex].quantity += 1;
    } else {
        form.items.push({
            product_id: prod.id,
            product_name: prod.name,
            allow_decimal: prod.allow_decimal,
            quantity: 1,
            unit_cost: Number(prod.cost_price),
            discount_amount: 0,
            tax_amount: 0,
        });
    }

    selectedProductId.value = '';
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const calculateLineTotal = (item: PurchaseItemRow) => {
    const total = (item.quantity * item.unit_cost) - item.discount_amount + item.tax_amount;
    return total > 0 ? total : 0;
};

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + calculateLineTotal(item), 0);
});

const grandTotal = computed(() => {
    const total = subtotal.value - Number(form.discount_amount) + Number(form.tax_amount) + Number(form.shipping_cost);
    return total > 0 ? total : 0;
});

const dueAmount = computed(() => {
    const due = grandTotal.value - Number(form.paid_amount);
    return due > 0 ? due : 0;
});

const submit = () => {
    form.post('/purchases', {
        onError: (err) => {
            if (err.error) alert(err.error);
        }
    });
};

const formatCurrency = (amount: number) => {
    return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <AppLayout>
        <Head title="Create Purchase Order" />

        <PageHeader title="Create Purchase Order" :breadcrumbs="[{ name: 'Purchases', href: '/purchases' }, { name: 'Create' }]">
            <template #actions>
                <Link
                    href="/purchases"
                    class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back to List
                </Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Supplier & Items List (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <AppCard title="Order Information">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <AppSelect
                                label="Supplier"
                                v-model="form.supplier_id"
                                :options="[
                                    { value: '', label: '-- Select Supplier --' },
                                    ...suppliers.map(s => ({ value: s.id, label: s.company_name ? `${s.name} (${s.company_name})` : s.name }))
                                ]"
                                :error="form.errors.supplier_id"
                                required
                            />
                        </div>

                        <div>
                            <AppSelect
                                label="Initial Status"
                                v-model="form.status"
                                :options="[
                                    { value: 'draft', label: 'Draft' },
                                    { value: 'received', label: 'Received (Increments Stock)' }
                                ]"
                                :error="form.errors.status"
                                required
                            />
                        </div>

                        <div>
                            <AppInput
                                label="Purchase Date"
                                type="date"
                                v-model="form.purchase_date"
                                :error="form.errors.purchase_date"
                                required
                            />
                        </div>

                        <div>
                            <AppInput
                                label="Expected Delivery Date"
                                type="date"
                                v-model="form.expected_delivery_date"
                                :error="form.errors.expected_delivery_date"
                            />
                        </div>
                    </div>
                </AppCard>

                <!-- Line Items Builder -->
                <AppCard title="Purchase Items">
                    <!-- Product selector bar -->
                    <div class="flex items-center space-x-3 mb-5">
                        <div class="flex-1">
                            <AppSelect
                                v-model="selectedProductId"
                                :options="[
                                    { value: '', label: '-- Select Product to Add --' },
                                    ...products.map(p => ({ value: p.id, label: `${p.name} (SKU: ${p.sku}) - Stock: ${p.current_stock}` }))
                                ]"
                            />
                        </div>
                        <AppButton
                            type="button"
                            variant="primary"
                            :disabled="!selectedProductId"
                            @click="addItem"
                        >
                            Add Item
                        </AppButton>
                    </div>

                    <div v-if="form.errors.items" class="mb-3 text-xs text-red-600 font-semibold">
                        {{ form.errors.items }}
                    </div>

                    <div v-if="form.items.length === 0" class="text-center py-8 text-sm text-gray-500 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-lg">
                        No items added to this purchase order yet. Select a product above.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-2 px-2">Product</th>
                                    <th class="py-2 px-2 w-28">Quantity</th>
                                    <th class="py-2 px-2 w-28">Unit Cost ($)</th>
                                    <th class="py-2 px-2 w-24">Discount ($)</th>
                                    <th class="py-2 px-2 w-24">Tax ($)</th>
                                    <th class="py-2 px-2 w-28 text-right">Total ($)</th>
                                    <th class="py-2 px-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                                <tr v-for="(item, idx) in form.items" :key="item.product_id">
                                    <td class="py-2 px-2 font-medium text-gray-900 dark:text-gray-100">
                                        {{ item.product_name }}
                                    </td>
                                    <td class="py-2 px-2">
                                        <input
                                            type="number"
                                            :step="item.allow_decimal ? '0.001' : '1'"
                                            :min="item.allow_decimal ? '0.001' : '1'"
                                            v-model.number="item.quantity"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-800 dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </td>
                                    <td class="py-2 px-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            v-model.number="item.unit_cost"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-800 dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </td>
                                    <td class="py-2 px-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            v-model.number="item.discount_amount"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-800 dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </td>
                                    <td class="py-2 px-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            v-model.number="item.tax_amount"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-800 dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </td>
                                    <td class="py-2 px-2 text-right font-bold text-gray-900 dark:text-gray-100">
                                        ${{ formatCurrency(calculateLineTotal(item)) }}
                                    </td>
                                    <td class="py-2 px-2 text-center">
                                        <button
                                            type="button"
                                            @click="removeItem(idx)"
                                            class="text-red-500 hover:text-red-700 font-bold"
                                            title="Remove Item"
                                        >
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>

                <AppCard title="Notes & Terms">
                    <AppTextarea label="Order Notes / Terms" v-model="form.notes" :rows="3" placeholder="Additional notes or supplier terms..." />
                </AppCard>
            </div>

            <!-- Right: Financial Summary (1 col) -->
            <div class="space-y-6">
                <AppCard title="Financial Summary">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center text-sm font-medium text-gray-600 dark:text-gray-400">
                            <span>Subtotal:</span>
                            <span class="font-bold text-gray-900 dark:text-gray-100">${{ formatCurrency(subtotal) }}</span>
                        </div>

                        <div>
                            <AppInput
                                label="Header Discount ($)"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="form.discount_amount"
                                :error="form.errors.discount_amount"
                            />
                        </div>

                        <div>
                            <AppInput
                                label="Header Tax ($)"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="form.tax_amount"
                                :error="form.errors.tax_amount"
                            />
                        </div>

                        <div>
                            <AppInput
                                label="Shipping Cost ($)"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="form.shipping_cost"
                                :error="form.errors.shipping_cost"
                            />
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between items-center text-base font-bold text-gray-900 dark:text-gray-100">
                            <span>Grand Total:</span>
                            <span class="text-indigo-600 dark:text-indigo-400 text-lg">${{ formatCurrency(grandTotal) }}</span>
                        </div>

                        <div>
                            <AppInput
                                label="Paid Amount ($)"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="form.paid_amount"
                                :error="form.errors.paid_amount"
                            />
                        </div>

                        <div class="flex justify-between items-center text-sm font-medium text-red-600 dark:text-red-400">
                            <span>Due Amount:</span>
                            <span class="font-bold">${{ formatCurrency(dueAmount) }}</span>
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                            <AppButton
                                type="submit"
                                variant="primary"
                                class="w-full justify-center"
                                :loading="form.processing"
                                :disabled="form.items.length === 0"
                            >
                                Save Purchase Order
                            </AppButton>
                        </div>
                    </div>
                </AppCard>
            </div>
        </form>
    </AppLayout>
</template>
