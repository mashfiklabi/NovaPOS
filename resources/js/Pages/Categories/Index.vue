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
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    status: string;
    deleted_at: string | null;
    parent?: {
        id: number;
        name: string;
    } | null;
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
        status: string | null;
    };
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'active');

watch(search, (value) => {
    router.get('/categories', { search: value, status: status.value }, {
        preserveState: true,
        replace: true,
    });
});

const setStatus = (val: string) => {
    status.value = val;
    selectedIds.value = [];
    router.get('/categories', { search: search.value, status: val }, {
        preserveState: true,
        replace: true,
    });
};

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
            onError: (errors) => form.setError(errors),
        });
    } else {
        router.post('/categories', data, {
            onSuccess: () => closeDrawer(),
            onError: (errors) => form.setError(errors),
        });
    }
};

const deleteCategory = (category: Category) => {
    if (confirm(`Are you sure you want to delete category "${category.name}"?`)) {
        router.delete(`/categories/${category.id}`, {
            preserveScroll: true,
        });
    }
};

// --- BULK OPERATIONS & SOFT RESTORES ---
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    if (props.categories.data.length === 0) return false;
    return props.categories.data.every(c => selectedIds.value.includes(c.id));
});

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) {
        selectedIds.value = props.categories.data.map(c => c.id);
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

const restoreCategory = (category: Category) => {
    if (confirm(`Are you sure you want to restore category "${category.name}"?`)) {
        router.post(`/categories/${category.id}/restore`, {}, {
            preserveScroll: true,
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to delete ${selectedIds.value.length} selected categories?`)) {
        router.post('/categories/bulk-delete', { ids: selectedIds.value }, {
            onSuccess: () => {
                selectedIds.value = [];
            },
            preserveScroll: true,
        });
    }
};

const bulkRestore = () => {
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected categories?`)) {
        router.post('/categories/bulk-restore', { ids: selectedIds.value }, {
            onSuccess: () => {
                selectedIds.value = [];
            },
            preserveScroll: true,
        });
    }
};

const exportCsv = () => {
    window.location.href = `/categories/export?status=${status.value}&search=${search.value}`;
};
</script>

<template>
    <AppLayout>
        <Head title="Product Categories Management" />

        <PageHeader title="Categories" :breadcrumbs="[{ name: 'Categories' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search categories..." class="mr-2" />
                <AppButton variant="secondary" @click="exportCsv" class="mr-2">
                    Export CSV
                </AppButton>
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add Category
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
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-150 flex items-center space-x-1"
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
            <div v-if="categories.data.length === 0" class="p-6">
                <EmptyState
                    :title="status === 'trash' ? 'Trash is empty' : 'No categories recorded'"
                    :description="status === 'trash' ? 'Soft-deleted categories will appear here where they can be restored.' : 'Organize your retail inventory by defining product categories (e.g. Apparel, Electronics).'"
                >
                    <template #actions v-if="status !== 'trash'">
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Create First Category
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Category Name', 'Parent Category', 'Description', 'Status', 'Actions']">
                    <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors" :class="{ 'bg-indigo-50/10 dark:bg-indigo-950/5': selectedIds.includes(category.id) }">
                        <td class="w-10 px-6 py-4 whitespace-nowrap">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(category.id)"
                                @change="toggleSelect(category.id, $event)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
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
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <template v-if="status === 'trash'">
                                <button
                                    @click="restoreCategory(category)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Restore
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    @click="openEditDrawer(category)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteCategory(category)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Delete
                                </button>
                            </template>
                        </td>
                    </tr>
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
