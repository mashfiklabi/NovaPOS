<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
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
    tax_rate: string;
    category: Category | null;
    brand: Brand | null;
    unit: Unit;
    deleted_at?: string | null;
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
        sort_by?: string;
        sort_dir?: string;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = ref(props.filters.status === 'trash' ? 'trash' : 'active'); // tab: active or trash
const statusFilter = ref(props.filters.status && props.filters.status !== 'trash' ? props.filters.status : 'all'); // active, inactive, out_of_stock, discontinued, all
const sortBy = ref(props.filters.sort_by || 'id');
const sortDir = ref(props.filters.sort_dir || 'desc');

// Sync filters with router
const updateFilters = () => {
    router.get('/products', {
        search: search.value || undefined,
        status: activeTab.value === 'trash' ? 'trash' : statusFilter.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    updateFilters();
});

const switchTab = (tab: 'active' | 'trash') => {
    activeTab.value = tab;
    selectedIds.value = [];
    statusFilter.value = 'all';
    updateFilters();
};

const handleStatusFilterChange = (val: string) => {
    statusFilter.value = val;
    activeTab.value = 'active';
    updateFilters();
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    updateFilters();
};

// Check permissions
const hasPermission = (permission: string) => {
    const auth = (usePage().props.auth as any) || {};
    const user = auth.user || {};
    const perms = user.permissions || [];
    const roles = user.roles || [];
    if (roles.includes('Super Admin')) {
        return true;
    }
    return perms.includes(permission);
};

// Checklist select handling
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    return props.products.data.length > 0 && selectedIds.value.length === props.products.data.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.products.data.map(p => p.id);
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

// Drawer state & CRUD
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
    cost_price: '', // Purchase Price
    selling_price: '',
    stock_alert_threshold: '0.000', // Minimum Stock
    current_stock: '0.000',
    image: null as File | null,
    status: 'active',
    track_stock: true,
    allow_decimal: false,
    tax_type: 'none' as 'exclusive' | 'inclusive' | 'none',
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
    form.track_stock = true;
    form.allow_decimal = false;
    form.tax_type = 'none';
    form.tax_rate = '0.00';
    isDrawerOpen.value = true;
};

const openEditDrawer = (product: Product) => {
    editingProduct.value = product;
    form.clearErrors();
    form._method = 'POST'; // support file updates with POST method spoofing
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
    form.track_stock = Boolean(product.track_stock);
    form.allow_decimal = Boolean(product.allow_decimal);
    form.tax_type = product.tax_type || 'none';
    form.tax_rate = product.tax_rate || '0.00';
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
        normalised.track_stock = normalised.track_stock ? 1 : 0;
        normalised.allow_decimal = normalised.allow_decimal ? 1 : 0;
        normalised.tax_rate = Number(normalised.tax_rate);

        if (editingProduct.value) {
            normalised._method = 'PUT';
        }
        return normalised;
    };

    form.transform(transformData).post(url, {
        onSuccess: () => closeDrawer(),
        onError: (errors) => {
            if (errors.error) alert(errors.error);
            form.setError(errors);
        }
    });
};

const deleteProduct = (product: Product) => {
    if (confirm(`Are you sure you want to soft delete product "${product.name}"?`)) {
        router.delete(`/products/${product.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreProduct = (product: Product) => {
    if (confirm(`Are you sure you want to restore product "${product.name}"?`)) {
        router.post(`/products/${product.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const permanentlyDeleteProduct = (product: Product) => {
    if (confirm(`WARNING: You are about to PERMANENTLY delete product "${product.name}". This will clean up file assets and cannot be undone. Proceed?`)) {
        router.delete(`/products/${product.id}/force-delete`, {
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

const bulkDelete = () => {
    if (confirm(`Are you sure you want to soft delete ${selectedIds.value.length} selected products?`)) {
        router.post('/products/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected products?`)) {
        router.post('/products/bulk-restore', {
            ids: selectedIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkForceDelete = () => {
    if (confirm(`WARNING: You are about to PERMANENTLY delete ${selectedIds.value.length} selected products. This cannot be undone. Proceed?`)) {
        router.post('/products/bulk-force-delete', {
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

const exportCSV = () => {
    window.location.href = '/products/export';
};

const formatPrice = (value: string) => {
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatStock = (value: string, shortName: string) => {
    const formattedVal = Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    return `${formattedVal} ${shortName}`;
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

        <!-- Status Tabs & Bulk Bar -->
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
                    Active Products
                </button>
                <button
                    @click="switchTab('trash')"
                    class="pb-2 text-sm font-semibold transition-colors relative"
                    :class="[
                        activeTab === 'trash'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Trash / Deleted
                </button>
            </div>

            <!-- Proper Status Filter Dropdown -->
            <div v-if="activeTab === 'active'" class="flex items-center space-x-2">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Status:</span>
                <select
                    :value="statusFilter"
                    @change="handleStatusFilterChange(($event.target as HTMLSelectElement).value)"
                    class="rounded-md border-gray-300 text-xs py-1 pl-2 pr-8 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100"
                >
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="out_of_stock">Out Of Stock</option>
                    <option value="discontinued">Discontinued</option>
                </select>
            </div>

            <!-- Bulk Toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="activeTab === 'active' && hasPermission('products.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Bulk Soft Delete
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('products.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('products.delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkForceDelete"
                >
                    Bulk Delete Permanently
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="products.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted products' : 'No products recorded'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Populate your store shelves by introducing products.'"
                >
                    <template #actions>
                        <AppButton v-if="activeTab !== 'trash' && hasPermission('products.create')" variant="primary" @click="openCreateDrawer">
                            Add New Product
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Product Detail', 'SKU / Barcode', 'Category & Brand', 'Cost / Retail', 'Stock / Alert', 'Status', 'Actions']">
                    <!-- Column sorting headers -->
                    <template #header-tr-content>
                        <th class="w-10 pl-6 py-3 text-left">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </th>
                        <th @click="toggleSort('name')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Product Detail
                            <span v-if="sortBy === 'name'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th @click="toggleSort('sku')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            SKU / Barcode
                            <span v-if="sortBy === 'sku'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Category & Brand
                        </th>
                        <th @click="toggleSort('cost_price')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Purchase / Sell
                            <span v-if="sortBy === 'cost_price'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th @click="toggleSort('current_stock')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Stock / Minimum
                            <span v-if="sortBy === 'current_stock'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th @click="toggleSort('status')" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Status
                            <span v-if="sortBy === 'status'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </template>

                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(product.id)"
                                @change="toggleSelectOne(product.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded flex items-center justify-center overflow-hidden">
                                    <!-- Secure streaming link -->
                                    <img v-if="product.image" :src="route('products.image', product.id)" class="h-full w-full object-cover" />
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
                            <p class="text-gray-500 text-xs">Purchase: <span class="font-mono font-medium">${{ formatPrice(product.cost_price) }}</span></p>
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
                            <p class="text-xs text-gray-400">Min: {{ formatStock(product.stock_alert_threshold, product.unit.short_name) }}</p>
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
                        <!-- Actions column -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('products.restore')"
                                    @click="restoreProduct(product)"
                                    class="text-xs font-semibold text-green-600 hover:text-green-500 dark:text-green-400"
                                >
                                    Restore
                                </button>
                                <button
                                    v-if="hasPermission('products.delete')"
                                    @click="permanentlyDeleteProduct(product)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Delete Permanently
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    v-if="hasPermission('products.update')"
                                    @click="openEditDrawer(product)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="hasPermission('products.delete')"
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
                            label="Category (Required)"
                            v-model="form.category_id"
                            :options="[
                                { value: '', label: 'Select Category' },
                                ...categories.map(c => ({ value: c.id, label: c.name }))
                            ]"
                            :error="form.errors.category_id"
                            required
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
                    <AppInput label="Purchase Price ($)" type="number" step="0.01" v-model="form.cost_price" :error="form.errors.cost_price" required />
                    <AppInput label="Selling Price ($)" type="number" step="0.01" v-model="form.selling_price" :error="form.errors.selling_price" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput label="Initial Inventory Count" type="number" step="0.001" v-model="form.current_stock" :error="form.errors.current_stock" />
                    <AppInput label="Minimum Stock Alert" type="number" step="0.001" v-model="form.stock_alert_threshold" :error="form.errors.stock_alert_threshold" required />
                </div>

                <!-- NEW SPRINT 3 Product properties -->
                <div class="grid grid-cols-2 gap-4 border-t border-gray-100 border-gray-200 dark:border-gray-800 pt-4">
                    <div class="flex items-center h-full pt-6">
                        <label class="inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.track_stock"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                            <span class="ml-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Track Stock Levels</span>
                        </label>
                    </div>

                    <div class="flex items-center h-full pt-6">
                        <label class="inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.allow_decimal"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                            <span class="ml-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Allow Fractional Quantities</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 border-b border-gray-100 border-gray-200 dark:border-gray-800 pb-4">
                    <div>
                        <AppSelect
                            label="Tax Type"
                            v-model="form.tax_type"
                            :options="[
                                { value: 'none', label: 'No Tax (None)' },
                                { value: 'exclusive', label: 'Exclusive Tax' },
                                { value: 'inclusive', label: 'Inclusive Tax' }
                            ]"
                            :error="form.errors.tax_type"
                            required
                        />
                    </div>
                    <div>
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
