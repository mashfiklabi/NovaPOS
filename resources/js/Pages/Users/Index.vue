<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Table from '@/Components/Table.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Drawer from '@/Components/Drawer.vue';
import EmptyState from '@/Components/EmptyState.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

interface Role {
    id: number;
    name: string;
    description: string | null;
}

interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    status: string;
    roles: Role[];
}

const props = defineProps<{
    users: {
        data: User[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    roles: Role[];
    filters: {
        search: string | null;
    };
}>();

// Search state
const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/users', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

// Drawer & Form State
const isDrawerOpen = ref(false);
const editingUser = ref<User | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    role_ids: [] as number[],
});

const openCreateDrawer = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    isDrawerOpen.value = true;
};

const openEditDrawer = (user: User) => {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.password_confirmation = '';
    form.status = user.status;
    form.role_ids = user.roles.map(r => r.id);
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingUser.value = null;
    form.reset();
};

const submit = () => {
    if (editingUser.value) {
        form.put(`/users/${editingUser.value.id}`, {
            onSuccess: () => closeDrawer(),
        });
    } else {
        form.post('/users', {
            onSuccess: () => closeDrawer(),
        });
    }
};

const deleteUser = (user: User) => {
    if (confirm(`Are you sure you want to delete ${user.name}? This will archive their profile.`)) {
        router.delete(`/users/${user.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Users & Accounts Management" />

        <PageHeader title="Users" :breadcrumbs="[{ name: 'Users' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search users name or email..." class="mr-2" />
                <button
                    @click="openCreateDrawer"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 transition-all duration-150"
                >
                    Add User
                </button>
            </template>
        </PageHeader>

        <!-- Users list card -->
        <Card no-padding>
            <div v-if="users.data.length === 0" class="p-6">
                <EmptyState
                    title="No users found"
                    description="Get started by creating your store managers, cashiers, or administrators here."
                >
                    <template #actions>
                        <button
                            @click="openCreateDrawer"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500"
                        >
                            Create First Account
                        </button>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <Table :headers="['Name', 'Email', 'Role Assignments', 'Account Status', 'Actions']">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">
                            {{ user.name }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ user.email }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                v-for="role in user.roles"
                                :key="role.id"
                                class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-950/30 dark:text-indigo-400 mr-1"
                            >
                                {{ role.name }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="[
                                    user.status === 'active'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ user.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap space-x-3">
                            <button
                                @click="openEditDrawer(user)"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                Edit
                            </button>
                            <button
                                v-if="$page.props.auth.user.id !== user.id"
                                @click="deleteUser(user)"
                                class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </Table>
                <Pagination :links="users.links" />
            </div>
        </Card>

        <!-- Create or Edit User Drawer -->
        <Drawer
            :show="isDrawerOpen"
            :title="editingUser ? 'Modify User Profile' : 'Configure New User Account'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-6">
                <!-- User Basic fields -->
                <div>
                    <InputLabel for="name" value="Full Name" />
                    <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" value="Email Address" />
                    <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>

                <!-- Password (optional on edit) -->
                <div>
                    <InputLabel for="password" :value="editingUser ? 'Update Password (optional)' : 'Password'" />
                    <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" :required="!editingUser" />
                    <InputError class="mt-1" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Confirm Password" />
                    <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" :required="!editingUser" />
                </div>

                <!-- Status -->
                <div>
                    <InputLabel for="status" value="Account Status" />
                    <select
                        id="status"
                        v-model="form.status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.status" />
                </div>

                <!-- Roles checklist -->
                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assign System Roles</span>
                    <div class="mt-2 space-y-2">
                        <div v-for="role in roles" :key="role.id" class="flex items-start">
                            <div class="flex h-5 items-center">
                                <input
                                    :id="`role-${role.id}`"
                                    type="checkbox"
                                    :value="role.id"
                                    v-model="form.role_ids"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                                />
                            </div>
                            <div class="ml-3 text-sm">
                                <label :for="`role-${role.id}`" class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ role.name }}
                                </label>
                                <p class="text-xs text-gray-500">{{ role.description }}</p>
                            </div>
                        </div>
                    </div>
                    <InputError class="mt-1" :message="form.errors.role_ids" />
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
                    {{ editingUser ? 'Update User' : 'Create User' }}
                </PrimaryButton>
            </template>
        </Drawer>
    </AppLayout>
</template>
