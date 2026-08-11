<script setup lang="ts">
import { ref, watch } from 'vue';
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
    };
}>();

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/categories', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

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
</script>

<template>
    <AppLayout>
        <Head title="Product Categories Management" />

        <PageHeader title="Categories" :breadcrumbs="[{ name: 'Categories' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search categories..." class="mr-2" />
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add Category
                </AppButton>
            </template>
        </PageHeader>

        <AppCard no-padding>
            <div v-if="categories.data.length === 0" class="p-6">
                <EmptyState
                    title="No categories recorded"
                    description="Organize your retail inventory by defining product categories (e.g. Apparel, Electronics)."
                >
                    <template #actions>
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Create First Category
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['Category Name', 'Parent Category', 'Description', 'Status', 'Actions']">
                    <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
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
