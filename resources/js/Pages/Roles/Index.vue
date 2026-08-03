<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppTable from '@/Components/AppTable.vue';
import AppDrawer from '@/Components/AppDrawer.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';

interface Permission {
    id: number;
    name: string;
    description: string | null;
}

interface Role {
    id: number;
    name: string;
    permissions: Permission[];
}

const props = defineProps<{
    roles: Role[];
    permissions: Permission[];
}>();

// Form and Drawer states
const isDrawerOpen = ref(false);
const editingRole = ref<Role | null>(null);

const form = useForm({
    name: '',
    permission_names: [] as string[],
});

const openCreateDrawer = () => {
    editingRole.value = null;
    form.reset();
    form.clearErrors();
    isDrawerOpen.value = true;
};

const openEditDrawer = (role: Role) => {
    editingRole.value = role;
    form.clearErrors();
    form.name = role.name;
    form.permission_names = role.permissions.map(p => p.name);
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingRole.value = null;
    form.reset();
};

const submit = () => {
    if (editingRole.value) {
        form.put(`/roles/${editingRole.value.id}`, {
            onSuccess: () => closeDrawer(),
        });
    } else {
        form.post('/roles', {
            onSuccess: () => closeDrawer(),
        });
    }
};

const deleteRole = (role: Role) => {
    if (role.name === 'Super Admin') {
        alert('The Super Admin role is protected and cannot be deleted.');
        return;
    }

    if (confirm(`Are you sure you want to permanently delete the "${role.name}" role?`)) {
        router.delete(`/roles/${role.id}`);
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Spatie RBAC Setup" />

        <PageHeader title="Roles & Permissions" :breadcrumbs="[{ name: 'Roles' }]">
            <template #actions>
                <AppButton variant="primary" @click="openCreateDrawer">
                    Create Role
                </AppButton>
            </template>
        </PageHeader>

        <!-- Roles List -->
        <AppCard no-padding>
            <div v-if="roles.length === 0" class="p-6">
                <EmptyState
                    title="No security roles created"
                    description="RBAC rules assign groups of granular permissions to store cashiers, clerks, or managers."
                >
                    <template #actions>
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Configure System Role
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['Role Name', 'Active Permissions', 'Actions']">
                    <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ role.name }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex flex-wrap gap-1 max-w-xl">
                                <span
                                    v-for="perm in role.permissions"
                                    :key="perm.id"
                                    class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ perm.name }}
                                </span>
                                <span v-if="role.permissions.length === 0" class="text-xs text-gray-400 italic">No permissions assigned</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <button
                                @click="openEditDrawer(role)"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                Edit Role
                            </button>
                            <button
                                v-if="role.name !== 'Super Admin'"
                                @click="deleteRole(role)"
                                class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </AppTable>
            </div>
        </AppCard>

        <!-- Create or Edit Role Drawer Overlay -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingRole ? 'Edit Security Role' : 'Create Custom Role'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <AppInput label="Role Name" v-model="form.name" :error="form.errors.name" required :disabled="editingRole?.name === 'Super Admin'" />
                </div>

                <!-- Granular Permissions assignments -->
                <div>
                    <span class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Granular Permissions Checkboxes</span>

                    <div v-if="editingRole?.name === 'Super Admin'" class="p-3 bg-yellow-50 text-yellow-800 text-xs rounded-lg dark:bg-yellow-950/20 dark:text-yellow-400 border border-yellow-100 dark:border-yellow-900/30">
                        The 'Super Admin' role is granted wild-card permission privileges throughout the entire POS ecosystem and cannot have permissions removed.
                    </div>

                    <div v-else class="space-y-3 mt-3">
                        <div v-for="perm in permissions" :key="perm.id" class="flex items-start">
                            <div class="flex h-5 items-center">
                                <input
                                    :id="`perm-chk-${perm.id}`"
                                    type="checkbox"
                                    :value="perm.name"
                                    v-model="form.permission_names"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                                />
                            </div>
                            <div class="ml-3 text-sm">
                                <label :for="`perm-chk-${perm.id}`" class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ perm.name }}
                                </label>
                                <p class="text-xs text-gray-500">{{ perm.description || '' }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.permission_names" class="mt-1 text-xs text-red-500">{{ form.errors.permission_names }}</p>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingRole ? 'Update Role' : 'Create Role' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
