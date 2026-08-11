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

interface Brand {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    logo: string | null;
    status: string;
}

const props = defineProps<{
    brands: {
        data: Brand[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
    };
}>();

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/brands', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

const isDrawerOpen = ref(false);
const editingBrand = ref<Brand | null>(null);

const form = useForm({
    _method: 'POST',
    name: '',
    description: '',
    status: 'active',
    logo: null as File | null,
});

const handleFile = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    if (files && files.length > 0) {
        form.logo = files[0];
    }
};

const openCreateDrawer = () => {
    editingBrand.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.status = 'active';
    isDrawerOpen.value = true;
};

const openEditDrawer = (brand: Brand) => {
    editingBrand.value = brand;
    form.clearErrors();
    form._method = 'POST'; // we override using POST with _method=PUT to support files with PUT
    form.name = brand.name;
    form.description = brand.description || '';
    form.status = brand.status;
    form.logo = null;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingBrand.value = null;
    form.reset();
};

const submit = () => {
    const url = editingBrand.value ? `/brands/${editingBrand.value.id}` : '/brands';

    if (editingBrand.value) {
        // multipart post override for update
        form.transform((data) => ({
            ...data,
            _method: 'PUT'
        })).post(url, {
            onSuccess: () => closeDrawer(),
        });
    } else {
        form.post(url, {
            onSuccess: () => closeDrawer(),
        });
    }
};

const deleteBrand = (brand: Brand) => {
    if (confirm(`Are you sure you want to delete brand "${brand.name}"?`)) {
        router.delete(`/brands/${brand.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Manufacturer Brands Management" />

        <PageHeader title="Brands" :breadcrumbs="[{ name: 'Brands' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search brands..." class="mr-2" />
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add Brand
                </AppButton>
            </template>
        </PageHeader>

        <AppCard no-padding>
            <div v-if="brands.data.length === 0" class="p-6">
                <EmptyState
                    title="No manufacturer brands recorded"
                    description="Group your retail inventory items by manufacturers and product lines."
                >
                    <template #actions>
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Create First Brand
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['Brand Logo', 'Brand Name', 'Description', 'Status', 'Actions']">
                    <tr v-for="brand in brands.data" :key="brand.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="h-10 w-16 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded flex items-center justify-center overflow-hidden">
                                <img v-if="brand.logo" :src="`/storage/${brand.logo}`" class="h-full w-full object-contain" />
                                <span v-else class="text-xs font-bold text-gray-400 uppercase">{{ brand.name.substring(0, 3) }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ brand.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                            {{ brand.description || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="[
                                    brand.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ brand.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <button
                                @click="openEditDrawer(brand)"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteBrand(brand)"
                                class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </AppTable>
                <AppPagination :links="brands.links" />
            </div>
        </AppCard>

        <!-- Brand Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingBrand ? 'Edit Brand' : 'Create Brand'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <AppInput label="Brand Name" v-model="form.name" :error="form.errors.name" required />
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand Logo Image</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleFile"
                        class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <p v-if="form.errors.logo" class="mt-1 text-xs text-red-500">{{ form.errors.logo }}</p>
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
                    {{ editingBrand ? 'Save Changes' : 'Create Brand' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
