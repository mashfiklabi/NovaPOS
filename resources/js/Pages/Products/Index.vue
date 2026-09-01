<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import { formatCurrency } from '@/Composables/useFormatters';
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
    track_stock: boolean;
    allow_decimal: boolean;
    tax_type: string;
    tax_rate: string;
    image: string | null;
    status: string;
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
        status?: string;
    };
}>();

// Check permissions cleanly with PageProps
const page = usePage<PageProps>();
const permissions = computed(() => page.props.auth.permissions || page.props.auth.user?.permissions || []);
const roles = computed(() => page.props.auth.user?.roles || []);
const isSuperAdmin = computed(() => roles.value.includes('Super Admin'));

const hasPermission = (permission: string) => {
    if (isSuperAdmin.value) return true;
    return permissions.value.includes(permission);
};

const search = ref(props.filters.search || '');
const currentStatus = ref(props.filters.status || 'active'); // active, trash

const updateFilters = () => {
    router.get('/products', {
        search: search.value || undefined,
        status: currentStatus.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    updateFilters();
});

const switchTab = (tab: string) => {
    currentStatus.value = tab;
    selectedIds.value = [];
    updateFilters();
};

// Bulk Selection
const selectedIds = ref<number[]>([]);
const selectAllRef = ref<HTMLInputElement | null>(null);

const isAllSelected = computed(() => {
    return props.products.data.length > 0 && selectedIds.value.length === props.products.data.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < props.products.data.length;
});

watch([selectedIds, () => props.products.data], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = isPartiallySelected.value;
    }
}, { deep: true });

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.products.data.map(p => p.id);
    }
};

const toggleSelectProduct = (id: number) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
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
    track_stock: true,
    allow_decimal: false,
    tax_type: 'exclusive',
    tax_rate: '0.00',
    image: null as File | null,
    status: 'active',
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
    form.track_stock = true;
    form.allow_decimal = false;
    form.tax_type = 'exclusive';
    form.tax_rate = '0.00';
    isDrawerOpen.value = true;
};

const openEditDrawer = (product: Product) => {
    editingProduct.value = product;
    form.clearErrors();
    form._method = 'POST';
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
    form.track_stock = Boolean(product.track_stock);
    form.allow_decimal = Boolean(product.allow_decimal);
    form.tax_type = product.tax_type || 'exclusive';
    form.tax_rate = product.tax_rate || '0.00';
    form.status = product.status;
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

    const transformData = (data: any) => {
        const normalised = { ...data };
        normalised.category_id = normalised.category_id === '' ? null : Number(normalised.category_id);
        normalised.brand_id = normalised.brand_id === '' ? null : Number(normalised.brand_id);
        normalised.unit_id = Number(normalised.unit_id);
        normalised.cost_price = Number(normalised.cost_price);
        normalised.selling_price = Number(normalised.selling_price);
        normalised.stock_alert_threshold = Number(normalised.stock_alert_threshold);
        normalised.current_stock = Number(normalised.current_stock);
        normalised.tax_rate = Number(normalised.tax_rate);

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
    if (confirm(`Are you sure you want to move product "${product.name}" to Trash?`)) {
        router.delete(`/products/${product.id}`, { preserveScroll: true });
    }
};

const restoreProduct = (product: Product) => {
    if (confirm(`Are you sure you want to restore product "${product.name}"?`)) {
        router.post(`/products/${product.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => selectedIds.value = [],
        });
    }
};

// Bulk Actions
const bulkDelete = () => {
    if (confirm(`Are you sure you want to move ${selectedIds.value.length} selected products to Trash?`)) {
        router.post('/products/bulk-delete', { ids: selectedIds.value }, {
            preserveScroll: true,
            onSuccess: () => selectedIds.value = [],
        });
    }
};

const bulkRestore = () => {
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected products?`)) {
        router.post('/products/bulk-restore', { ids: selectedIds.value }, {
            preserveScroll: true,
            onSuccess: () => selectedIds.value = [],
        });
    }
};

const formatStock = (value: string, shortName: string) => {
    const formattedVal = Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    return `${formattedVal} ${shortName}`;
};

const exportCSV = () => {
    window.location.href = '/products/export';
};
</script>

<template>
    <AppLayout>
        <Head title="Products Inventory Directory" />

        <PageHeader title="Products" :breadcrumbs="[{ name: 'Products' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search sku, barcode, name..." />

                    <AppButton
                        v-if="hasPermission('products.export')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('products.create')"
                        variant="primary"
                        @click="openCreateDrawer"
                    >
                        Add Product
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
                        currentStatus === 'active'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Active Products
                </button>
                <button
                    @click="switchTab('trash')"
                    class="pb-2 text-sm font-semibold transition-colors relative flex items-center gap-1.5"
                    :class="[
                        currentStatus === 'trash'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Trash / Deleted
                </button>
            </div>

            <!-- Bulk Toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="currentStatus === 'active' && hasPermission('products.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Move to Trash
                </AppButton>
                <AppButton
                    v-if="currentStatus === 'trash' && hasPermission('products.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="products.data.length === 0" class="p-6">
                <EmptyState
                    :title="currentStatus === 'trash' ? 'No deleted products' : 'No products recorded'"
                    :description="currentStatus === 'trash' ? 'Trash is currently empty.' : 'Populate your store shelves by introducing products, configuring cost sheets, and defining stock alert triggers.'"
                >
                    <template #actions>
                        <AppButton v-if="currentStatus !== 'trash' && hasPermission('products.create')" variant="primary" @click="openCreateDrawer">
                            Create First Product
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Product Detail', 'SKU / Barcode', 'Category & Brand', 'Cost / Retail', 'Stock / Alert', 'Status', 'Actions']">
                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(product.id)"
                                @change="toggleSelectProduct(product.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="product.image" :src="`/products/${product.id}/image`" class="h-full w-full object-cover" />
                                    <span v-else class="text-xs font-bold text-gray-400 uppercase">{{ product.name.substring(0, 2) }}</span>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ product.name }}</p>
                                    <p class="text-xs text-gray-400">{{ product.unit ? product.unit.name : 'Units' }}</p>
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
                            <p class="text-gray-500 text-xs">Cost: <span class="font-mono font-medium">{{ formatCurrency(product.cost_price) }}</span></p>
                            <p class="text-indigo-600 dark:text-indigo-400 font-bold">Sell: <span class="font-mono">{{ formatCurrency(product.selling_price) }}</span></p>
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
                                {{ formatStock(product.current_stock, product.unit ? product.unit.short_name : '') }}
                            </p>
                            <p class="text-xs text-gray-400">Alert at: {{ formatStock(product.stock_alert_threshold, product.unit ? product.unit.short_name : '') }}</p>
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
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-2">
                            <template v-if="currentStatus === 'active'">
                                <button
                                    v-if="hasPermission('products.update')"
                                    @click="openEditDrawer(product)"
                                    class="p-1 text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors"
                                    title="Edit Product"
                                >
                                    <Heroicon name="PencilIcon" class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="hasPermission('products.delete')"
                                    @click="deleteProduct(product)"
                                    class="p-1 text-red-600 hover:text-red-500 dark:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors"
                                    title="Move to Trash"
                                >
                                    <Heroicon name="TrashIcon" class="h-4 w-4" />
                                </button>
                            </template>

                            <template v-else>
                                <button
                                    v-if="hasPermission('products.restore')"
                                    @click="restoreProduct(product)"
                                    class="p-1 text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 rounded-md hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
                                    title="Restore Product"
                                >
                                    <Heroicon name="ArrowPathIcon" class="h-4 w-4" />
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
                    <AppInput label="Cost Price" type="number" step="0.01" v-model="form.cost_price" :error="form.errors.cost_price" required />
                    <AppInput label="Selling Price" type="number" step="0.01" v-model="form.selling_price" :error="form.errors.selling_price" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput label="Initial Inventory Count" type="number" step="0.001" v-model="form.current_stock" :error="form.errors.current_stock" />
                    <AppInput label="Stock Alert Threshold" type="number" step="0.001" v-model="form.stock_alert_threshold" :error="form.errors.stock_alert_threshold" required />
                </div>

                <!-- Stock & Tax Configuration Parameters -->
                <div class="grid grid-cols-2 gap-4 border-t border-gray-200 dark:border-gray-800 pt-4">
                    <div class="flex items-center space-x-2 pt-2">
                        <input
                            type="checkbox"
                            id="track_stock"
                            v-model="form.track_stock"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="track_stock" class="text-sm font-medium text-gray-700 dark:text-gray-300">Track Stock Inventory</label>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <input
                            type="checkbox"
                            id="allow_decimal"
                            v-model="form.allow_decimal"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="allow_decimal" class="text-sm font-medium text-gray-700 dark:text-gray-300">Allow Fractional Decimals</label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        label="Tax Treatment"
                        v-model="form.tax_type"
                        :options="[
                            { value: 'exclusive', label: 'Tax Exclusive' },
                            { value: 'inclusive', label: 'Tax Inclusive' }
                        ]"
                        :error="form.errors.tax_type"
                        required
                    />
                    <AppInput label="Tax Rate (%)" type="number" step="0.01" v-model="form.tax_rate" :error="form.errors.tax_rate" />
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
