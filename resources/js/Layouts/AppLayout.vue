<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';

// Get page props with full type safety
const page = usePage<PageProps>();
const authUser = computed(() => page.props.auth.user);
const settings = computed(() => page.props.settings);

// Navigation states
const isMobileSidebarOpen = ref(false);
const isUserDropdownOpen = ref(false);

// Helper function to check permissions
const hasPermission = (permission: string) => {
    return authUser.value?.permissions?.includes(permission) ?? false;
};

// Menu Items configuration with permission guards
const menuItems = computed(() => {
    const items = [
        {
            name: 'Dashboard',
            href: '/dashboard',
            icon: `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>`,
            show: hasPermission('view_dashboard'),
        },
        {
            name: 'Users',
            href: '/users',
            icon: `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>`,
            show: hasPermission('manage_users'),
        },
        {
            name: 'Roles & Permissions',
            href: '/roles',
            icon: `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>`,
            show: hasPermission('manage_roles'),
        },
        {
            name: 'Settings',
            href: '/settings',
            icon: `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`,
            show: hasPermission('manage_settings'),
        },
        {
            name: 'Audit Logs',
            href: '/audit-logs',
            icon: `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>`,
            show: hasPermission('view_audit_logs'),
        }
    ];

    return items.filter(item => item.show);
});

// Check active route
const isRouteActive = (href: string) => {
    return page.url.startsWith(href);
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 flex transition-colors duration-200">

        <!-- Desktop Sidebar -->
        <aside class="hidden lg:flex lg:flex-col lg:w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 shrink-0 h-screen sticky top-0">
            <!-- Brand header -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100 dark:border-gray-800">
                <Link href="/dashboard" class="flex items-center space-x-2">
                    <span class="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">N</span>
                    <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ settings?.shop_name || 'NovaPOS' }}
                    </span>
                </Link>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in menuItems"
                    :key="item.name"
                    :href="item.href"
                    class="flex items-center space-x-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-150"
                    :class="[
                        isRouteActive(item.href)
                            ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 font-semibold'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/50 dark:hover:text-gray-200'
                    ]"
                >
                    <span v-html="item.icon" class="shrink-0" />
                    <span>{{ item.name }}</span>
                </Link>
            </nav>

            <!-- Bottom User Profile Footer -->
            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center space-x-3 p-2 rounded-lg">
                    <div class="h-9 w-9 bg-gray-200 dark:bg-gray-800 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 font-semibold">
                        {{ authUser?.name?.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            {{ authUser?.name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ authUser?.roles?.[0] || 'User' }}
                        </p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile sidebar overlay -->
        <div v-show="isMobileSidebarOpen" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-600/75 dark:bg-gray-900/80" @click="isMobileSidebarOpen = false" />

            <div class="fixed inset-y-0 left-0 flex w-full max-w-xs bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex-col h-full z-50">
                <!-- Close button -->
                <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 dark:border-gray-800">
                    <Link href="/dashboard" class="flex items-center space-x-2" @click="isMobileSidebarOpen = false">
                        <span class="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">N</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ settings?.shop_name || 'NovaPOS' }}</span>
                    </Link>
                    <button type="button" class="text-gray-500 hover:text-gray-600" @click="isMobileSidebarOpen = false">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                    <Link
                        v-for="item in menuItems"
                        :key="item.name"
                        :href="item.href"
                        class="flex items-center space-x-3 px-3 py-2.5 text-sm font-medium rounded-lg"
                        :class="[
                            isRouteActive(item.href)
                                ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800/50'
                        ]"
                        @click="isMobileSidebarOpen = false"
                    >
                        <span v-html="item.icon" class="shrink-0" />
                        <span>{{ item.name }}</span>
                    </Link>
                </nav>
            </div>
        </div>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
            <!-- Top Header Navbar -->
            <header class="h-16 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 transition-colors duration-200">
                <!-- Mobile burger button -->
                <button
                    type="button"
                    class="lg:hidden text-gray-500 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                    @click="isMobileSidebarOpen = true"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Left navbar spacing/breadcrumb space placeholder -->
                <div class="hidden sm:block">
                    <span class="text-xs text-gray-400 font-medium">Retail POS & Inventory Core</span>
                </div>

                <!-- Right profile area -->
                <div class="flex items-center space-x-4">
                    <!-- User drop down menu -->
                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center space-x-2 text-sm focus:outline-none"
                            @click="isUserDropdownOpen = !isUserDropdownOpen"
                        >
                            <span class="hidden md:inline-block font-medium text-gray-700 dark:text-gray-300">
                                {{ authUser?.name }}
                            </span>
                            <div class="h-8 w-8 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 font-semibold border border-gray-200 dark:border-gray-700">
                                {{ authUser?.name ? authUser.name.charAt(0) : 'U' }}
                            </div>
                        </button>

                        <!-- Dropdown panel -->
                        <div v-show="isUserDropdownOpen" class="absolute right-0 mt-2 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10 z-50">
                            <!-- Backdrop closer -->
                            <div class="fixed inset-0 z-[-1]" @click="isUserDropdownOpen = false" />

                            <Link href="/profile" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700" @click="isUserDropdownOpen = false">
                                My Profile
                            </Link>
                            <Link href="/logout" method="post" as="button" class="w-full text-left block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700" @click="isUserDropdownOpen = false">
                                Sign Out
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Page Content Section -->
            <main class="flex-1 p-4 sm:p-6 md:p-8">
                <slot />
            </main>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 py-4 px-6 text-center text-xs text-gray-500 dark:text-gray-400 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                <p>&copy; {{ new Date().getFullYear() }} {{ settings?.shop_name || 'NovaPOS' }}. All rights reserved.</p>
                <p>Designed with Stripe & Notion minimalism.</p>
            </footer>
        </div>
    </div>
</template>
