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

interface Unit {
    id: number;
    uuid: string;
    name: string;
    short_name: string;
    allow_decimal: string;
}

const props = defineProps<{
    units: {
        data: Unit[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
    };
}>();

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/units', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

const isDrawerOpen = ref(false);
const editingUnit = ref<Unit | null>(null);

const form = useForm({
    name: '',
    short_name: '',
    allow_decimal: 'disallowed',
});

const openCreateDrawer = () => {
    editingUnit.value = null;
    form.reset();
    form.clearErrors();
    form.allow_decimal = 'disallowed';
    isDrawerOpen.value = true;
};

const openEditDrawer = (unit: Unit) => {
    editingUnit.value = unit;
    form.clearErrors();
    form.name = unit.name;
    form.short_name = unit.short_name;
    form.allow_decimal = unit.allow_decimal;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingUnit.value = null;
    form.reset();
};

const submit = () => {
    if (editingUnit.value) {
        form.put(`/units/${editingUnit.value.id}`, {
            onSuccess: () => closeDrawer(),
        });
    } else {
        form.post('/units', {
            onSuccess: () => closeDrawer(),
        });
    }
};

const deleteUnit = (unit: Unit) => {
    if (confirm(`Are you sure you want to delete unit "${unit.name}" (${unit.short_name})?`)) {
        router.delete(`/units/${unit.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Measurement Units Management" />

        <PageHeader title="Units" :breadcrumbs="[{ name: 'Units' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search units..." class="mr-2" />
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add Unit
                </AppButton>
            </template>
        </PageHeader>

        <AppCard no-padding>
            <div v-if="units.data.length === 0" class="p-6">
                <EmptyState
                    title="No measurement units recorded"
                    description="Define product packaging metrics such as pieces (pcs), kilograms (kg), or liters (L)."
                >
                    <template #actions>
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Create First Unit
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['Unit Name', 'Short Name', 'Allow Decimals', 'Actions']">
                    <tr v-for="unit in units.data" :key="unit.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ unit.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ unit.short_name }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                                :class="[
                                    unit.allow_decimal === 'allowed'
                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
                                ]"
                            >
                                {{ unit.allow_decimal === 'allowed' ? 'Yes (3 Decimals)' : 'No (Integers)' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <button
                                @click="openEditDrawer(unit)"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteUnit(unit)"
                                class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </AppTable>
                <AppPagination :links="units.links" />
            </div>
        </AppCard>

        <!-- Unit Create / Edit Drawer -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingUnit ? 'Edit Unit' : 'Create Unit'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <AppInput label="Unit Name (e.g. Kilograms)" v-model="form.name" :error="form.errors.name" required />
                </div>

                <div>
                    <AppInput label="Short Name (e.g. kg)" v-model="form.short_name" :error="form.errors.short_name" required />
                </div>

                <div>
                    <AppSelect
                        label="Allow Fractional Decimals"
                        v-model="form.allow_decimal"
                        :options="[
                            { value: 'disallowed', label: 'No (Whole numbers only, e.g. 1, 5, 10)' },
                            { value: 'allowed', label: 'Yes (Supports up to 3 decimals, e.g. 1.250 kg)' }
                        ]"
                        :error="form.errors.allow_decimal"
                        required
                    />
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingUnit ? 'Save Changes' : 'Create Unit' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
