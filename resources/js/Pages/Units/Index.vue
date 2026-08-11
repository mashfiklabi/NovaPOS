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

interface Unit {
    id: number;
    uuid: string;
    name: string;
    short_name: string;
    allow_decimal: string;
    deleted_at: string | null;
}

const props = defineProps<{
    units: {
        data: Unit[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
        status: string | null;
    };
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'active');

watch(search, (value) => {
    router.get('/units', { search: value, status: status.value }, {
        preserveState: true,
        replace: true,
    });
});

const setStatus = (val: string) => {
    status.value = val;
    selectedIds.value = [];
    router.get('/units', { search: search.value, status: val }, {
        preserveState: true,
        replace: true,
    });
};

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

// --- BULK OPERATIONS & SOFT RESTORES ---
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    if (props.units.data.length === 0) return false;
    return props.units.data.every(u => selectedIds.value.includes(u.id));
});

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) {
        selectedIds.value = props.units.data.map(u => u.id);
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

const restoreUnit = (unit: Unit) => {
    if (confirm(`Are you sure you want to restore unit "${unit.name}"?`)) {
        router.post(`/units/${unit.id}/restore`, {}, {
            preserveScroll: true,
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to delete ${selectedIds.value.length} selected units?`)) {
        router.post('/units/bulk-delete', { ids: selectedIds.value }, {
            onSuccess: () => {
                selectedIds.value = [];
            },
            preserveScroll: true,
        });
    }
};

const bulkRestore = () => {
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected units?`)) {
        router.post('/units/bulk-restore', { ids: selectedIds.value }, {
            onSuccess: () => {
                selectedIds.value = [];
            },
            preserveScroll: true,
        });
    }
};

const exportCsv = () => {
    window.location.href = `/units/export?status=${status.value}&search=${search.value}`;
};
</script>

<template>
    <AppLayout>
        <Head title="Measurement Units Management" />

        <PageHeader title="Units" :breadcrumbs="[{ name: 'Units' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search units..." class="mr-2" />
                <AppButton variant="secondary" @click="exportCsv" class="mr-2">
                    Export CSV
                </AppButton>
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add Unit
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
            <div v-if="units.data.length === 0" class="p-6">
                <EmptyState
                    :title="status === 'trash' ? 'Trash is empty' : 'No measurement units recorded'"
                    :description="status === 'trash' ? 'Soft-deleted units will appear here where they can be restored.' : 'Define product packaging metrics such as pieces (pcs), kilograms (kg), or liters (L).'"
                >
                    <template #actions v-if="status !== 'trash'">
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Create First Unit
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Unit Name', 'Short Name', 'Allow Decimals', 'Actions']">
                    <tr v-for="unit in units.data" :key="unit.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors" :class="{ 'bg-indigo-50/10 dark:bg-indigo-950/5': selectedIds.includes(unit.id) }">
                        <td class="w-10 px-6 py-4 whitespace-nowrap">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(unit.id)"
                                @change="toggleSelect(unit.id, $event)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                            />
                        </td>
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
                            <template v-if="status === 'trash'">
                                <button
                                    @click="restoreUnit(unit)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Restore
                                </button>
                            </template>
                            <template v-else>
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
                            </template>
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
