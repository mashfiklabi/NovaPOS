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
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    status: string;
    parent?: {
        id: number;
        name: string;
    } | null;
    deleted_at?: string | null;
}

interface ParentCategoryOption {
    id: number;
    name: string;
}

const props = defineProps<{
    categories: {
        data: Category[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    parentCategories: ParentCategoryOption[];
    filters: {
        search: string | null;
        status?: string;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = ref(props.filters.status || 'active'); // active, trash

// Sync with router
const updateFilters = () => {
    router.get('/categories', {
        search: search.value || undefined,
        status: activeTab.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    updateFilters();
});

const switchTab = (tab: string) => {
    activeTab.value = tab;
    selectedIds.value = [];
    updateFilters();
};

// Check permissions
const page = usePage();
const permissions = computed(() => (page.props.auth as any)?.user?.permissions || []);
const roles = computed(() => (page.props.auth as any)?.user?.roles || []);
const isSuperAdmin = computed(() => roles.value.includes('Super Admin'));

const hasPermission = (permission: string) => {
    if (isSuperAdmin.value) return true;
    return permissions.value.includes(permission);
};

// Multi-select handling
const selectedIds = ref<number[]>([]);
const selectAllRef = ref<HTMLInputElement | null>(null);

const isAllSelected = computed(() => {
    return props.categories.data.length > 0 && selectedIds.value.length === props.categories.data.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < props.categories.data.length;
});

watch([selectedIds, () => props.categories.data], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = isPartiallySelected.value;
    }
}, { deep: true });

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.categories.data.map(c => c.id);
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

// Actions
const isDrawerOpen = ref(false);
const editingCategory = ref<Category | null>(null);

const form = useForm({
    name: '',
    description: '',
    parent_id: '' as string | number,
    status: 'active',
});

const openCreateDrawer = () => {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    form.parent_id = '';
    isDrawerOpen.value = true;
};

const openEditDrawer = (category: Category) => {
    editingCategory.value = category;
    form.clearErrors();
    form.name = category.name;
    form.description = category.description || '';
    form.parent_id = category.parent_id || '';
    form.status = category.status;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingCategory.value = null;
    form.reset();
};

const submit = () => {
    const parentIdValue = form.parent_id === '' ? null : Number(form.parent_id);
    const data = {
        name: form.name,
        description: form.description,
        parent_id: parentIdValue,
        status: form.status,
    };

    if (editingCategory.value) {
        router.put(`/categories/${editingCategory.value.id}`, data, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
                form.setError(errors);
            },
        });
    } else {
        router.post('/categories', data, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
                form.setError(errors);
            },
        });
    }
};

const deleteCategory = (category: Category) => {
    if (confirm(`Are you sure you want to move category "${category.name}" to Trash?`)) {
        router.delete(`/categories/${category.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreCategory = (category: Category) => {
    if (confirm(`Are you sure you want to restore category "${category.name}"?`)) {
        router.post(`/categories/${category.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to move ${selectedIds.value.length} selected categories to Trash?`)) {
        router.post('/categories/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected categories?`)) {
        router.post('/categories/bulk-restore', {
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
    window.location.href = '/categories/export';
};
</script>

<template>
    <AppLayout>
        <Head title="Product Categories Management" />

        <PageHeader title="Categories" :breadcrumbs="[{ name: 'Categories' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search categories..." />

                    <AppButton
                        v-if="hasPermission('categories.export')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('categories.create')"
                        variant="primary"
                        @click="openCreateDrawer"
                    >
                        Add Category
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
                        activeTab === 'active'
                            ? 'text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                    ]"
                >
                    Active Categories
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
            </div>

            <!-- Bulk actions toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="activeTab === 'active' && hasPermission('categories.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Move to Trash
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('categories.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="categories.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted categories' : 'No categories recorded'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Organize your retail inventory by defining product categories.'"
                >
                    <template #actions>
                        <AppButton v-if="activeTab !== 'trash' && hasPermission('categories.create')" variant="primary" @click="openCreateDrawer">
                            Create First Category
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Category Name', 'Parent Category', 'Description', 'Status', 'Actions']">
                    <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(category.id)"
                                @change="toggleSelectOne(category.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ category.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ category.parent ? category.parent.name : 'None (Root)' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                            {{ category.description || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="[
                                    category.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ category.status }}
                            </span>
                        </td>
                        <!-- Actions column -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('categories.restore')"
                                    @click="restoreCategory(category)"
                                    class="text-xs font-semibold text-green-600 hover:text-green-500 dark:text-green-400"
                                >
                                    Restore
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    v-if="hasPermission('categories.update')"
                                    @click="openEditDrawer(category)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="hasPermission('categories.delete')"
                                    @click="deleteCategory(category)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Move to Trash
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
                <AppPagination :links="categories.links" />
            </div>
        </AppCard>

        <!-- Category Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingCategory ? 'Edit Category' : 'Create Category'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <AppInput label="Category Name" v-model="form.name" :error="form.errors.name" required />
                </div>

                <div>
                    <AppSelect
                        label="Parent Category (Optional)"
                        v-model="form.parent_id"
                        :options="[
                            { value: '', label: 'None (Root Category)' },
                            ...parentCategories
                                .filter(p => !editingCategory || p.id !== editingCategory.id)
                                .map(p => ({ value: p.id, label: p.name }))
                        ]"
                        :error="form.errors.parent_id"
                    />
                </div>

                <div>
                    <AppSelect
                        label="Status"
                        v-model="form.status"
                        :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' }
                        ]"
                        :error="form.errors.status"
                        required
                    />
                </div>

                <div>
                    <AppTextarea label="Description (Optional)" v-model="form.description" :error="form.errors.description" :rows="4" />
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingCategory ? 'Save Changes' : 'Create Category' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
