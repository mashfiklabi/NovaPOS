<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Table from '@/Components/Table.vue';
import Drawer from '@/Components/Drawer.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import EmptyState from '@/Components/EmptyState.vue';

interface Permission {
    id: number;
    name: string;
    description: string | null;
}

interface Role {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    permissions: Permission[];
}

const props = defineProps<{
    roles: Role[];
    permissions: Permission[];
}>();

// Form & Drawer states
const isDrawerOpen = ref(false);
const editingRole = ref<Role | null>(null);

const form = useForm({
    name: '',
    description: '',
    permission_ids: [] as number[],
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
    form.description = role.description || '';
    form.permission_ids = role.permissions.map(p => p.id);
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
    if (role.name === 'Admin') {
        alert('The Admin role is protected and cannot be deleted.');
        return;
    }

    if (confirm(`Are you sure you want to permanently delete the "${role.name}" role?`)) {
        router.delete(`/roles/${role.id}`);
    }
};
</script>

<template>
    <AppLayout>
        <Head title="RBAC Roles & Permissions" />

        <PageHeader title="Roles & Permissions" :breadcrumbs="[{ name: 'Roles' }]">
            <template #actions>
                <button
                    @click="openCreateDrawer"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 transition-all duration-150"
                >
                    Create Role
                </button>
            </template>
        </PageHeader>

        <!-- Roles list card -->
        <Card no-padding>
            <div v-if="roles.length === 0" class="p-6">
                <EmptyState
                    title="No roles created"
                    description="Roles assign groups of granular system permissions to your cashiers and store managers."
                >
                    <template #actions>
                        <button
                            @click="openCreateDrawer"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500"
                        >
                            Create First Role
                        </button>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <Table :headers="['Role Name', 'Description', 'Active Permissions', 'Actions']">
                    <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ role.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ role.description || 'No description provided' }}
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
                                v-if="role.name !== 'Admin'"
                                @click="deleteRole(role)"
                                class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </Table>
            </div>
        </Card>

        <!-- Create or Edit Role Drawer -->
        <Drawer
            :show="isDrawerOpen"
            :title="editingRole ? 'Update Role Settings' : 'Configure New System Role'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <InputLabel for="role_name" value="Role Name" />
                    <TextInput id="role_name" type="text" class="mt-1 block w-full" v-model="form.name" required :disabled="editingRole?.name === 'Admin'" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="role_description" value="Role Description" />
                    <textarea
                        id="role_description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100"
                        v-model="form.description"
                    />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>

                <!-- Granular Permissions assignments -->
                <div>
                    <span class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Granular Permissions Checkboxes</span>

                    <div v-if="editingRole?.name === 'Admin'" class="p-3 bg-yellow-50 text-yellow-800 text-xs rounded-lg dark:bg-yellow-950/20 dark:text-yellow-400 border border-yellow-100 dark:border-yellow-900/30">
                        The 'Admin' role is granted wild-card permission privileges throughout the entire POS ecosystem and cannot have permissions removed.
                    </div>

                    <div v-else class="space-y-3 mt-3">
                        <div v-for="perm in permissions" :key="perm.id" class="flex items-start">
                            <div class="flex h-5 items-center">
                                <input
                                    :id="`perm-${perm.id}`"
                                    type="checkbox"
                                    :value="perm.id"
                                    v-model="form.permission_ids"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                                />
                            </div>
                            <div class="ml-3 text-sm">
                                <label :for="`perm-${perm.id}`" class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ perm.name }}
                                </label>
                                <p class="text-xs text-gray-500">{{ perm.description }}</p>
                            </div>
                        </div>
                    </div>
                    <InputError class="mt-1" :message="form.errors.permission_ids" />
                </div>
            </form>

            <template #footer>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="closeDrawer"
                >
                    Cancel
                </button>
                <PrimaryButton :disabled="form.processing" @click="submit" class="ml-3">
                    {{ editingRole ? 'Update Role' : 'Create Role' }}
                </PrimaryButton>
            </template>
        </Drawer>
    </AppLayout>
</template>
