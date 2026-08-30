<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { PageProps, POSProduct, Sale } from '@/types';
import { formatCurrency } from '@/Composables/useFormatters';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import Heroicon from '@/Components/Heroicon.vue';

interface EditableItem {
    product: POSProduct;
    quantity: number;
    discount_amount: number;
    tax_amount: number;
}

const props = defineProps<{
    sale: Sale;
    customers: Array<{ id: number; name: string; phone: string | null }>;
    products: POSProduct[];
}>();

const page = usePage<PageProps>();

// Initialize edit items from existing sale
const cart = ref<EditableItem[]>(
    (props.sale.items || []).map(item => {
        const prod = props.products.find(p => p.id === item.product_id) || {
            id: item.product_id,
            name: item.product?.name || `Product #${item.product_id}`,
            sku: item.product?.code || 'N/A',
            barcode: null,
            selling_price: item.unit_price,
            current_stock: 999,
            allow_decimal: true,
            tax_type: null,
            tax_rate: 0,
        };
        return {
            product: prod,
            quantity: Number(item.quantity),
            discount_amount: Number(item.discount_amount || 0),
            tax_amount: Number(item.tax_amount || 0),
        };
    })
);

const selectedCustomerId = ref<number | null>(props.sale.customer_id);
const headerDiscount = ref<number>(Number(props.sale.discount_amount || 0));
const headerTax = ref<number>(Number(props.sale.tax_amount || 0));
const shippingCost = ref<number>(Number(props.sale.shipping_cost || 0));
const notes = ref<string>(props.sale.notes || '');

const updateQuantity = (index: number, newQty: number) => {
    if (newQty <= 0) {
        cart.value.splice(index, 1);
        return;
    }
    cart.value[index].quantity = newQty;
};

const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        const lineSub = item.quantity * Number(item.product.selling_price);
        return sum + Math.max(0, lineSub);
    }, 0);
});

const grandTotal = computed(() => {
    const total = subtotal.value - (headerDiscount.value || 0) + (headerTax.value || 0) + (shippingCost.value || 0);
    return Math.max(0, Math.round(total * 100) / 100);
});

const form = useForm({
    customer_id: selectedCustomerId.value,
    sale_date: props.sale.sale_date,
    items: [] as Array<{
        product_id: number;
        quantity: number;
        discount_amount?: number;
        tax_amount?: number;
    }>,
    discount_amount: headerDiscount.value,
    tax_amount: headerTax.value,
    shipping_cost: shippingCost.value,
    notes: notes.value,
});

const submitEdit = () => {
    if (cart.value.length === 0) {
        alert('Cannot save a sale with 0 items.');
        return;
    }

    form.customer_id = selectedCustomerId.value;
    form.discount_amount = headerDiscount.value || 0;
    form.tax_amount = headerTax.value || 0;
    form.shipping_cost = shippingCost.value || 0;
    form.notes = notes.value;

    form.items = cart.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity,
        discount_amount: item.discount_amount || 0,
        tax_amount: item.tax_amount || 0,
    }));

    form.put(`/sales/${props.sale.id}`, {
        onError: (err) => {
            if (err.error) alert(err.error);
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head :title="`Edit Sale ${sale.invoice_number}`" />

        <PageHeader :title="`Edit Sale: ${sale.invoice_number}`" :breadcrumbs="[{ name: 'Sales', href: '/sales' }, { name: sale.invoice_number, href: `/sales/${sale.id}` }, { name: 'Edit' }]">
            <template #actions>
                <Link
                    :href="`/sales/${sale.id}`"
                    class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                >
                    Cancel / Back
                </Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Items & Form -->
            <div class="lg:col-span-2 space-y-6">
                <AppCard title="Sale Items & Quantities">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 px-3">Product</th>
                                    <th class="py-3 px-3 text-right">Qty</th>
                                    <th class="py-3 px-3 text-right">Unit Price</th>
                                    <th class="py-3 px-3 text-right">Total</th>
                                    <th class="py-3 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="(item, index) in cart" :key="item.product.id">
                                    <td class="py-3 px-3 font-medium text-gray-900 dark:text-gray-100">
                                        <div>{{ item.product.name }}</div>
                                        <div class="text-xs text-gray-400">SKU: {{ item.product.sku }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <input
                                            type="number"
                                            :step="item.product.allow_decimal ? '0.001' : '1'"
                                            v-model.number="item.quantity"
                                            @change="updateQuantity(index, item.quantity)"
                                            class="w-20 text-right text-xs py-1 px-2 rounded border-gray-300 dark:border-gray-700 font-bold"
                                        />
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">
                                        {{ formatCurrency(Number(item.product.selling_price)) }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(item.quantity * Number(item.product.selling_price)) }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <button @click="cart.splice(index, 1)" class="text-red-600 p-1 hover:bg-red-50 rounded">
                                            <Heroicon name="TrashIcon" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>

                <AppCard title="Sale Notes">
                    <AppInput label="Notes" v-model="notes" />
                </AppCard>
            </div>

            <!-- Customer & Totals -->
            <div class="space-y-6">
                <AppCard title="Customer Details">
                    <select
                        v-model="selectedCustomerId"
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-indigo-500"
                    >
                        <option :value="null">Walk-in Customer (Default)</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">
                            {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                        </option>
                    </select>
                </AppCard>

                <AppCard title="Order Summary">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Subtotal:</span>
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(subtotal) }}</span>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block">Header Discount ($)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="headerDiscount"
                                class="w-full text-xs py-1 px-2 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900"
                            />
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block">Header Tax ($)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="headerTax"
                                class="w-full text-xs py-1 px-2 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900"
                            />
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between font-bold text-base text-gray-900 dark:text-gray-100">
                            <span>Grand Total:</span>
                            <span class="text-indigo-600 dark:text-indigo-400">{{ formatCurrency(grandTotal) }}</span>
                        </div>

                        <AppButton
                            variant="primary"
                            size="lg"
                            class="w-full justify-center mt-4"
                            :loading="form.processing"
                            @click="submitEdit"
                        >
                            Save Changes
                        </AppButton>
                    </div>
                </AppCard>
            </div>
        </div>
    </AppLayout>
</template>