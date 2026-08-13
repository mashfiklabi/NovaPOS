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

interface Unit {
    id: number;
    uuid: string;
    name: string;
    short_name: string;
    allow_decimal: string;
    deleted_at?: string | null;
}

const props = defineProps<{
    units: {
        data: Unit[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
        status?: string;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = ref(props.filters.status || 'active'); // active, trash

// Sync filters with router
const updateFilters = () => {
    router.get('/units', {
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
    return props.units.data.length > 0 && selectedIds.value.length === props.units.data.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.units.data.map(u => u.id);
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
            onError: (errors) => {
                if (errors.error) alert(errors.error);
                form.setError(errors);
            }
        });
    } else {
        form.post('/units', {
            onSuccess: () => closeDrawer(),
            onError: (errors) => {
                if (errors.error) alert(errors.error);
                form.setError(errors);
            }
        });
    }
};

const deleteUnit = (unit: Unit) => {
    if (confirm(`Are you sure you want to delete unit "${unit.name}" (${unit.short_name})?`)) {
        router.delete(`/units/${unit.id}`, {
            preserveScroll: true,
            onError: (err) => {
                if (err.error) alert(err.error);
            }
        });
    }
};

const restoreUnit = (unit: Unit) => {
    if (confirm(`Are you sure you want to restore unit "${unit.name}"?`)) {
        router.post(`/units/${unit.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const bulkDelete = () => {
    if (confirm(`Are you sure you want to delete ${selectedIds.value.length} selected units?`)) {
        router.post('/units/bulk-delete', {
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
    if (confirm(`Are you sure you want to restore ${selectedIds.value.length} selected units?`)) {
        router.post('/units/bulk-restore', {
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
    window.location.href = '/units/export';
};
</script>

<template>
    <AppLayout>
        <Head title="Measurement Units Management" />

        <PageHeader title="Units" :breadcrumbs="[{ name: 'Units' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <SearchInput v-model="search" placeholder="Search units..." />

                    <AppButton
                        v-if="hasPermission('units.export')"
                        variant="secondary"
                        @click="exportCSV"
                        title="Export CSV"
                    >
                        Export CSV
                    </AppButton>

                    <AppButton
                        v-if="hasPermission('units.create')"
                        variant="primary"
                        @click="openCreateDrawer"
                    >
                        Add Unit
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
                    Active Units
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

            <!-- Bulk Toolbar -->
            <div v-if="selectedIds.length > 0" class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    {{ selectedIds.length }} selected
                </span>
                <AppButton
                    v-if="activeTab === 'active' && hasPermission('units.bulk_delete')"
                    size="sm"
                    variant="danger"
                    @click="bulkDelete"
                >
                    Bulk Delete
                </AppButton>
                <AppButton
                    v-if="activeTab === 'trash' && hasPermission('units.bulk_restore')"
                    size="sm"
                    variant="primary"
                    @click="bulkRestore"
                >
                    Bulk Restore
                </AppButton>
            </div>
        </div>

        <AppCard no-padding>
            <div v-if="units.data.length === 0" class="p-6">
                <EmptyState
                    :title="activeTab === 'trash' ? 'No deleted units' : 'No measurement units recorded'"
                    :description="activeTab === 'trash' ? 'Trash is currently empty.' : 'Define product packaging metrics such as pieces (pcs), kilograms (kg), or liters (L).'"
                >
                    <template #actions>
                        <AppButton v-if="activeTab !== 'trash' && hasPermission('units.create')" variant="primary" @click="openCreateDrawer">
                            Create First Unit
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['', 'Unit Name', 'Short Name', 'Allow Decimals', 'Actions']">
                    <tr v-for="unit in units.data" :key="unit.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <!-- Checkbox column -->
                        <td class="w-10 pl-6 py-4">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(unit.id)"
                                @change="toggleSelectOne(unit.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
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
                        <!-- Actions column -->
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <template v-if="activeTab === 'trash'">
                                <button
                                    v-if="hasPermission('units.restore')"
                                    @click="restoreUnit(unit)"
                                    class="text-xs font-semibold text-green-600 hover:text-green-500 dark:text-green-400"
                                >
                                    Restore
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    v-if="hasPermission('units.update')"
                                    @click="openEditDrawer(unit)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="hasPermission('units.delete')"
                                    @click="deleteUnit(unit)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                                >
                                    Delete
                                </button>
                            </template>
                        </td>
                    </tr>

                    <!-- Table header extension to include "select all" triggers -->
                    <template #header-prepend>
                        <th class="w-10 pl-6 py-3 text-left">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                            />
                        </th>
                    </template>
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
