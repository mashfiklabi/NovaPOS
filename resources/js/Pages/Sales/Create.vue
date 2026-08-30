<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { PageProps, POSProduct, Customer } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import Heroicon from '@/Components/Heroicon.vue';

interface CartItem {
    product: POSProduct;
    quantity: number;
    discount_amount: number;
    tax_amount: number;
}

const props = defineProps<{
    customers: Array<{ id: number; name: string; phone: string | null }>;
    products: POSProduct[];
}>();

// Search & Barcode state
const searchQuery = ref('');
const barcodeQuery = ref('');
const barcodeInputRef = ref<HTMLInputElement | null>(null);

// Customer selection
const selectedCustomerId = ref<number | null>(null);

// Cart State
const cart = ref<CartItem[]>([]);
const headerDiscount = ref<number>(0);
const headerTax = ref<number>(0);
const shippingCost = ref<number>(0);
const paidAmount = ref<number>(0);
const paymentMethod = ref<string>('cash');
const notes = ref<string>('');

// Focus USB Barcode input on mount
onMounted(() => {
    nextTick(() => {
        barcodeInputRef.value?.focus();
    });
});

// Filtered products list for product search grid
const filteredProducts = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.products;
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.sku.toLowerCase().includes(q) ||
        (p.barcode && p.barcode.toLowerCase().includes(q))
    );
});

// Barcode scan handler
const handleBarcodeScan = () => {
    const code = barcodeQuery.value.trim();
    if (!code) return;

    const matchedProduct = props.products.find(p =>
        (p.barcode && p.barcode.toLowerCase() === code.toLowerCase()) ||
        p.sku.toLowerCase() === code.toLowerCase()
    );

    if (matchedProduct) {
        addToCart(matchedProduct);
    } else {
        alert(`No product found matching barcode/SKU "${code}"`);
    }

    barcodeQuery.value = '';
    barcodeInputRef.value?.focus();
};

// Add product to cart
const addToCart = (product: POSProduct) => {
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id);
    if (existingIndex > -1) {
        const item = cart.value[existingIndex];
        const step = product.allow_decimal ? 1 : 1;
        updateQuantity(existingIndex, item.quantity + step);
    } else {
        const initialQty = 1;
        cart.value.push({
            product,
            quantity: initialQty,
            discount_amount: 0,
            tax_amount: 0,
        });
    }
};

// Quantity update with strict decimal rule validation
const updateQuantity = (index: number, newQty: number) => {
    const item = cart.value[index];
    if (newQty <= 0) {
        removeFromCart(index);
        return;
    }

    if (!item.product.allow_decimal) {
        if (!Number.isInteger(newQty)) {
            alert(`Product "${item.product.name}" does not allow decimal quantities.`);
            return;
        }
    }

    const currentStock = Number(item.product.current_stock);
    if (newQty > currentStock) {
        alert(`Warning: Requested quantity (${newQty}) exceeds current available stock (${currentStock}) for "${item.product.name}".`);
    }

    item.quantity = newQty;
};

// Remove cart item
const removeFromCart = (index: number) => {
    cart.value.splice(index, 1);
};

// Clear entire cart
const clearCart = () => {
    if (cart.value.length === 0) return;
    if (confirm('Are you sure you want to clear the cashier cart?')) {
        cart.value = [];
        headerDiscount.value = 0;
        headerTax.value = 0;
        shippingCost.value = 0;
        paidAmount.value = 0;
    }
};

// Financial Computations
const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        const price = Number(item.product.selling_price);
        const lineSub = item.quantity * price;
        const lineTotal = lineSub - (item.discount_amount || 0) + (item.tax_amount || 0);
        return sum + Math.max(0, lineTotal);
    }, 0);
});

const grandTotal = computed(() => {
    const total = subtotal.value - (headerDiscount.value || 0) + (headerTax.value || 0) + (shippingCost.value || 0);
    return Math.max(0, Math.round(total * 100) / 100);
});

// Auto-fill paidAmount to match grandTotal unless explicitly edited
watch(grandTotal, (newTotal) => {
    paidAmount.value = newTotal;
}, { immediate: true });

const dueAmount = computed(() => {
    const due = grandTotal.value - (paidAmount.value || 0);
    return Math.max(0, Math.round(due * 100) / 100);
});

// Inertia Form Submission
const form = useForm({
    customer_id: null as number | null,
    sale_date: new Date().toISOString().split('T')[0],
    items: [] as Array<{
        product_id: number;
        quantity: number;
        unit_price?: number;
        discount_amount?: number;
        tax_amount?: number;
    }>,
    discount_amount: 0,
    tax_amount: 0,
    shipping_cost: 0,
    paid_amount: 0,
    payment_method: 'cash',
    status: 'completed',
    notes: '',
});

const submitSale = () => {
    if (cart.value.length === 0) {
        alert('Cannot complete sale with an empty cart.');
        return;
    }

    if (paidAmount.value > grandTotal.value) {
        alert(`Paid amount ($${paidAmount.value}) cannot exceed grand total ($${grandTotal.value}).`);
        return;
    }

    form.customer_id = selectedCustomerId.value;
    form.discount_amount = headerDiscount.value || 0;
    form.tax_amount = headerTax.value || 0;
    form.shipping_cost = shippingCost.value || 0;
    form.paid_amount = paidAmount.value || 0;
    form.payment_method = paymentMethod.value;
    form.notes = notes.value;

    form.items = cart.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity,
        discount_amount: item.discount_amount || 0,
        tax_amount: item.tax_amount || 0,
    }));

    form.post('/sales', {
        onError: (errors) => {
            if (errors.error) alert(errors.error);
        },
    });
};

const formatCurrency = (amount: number) => {
    return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <AppLayout>
        <Head title="POS Cashier Terminal" />

        <PageHeader title="Point of Sale (POS)" :breadcrumbs="[{ name: 'Sales', href: '/sales' }, { name: 'Terminal' }]">
            <template #actions>
                <Link
                    href="/sales"
                    class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                >
                    Sales History
                </Link>
            </template>
        </PageHeader>

        <!-- Main POS Terminal Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Product Catalog & Search (7 Cols) -->
            <div class="lg:col-span-7 space-y-4">
                <AppCard no-padding>
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800 space-y-3">
                        <!-- Barcode Scanner Input -->
                        <form @submit.prevent="handleBarcodeScan" class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Heroicon name="QrCodeIcon" class="h-5 w-5 text-indigo-500" />
                            </div>
                            <input
                                ref="barcodeInputRef"
                                v-model="barcodeQuery"
                                type="text"
                                placeholder="Scan USB Barcode / Enter Product SKU & Press Enter..."
                                class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-indigo-200 dark:border-indigo-900 bg-indigo-50/50 dark:bg-indigo-950/20 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </form>

                        <!-- Product Search Input -->
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Heroicon name="MagnifyingGlassIcon" class="h-4 w-4 text-gray-400" />
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search products by name..."
                                class="w-full pl-9 pr-4 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <div class="p-4 max-h-[550px] overflow-y-auto">
                        <div v-if="filteredProducts.length === 0" class="text-center py-8 text-gray-500 text-sm">
                            No products found matching "{{ searchQuery }}".
                        </div>

                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <button
                                v-for="product in filteredProducts"
                                :key="product.id"
                                @click="addToCart(product)"
                                class="flex flex-col justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-indigo-500 dark:hover:border-indigo-500 hover:shadow-md transition-all text-left group"
                            >
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-gray-100 text-xs line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-0.5 font-mono">
                                        SKU: {{ product.sku }}
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2">
                                    <div class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400">
                                        ${{ formatCurrency(Number(product.selling_price)) }}
                                    </div>
                                    <div
                                        class="text-[10px] px-1.5 py-0.5 rounded font-semibold"
                                        :class="Number(product.current_stock) > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400'"
                                    >
                                        Stock: {{ product.current_stock }} {{ product.unit?.short_name || '' }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- RIGHT COLUMN: Customer, Cart & Checkout (5 Cols) -->
            <div class="lg:col-span-5 space-y-4">

                <!-- Customer Selector -->
                <AppCard title="Customer">
                    <div class="flex items-center space-x-2">
                        <select
                            v-model="selectedCustomerId"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-indigo-500"
                        >
                            <option :value="null">Walk-in Customer (Default)</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">
                                {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                            </option>
                        </select>
                    </div>
                </AppCard>

                <!-- Cart Table -->
                <AppCard no-padding>
                    <div class="p-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider flex items-center gap-1.5">
                            <Heroicon name="ShoppingCartIcon" class="h-4 w-4 text-indigo-500" />
                            Current Cart ({{ cart.length }})
                        </h3>
                        <button
                            @click="clearCart"
                            class="text-xs text-red-600 dark:text-red-400 hover:underline font-semibold"
                            :disabled="cart.length === 0"
                        >
                            Clear Cart
                        </button>
                    </div>

                    <div class="max-h-[300px] overflow-y-auto">
                        <div v-if="cart.length === 0" class="text-center py-10 text-gray-400 text-xs">
                            Cart is empty. Select or scan products to add.
                        </div>

                        <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                            <div v-for="(item, index) in cart" :key="item.product.id" class="p-3 flex items-center justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">
                                        {{ item.product.name }}
                                    </div>
                                    <div class="text-[10px] text-gray-500">
                                        ${{ formatCurrency(Number(item.product.selling_price)) }} / {{ item.product.unit?.short_name || 'unit' }}
                                    </div>
                                </div>

                                <!-- Quantity Controls -->
                                <div class="flex items-center space-x-1">
                                    <button
                                        @click="updateQuantity(index, item.quantity - (item.product.allow_decimal ? 1 : 1))"
                                        class="h-6 w-6 flex items-center justify-center rounded border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 text-xs font-bold"
                                    >
                                        -
                                    </button>
                                    <input
                                        type="number"
                                        :step="item.product.allow_decimal ? '0.001' : '1'"
                                        :min="item.product.allow_decimal ? '0.001' : '1'"
                                        v-model.number="item.quantity"
                                        @change="updateQuantity(index, item.quantity)"
                                        class="w-14 text-center text-xs py-0.5 px-1 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 font-bold"
                                    />
                                    <button
                                        @click="updateQuantity(index, item.quantity + (item.product.allow_decimal ? 1 : 1))"
                                        class="h-6 w-6 flex items-center justify-center rounded border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 text-xs font-bold"
                                    >
                                        +
                                    </button>
                                </div>

                                <!-- Line total -->
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 w-16 text-right">
                                    ${{ formatCurrency(item.quantity * Number(item.product.selling_price)) }}
                                </div>

                                <!-- Delete item button -->
                                <button
                                    @click="removeFromCart(index)"
                                    class="p-1 text-gray-400 hover:text-red-600 transition-colors"
                                >
                                    <Heroicon name="TrashIcon" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Summary & Checkout Footer -->
                    <div class="p-4 bg-gray-50/80 dark:bg-gray-900/80 border-t border-gray-200 dark:border-gray-800 space-y-3">
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Subtotal:</span>
                                <span class="font-bold text-gray-900 dark:text-gray-100">${{ formatCurrency(subtotal) }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div>
                                    <label class="text-[10px] text-gray-500 block">Discount ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model.number="headerDiscount"
                                        class="w-full text-xs py-1 px-2 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900"
                                    />
                                </div>
                                <div>
                                    <label class="text-[10px] text-gray-500 block">Tax ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model.number="headerTax"
                                        class="w-full text-xs py-1 px-2 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Grand Total Display -->
                        <div class="p-3 bg-indigo-600 rounded-xl text-white flex items-center justify-between shadow-md">
                            <div>
                                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-200 block">Grand Total</span>
                                <span class="text-xl font-black">${{ formatCurrency(grandTotal) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-200 block">Balance Due</span>
                                <span class="text-sm font-bold" :class="dueAmount > 0 ? 'text-amber-300' : 'text-emerald-300'">
                                    ${{ formatCurrency(dueAmount) }}
                                </span>
                            </div>
                        </div>

                        <!-- Payment Options -->
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <div>
                                <label class="text-[10px] text-gray-500 block font-semibold mb-0.5">Method</label>
                                <select
                                    v-model="paymentMethod"
                                    class="w-full text-xs py-1.5 px-2 rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900"
                                >
                                    <option value="cash">Cash</option>
                                    <option value="card">Credit/Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 block font-semibold mb-0.5">Amount Paid ($)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :max="grandTotal"
                                    v-model.number="paidAmount"
                                    class="w-full text-xs py-1.5 px-2 rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 font-bold"
                                />
                            </div>
                        </div>

                        <!-- Complete Sale Action Button -->
                        <AppButton
                            variant="primary"
                            size="lg"
                            class="w-full justify-center !py-3 !text-sm !font-bold shadow-lg"
                            :loading="form.processing"
                            :disabled="cart.length === 0"
                            @click="submitSale"
                        >
                            COMPLETE SALE
                        </AppButton>
                    </div>
                </AppCard>
            </div>

        </div>
    </AppLayout>
</template>