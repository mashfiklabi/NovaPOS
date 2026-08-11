<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
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

interface Category {
    id: number;
    name: string;
}

interface Brand {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
    short_name: string;
}

interface Product {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    sku: string;
    barcode: string | null;
    description: string | null;
    category_id: number | null;
    brand_id: number | null;
    unit_id: number;
    cost_price: string;
    selling_price: string;
    stock_alert_threshold: string;
    current_stock: string;
    image: string | null;
    status: string;
    track_stock: boolean;
    allow_decimal: boolean;
    tax_type: 'exclusive' | 'inclusive' | 'none';
    tax_rate: string | null;
    category: Category | null;
    brand: Brand | null;
    unit: Unit;
    deleted_at: string | null;
}

const props = defineProps<{
    products: {
        data: Product[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    categories: Category[];
    brands: Brand[];
    units: Unit[];
    filters: {
        search: string | null;
        status: string | null;
    };
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'active');

watch(search, (value) => {
    router.get('/products', { search: value, status: status.value }, {
        preserveState: true,
        replace: true,
    });
});

const setStatus = (val: string) => {
    status.value = val;
    selectedIds.value = [];
    router.get('/products', { search: search.value, status: val }, {
        preserveState: true,
        replace: true,
    });
};

const isDrawerOpen = ref(false);
const editingProduct = ref<Product | null>(null);

const form = useForm({
    _method: 'POST',
    name: '',
    sku: '',
    barcode: '',
    description: '',
    category_id: '' as string | number,
    brand_id: '' as string | number,
    unit_id: '' as string | number,
    cost_price: '',
    selling_price: '',
    stock_alert_threshold: '0.000',
    current_stock: '0.000',
    image: null as File | null,
    status: 'active',
    track_stock: '1' as string | number,
    allow_decimal: '0' as string | number,
    tax_type: 'none',
    tax_rate: '0.00',
});

const handleFile = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    if (files && files.length > 0) {
        form.image = files[0];
    }
};

const openCreateDrawer = () => {
    editingProduct.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.status = 'active';
    form.category_id = '';
    form.brand_id = '';
    form.unit_id = props.units.length > 0 ? props.units[0].id : '';
    form.cost_price = '';
    form.selling_price = '';
    form.stock_alert_threshold = '0.000';
    form.current_stock = '0.000';
    form.track_stock = '1';
    form.allow_decimal = '0';
    form.tax_type = 'none';
    form.tax_rate = '0.00';
    isDrawerOpen.value = true;
};

const openEditDrawer = (product: Product) => {
    editingProduct.value = product;
    form.clearErrors();
    form._method = 'POST'; // we override using POST with _method=PUT to support files with PUT
    form.name = product.name;
    form.sku = product.sku;
    form.barcode = product.barcode || '';
    form.description = product.description || '';
    form.category_id = product.category_id || '';
    form.brand_id = product.brand_id || '';
    form.unit_id = product.unit_id;
    form.cost_price = product.cost_price;
    form.selling_price = product.selling_price;
    form.stock_alert_threshold = product.stock_alert_threshold;
    form.current_stock = product.current_stock;
    form.status = product.status;
    form.track_stock = product.track_stock ? '1' : '0';
    form.allow_decimal = product.allow_decimal ? '1' : '0';
    form.tax_type = product.tax_type;
    form.tax_rate = product.tax_rate ? String(product.tax_rate) : '0.00';
    form.image = null;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingProduct.value = null;
    form.reset();
};

const submit = () => {
    const url = editingProduct.value ? `/products/${editingProduct.value.id}` : '/products';

    // Normalise fields
    const transformData = (data: any) => {
        const normalised = { ...data };
        normalised.category_id = normalised.category_id === '' ? null : Number(normalised.category_id);
        normalised.brand_id = normalised.brand_id === '' ? null : Number(normalised.brand_id);
        normalised.unit_id = Number(normalised.unit_id);
        normalised.cost_price = Number(normalised.cost_price);
        normalised.selling_price = Number(normalised.selling_price);
        normalised.stock_alert_threshold = Number(normalised.stock_alert_threshold);
        normalised.current_stock = Number(normalised.current_stock);
        normalised.track_stock = normalised.track_stock === '1' ? 1 : 0;
        normalised.allow_decimal = normalised.allow_decimal === '1' ? 1 : 0;
        normalised.tax_type = String(normalised.tax_type);
        normalised.tax_rate = normalised.tax_type === 'none' ? 0.00 : Number(normalised.tax_rate);

        if (editingProduct.value) {
            normalised._method = 'PUT';
        }
        return normalised;
    };

    form.transform(transformData).post(url, {
        onSuccess: () => closeDrawer(),
    });
};

const deleteProduct = (product: Product) => {
    if (confirm(`Are you sure you want to delete product "${product.name}"?`)) {
        router.delete(`/products/${product.id}`, {
            preserveScroll: true,
        });
    }
};

const formatPrice = (value: string) => {
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatStock = (value: string, shortName: string) => {
    const formattedVal = Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    return `${formattedVal} ${shortName}`;
};

// --- BULK OPERATIONS & SOFT RESTORES ---
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    if (props.products.data.length === 0) return false;
    return props.products.data.every(p => selectedIds.value.includes(p.id));
});

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) {
        selectedIds.value = props.products.data.map(p => p.id);
    } else {
        selectedIds.value = [];
    }
};

const toggleSelect = (id: number, event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }
    } else {
        selectedIds.value = selectedIds.value.filter(item => item !== id);
    }
};

const restoreProduct = (product: Product) => {
    if (confirm(`Are you sure you want to restore product "${product.name}"?`)) {
        router.post(`/products/${product.id}/restore`, {}, {
            preserveScroll: true,
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to delete ${selectedIds.value.length} selected products?`)) {
        router.post('/products/bulk-delete', { ids: selectedIds.value }, {
            onSuccess: () => {
                selectedIds.value = [];
            },
            preserveScroll: true,
        });
    }
};

const bulkRestore = () => {
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected products?`)) {
        router.post('/products/bulk-restore', { ids: selectedIds.value }, {
            onSuccess: () => {
                selectedIds.value = [];
            },
            preserveScroll: true,
        });
    }
};

const exportCsv = () => {
    window.location.href = `/products/export?status=${status.value}&search=${search.value}`;
};
</script>

<template>
    <AppLayout>
        <Head title="Products Inventory Directory" />

        <PageHeader title="Products" :breadcrumbs="[{ name: 'Products' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search sku, barcode, name..." class="mr-2" />
                <AppButton variant="secondary" @click="exportCsv" class="mr-2">
                    Export CSV
                </AppButton>
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add Product
                </AppButton>
            </template>
        </PageHeader>

        <!-- Status Filter Tabs & Bulk Actions Toolbar -->
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-150 dark:border-gray-800 shadow-sm">
            <div class="flex items-center space-x-2">
                <button
                    @click="setStatus('active')"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-150"
                    :class="[
                        status === 'active'
                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50'
                    ]"
                >
                    All Directories
                </button>
                <button
                    @click="setStatus('trash')"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-150"
                    :class="[
                        status === 'trash'
                            ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50'
                    ]"
                >
                    <span>Trash</span>
                </button>
            </div>

            <!-- Bulk actions toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-3 bg-indigo-50/50 dark:bg-indigo-950/20 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-medium text-indigo-700 dark:text-indigo-400">
                    {{ selectedIds.length }} Selected
                </span>
                <AppButton v-if="status === 'active'" size="sm" variant="danger" @click="bulkDelete">
                    Bulk Delete
                </AppButton>
                <AppButton v-if="status === 'trash'" size="sm" variant="primary" @click="bulkRestore">
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="products.data.length === 0" class="p-6">
                <EmptyState
                    :title="status === 'trash' ? 'Trash is empty' : 'No products recorded'"
                    :description="status === 'trash' ? 'Soft-deleted products will appear here where they can be restored.' : 'Populate your store shelves by introducing products, configuring cost sheets, and defining stock alert triggers.'"
                >
                    <template #actions v-if="status !== 'trash'">
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Add New Product
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Product Detail', 'SKU / Barcode', 'Category & Brand', 'Cost / Retail', 'Stock / Alert', 'Status', 'Actions']">
                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors" :class="{ 'bg-indigo-50/10 dark:bg-indigo-950/5': selectedIds.includes(product.id) }">
                        <td class="w-10 px-6 py-4 whitespace-nowrap">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(product.id)"
                                @change="toggleSelect(product.id, $event)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded flex items-center justify-center overflow-hidden">
                                    <img v-if="product.image" :src="`/storage/${product.image}`" class="h-full w-full object-cover" />
                                    <span v-else class="text-xs font-bold text-gray-400 uppercase">{{ product.name.substring(0, 2) }}</span>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ product.name }}</p>
                                    <p class="text-xs text-gray-400">{{ product.unit.name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            <p class="font-mono text-gray-800 dark:text-gray-200">{{ product.sku }}</p>
                            <p class="text-xs text-gray-400">{{ product.barcode || 'No Barcode' }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <p class="text-gray-800 dark:text-gray-200">{{ product.category ? product.category.name : 'Uncategorized' }}</p>
                            <p class="text-xs text-gray-400">{{ product.brand ? product.brand.name : 'No Brand' }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <p class="text-gray-500 text-xs">Cost: <span class="font-mono font-medium">${{ formatPrice(product.cost_price) }}</span></p>
                            <p class="text-indigo-600 dark:text-indigo-400 font-bold">Sell: <span class="font-mono">${{ formatPrice(product.selling_price) }}</span></p>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <p
                                class="font-bold font-mono"
                                :class="[
                                    Number(product.current_stock) <= Number(product.stock_alert_threshold)
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-gray-900 dark:text-gray-100'
                                ]"
                            >
                                {{ formatStock(product.current_stock, product.unit.short_name) }}
                            </p>
                            <p class="text-xs text-gray-400">Alert at: {{ formatStock(product.stock_alert_threshold, product.unit.short_name) }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                                :class="[
                                    product.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : product.status === 'out_of_stock'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ product.status.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <template v-if="status === 'trash'">
                                <button
                                    @click="restoreProduct(product)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Restore
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    @click="openEditDrawer(product)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteProduct(product)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Delete
                                </button>
                            </template>
                        </td>
                    </tr>
                </AppTable>
                <AppPagination :links="products.links" />
            </div>
        </AppCard>

        <!-- Product Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingProduct ? 'Edit Product Details' : 'Create Product'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <AppInput label="Product Name" v-model="form.name" :error="form.errors.name" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput label="SKU / Stock Keeping Unit" v-model="form.sku" :error="form.errors.sku" required />
                    <AppInput label="Barcode Number (EAN/UPC)" v-model="form.barcode" :error="form.errors.barcode" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <AppSelect
                            label="Category"
                            v-model="form.category_id"
                            :options="[
                                { value: '', label: 'Uncategorized' },
                                ...categories.map(c => ({ value: c.id, label: c.name }))
                            ]"
                            :error="form.errors.category_id"
                        />
                    </div>
                    <div>
                        <AppSelect
                            label="Brand"
                            v-model="form.brand_id"
                            :options="[
                                { value: '', label: 'No Brand' },
                                ...brands.map(b => ({ value: b.id, label: b.name }))
                            ]"
                            :error="form.errors.brand_id"
                        />
                    </div>
                    <div>
                        <AppSelect
                            label="Unit"
                            v-model="form.unit_id"
                            :options="units.map(u => ({ value: u.id, label: `${u.name} (${u.short_name})` }))"
                            :error="form.errors.unit_id"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput label="Cost Price ($)" type="number" step="0.01" v-model="form.cost_price" :error="form.errors.cost_price" required />
                    <AppInput label="Selling Price ($)" type="number" step="0.01" v-model="form.selling_price" :error="form.errors.selling_price" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput label="Initial Inventory Count" type="number" step="0.001" v-model="form.current_stock" :error="form.errors.current_stock" />
                    <AppInput label="Stock Alert Threshold" type="number" step="0.001" v-model="form.stock_alert_threshold" :error="form.errors.stock_alert_threshold" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <AppSelect
                            label="Track Inventory Stock"
                            v-model="form.track_stock"
                            :options="[
                                { value: '1', label: 'Yes, track stock levels' },
                                { value: '0', label: 'No, do not track' }
                            ]"
                            :error="form.errors.track_stock"
                            required
                        />
                    </div>
                    <div>
                        <AppSelect
                            label="Allow Fractional Stock"
                            v-model="form.allow_decimal"
                            :options="[
                                { value: '0', label: 'No, whole quantities' },
                                { value: '1', label: 'Yes, allow decimal (e.g. 1.5kg)' }
                            ]"
                            :error="form.errors.allow_decimal"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <AppSelect
                            label="Tax Type"
                            v-model="form.tax_type"
                            :options="[
                                { value: 'none', label: 'No Tax' },
                                { value: 'exclusive', label: 'Tax Exclusive' },
                                { value: 'inclusive', label: 'Tax Inclusive' }
                            ]"
                            :error="form.errors.tax_type"
                            required
                        />
                    </div>
                    <div v-if="form.tax_type !== 'none'">
                        <AppInput
                            label="Tax Rate (%)"
                            type="number"
                            step="0.01"
                            v-model="form.tax_rate"
                            :error="form.errors.tax_rate"
                            required
                        />
                    </div>
                </div>

                <div>
                    <AppSelect
                        label="Status"
                        v-model="form.status"
                        :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                            { value: 'out_of_stock', label: 'Out Of Stock' },
                            { value: 'discontinued', label: 'Discontinued' }
                        ]"
                        :error="form.errors.status"
                        required
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product Shelving Photo</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleFile"
                        class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                </div>

                <div>
                    <AppTextarea label="Shelving & Packaging Notes (Optional)" v-model="form.description" :error="form.errors.description" :rows="3" />
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingProduct ? 'Save Changes' : 'Create Product' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
