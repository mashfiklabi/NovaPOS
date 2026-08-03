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

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    status: string;
    phone: string | null;
    avatar: string | null;
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

// Search filtration
const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/users', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

// Form and Drawer states
const isDrawerOpen = ref(false);
const editingUser = ref<User | null>(null);

const form = useForm({
    _method: 'POST', // standard override to support multipart with put
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    role_names: [] as string[],
    avatar: null as File | null,
});

const handleFile = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    if (files && files.length > 0) {
        form.avatar = files[0];
    }
};

const openCreateDrawer = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.status = 'active';
    isDrawerOpen.value = true;
};

const openEditDrawer = (user: User) => {
    editingUser.value = user;
    form.clearErrors();
    form._method = 'PUT';
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone || '';
    form.password = '';
    form.password_confirmation = '';
    form.status = user.status;
    form.role_names = user.roles.map(r => r.name);
    form.avatar = null;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    editingUser.value = null;
    form.reset();
};

const submit = () => {
    if (editingUser.value) {
        // multipart/form-data upload using post with _method=PUT to support avatars
        form.post(`/users/${editingUser.value.id}`, {
            onSuccess: () => closeDrawer(),
        });
    } else {
        form.post('/users', {
            onSuccess: () => closeDrawer(),
        });
    }
};

const deleteUser = (user: User) => {
    if (confirm(`Are you sure you want to soft-delete ${user.name}? They will lose account access.`)) {
        router.delete(`/users/${user.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <Head title="System Users & RBAC Management" />

        <PageHeader title="Users" :breadcrumbs="[{ name: 'Users' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search user accounts..." class="mr-2" />
                <AppButton variant="primary" @click="openCreateDrawer">
                    Add User
                </AppButton>
            </template>
        </PageHeader>

        <!-- User index container -->
        <AppCard no-padding>
            <div v-if="users.data.length === 0" class="p-6">
                <EmptyState
                    title="No users recorded"
                    description="You can define roles, assign access permissions, and configure employee checkout accounts."
                >
                    <template #actions>
                        <AppButton variant="primary" @click="openCreateDrawer">
                            Configure Store Associate
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <div v-else>
                <AppTable :headers="['Associate', 'Phone', 'Access Roles', 'Status', 'Actions']">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-9 w-9 rounded-full bg-gray-150 border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex items-center justify-center font-bold text-gray-700 dark:text-gray-300">
                                    <img v-if="user.avatar" :src="`/storage/${user.avatar}`" class="h-9 w-9 rounded-full object-cover" />
                                    <span v-else>{{ user.name.charAt(0) }}</span>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ user.name }}</p>
                                    <p class="text-xs text-gray-400">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ user.phone || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span
                                v-for="role in user.roles"
                                :key="role.id"
                                class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-700/10 dark:bg-indigo-950/40 dark:text-indigo-400 mr-1"
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
                </AppTable>
                <AppPagination :links="users.links" />
            </div>
        </AppCard>

        <!-- User Create / Edit Drawer Overlay -->
        <AppDrawer
            :show="isDrawerOpen"
            :title="editingUser ? 'Edit User Credentials' : 'Create User Account'"
            @close="closeDrawer"
        >
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <AppInput label="Associate Full Name" v-model="form.name" :error="form.errors.name" required />
                </div>

                <div>
                    <AppInput label="Email Address" type="email" v-model="form.email" :error="form.errors.email" required />
                </div>

                <div>
                    <AppInput label="Phone Number" v-model="form.phone" :error="form.errors.phone" />
                </div>

                <div>
                    <AppInput :label="editingUser ? 'Change Password (optional)' : 'Password'" type="password" v-model="form.password" :error="form.errors.password" :required="!editingUser" />
                </div>

                <div>
                    <AppInput label="Confirm Password" type="password" v-model="form.password_confirmation" />
                </div>

                <div>
                    <AppSelect
                        label="Account Status"
                        v-model="form.status"
                        :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                            { value: 'suspended', label: 'Suspended' }
                        ]"
                        :error="form.errors.status"
                        required
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Avatar Profile Photo</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleFile"
                        class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <p v-if="form.errors.avatar" class="mt-1 text-xs text-red-500">{{ form.errors.avatar }}</p>
                </div>

                <!-- Roles checklist -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Select Spatie Roles</label>
                    <div class="mt-2 space-y-2">
                        <div v-for="role in roles" :key="role.id" class="flex items-start">
                            <div class="flex h-5 items-center">
                                <input
                                    :id="`role-chk-${role.id}`"
                                    type="checkbox"
                                    :value="role.name"
                                    v-model="form.role_names"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                                />
                            </div>
                            <div class="ml-3 text-sm">
                                <label :for="`role-chk-${role.id}`" class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ role.name }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.role_names" class="mt-1 text-xs text-red-500">{{ form.errors.role_names }}</p>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="closeDrawer">
                    Cancel
                </AppButton>
                <AppButton variant="primary" :loading="form.processing" @click="submit" class="ml-3">
                    {{ editingUser ? 'Update Profile' : 'Register User' }}
                </AppButton>
            </template>
        </AppDrawer>
    </AppLayout>
</template>
