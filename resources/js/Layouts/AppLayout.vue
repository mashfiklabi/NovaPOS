<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import Heroicon from '@/Components/Heroicon.vue';
import { useTheme } from '@/Composables/useTheme';

// Get page props with full type safety
const page = usePage<PageProps>();
const authUser = computed(() => page.props.auth.user);
const settings = computed(() => page.props.settings);
const navigation = computed(() => page.props.navigation || []);

// Theme composable
const { theme, isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme();
});

// Navigation states
const isMobileSidebarOpen = ref(false);
const isUserDropdownOpen = ref(false);

// Check active route
const isRouteActive = (route_name: string) => {
    if (route_name === 'dashboard') {
        return page.url === '/dashboard';
    }
    const prefix = route_name.split('.')[0];
    return page.url.startsWith('/' + prefix);
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 flex transition-colors duration-200">

        <!-- Desktop Sidebar -->
        <aside class="hidden lg:flex lg:flex-col lg:w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 shrink-0 h-screen sticky top-0">
            <!-- Brand header -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100 dark:border-gray-800">
                <Link href="/dashboard" class="flex items-center space-x-2">
                    <img v-if="settings?.logo" :src="`/storage/${settings.logo}`" class="h-8 w-auto" />
                    <span v-else class="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">N</span>
                    <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ settings?.shop_name || 'NovaPOS' }}
                    </span>
                </Link>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.title"
                    :href="route(item.route)"
                    class="flex items-center space-x-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-150"
                    :class="[
                        isRouteActive(item.route)
                            ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 font-semibold'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/50 dark:hover:text-gray-200'
                    ]"
                >
                    <Heroicon :name="item.icon" class="shrink-0 h-5 w-5" />
                    <span>{{ item.title }}</span>
                </Link>
            </nav>

            <!-- Bottom User Profile Footer -->
            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center space-x-3 p-2 rounded-lg">
                    <div class="h-9 w-9 bg-gray-200 dark:bg-gray-800 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 font-semibold border border-gray-300 dark:border-gray-700">
                        <img v-if="authUser?.avatar" :src="`/storage/${authUser.avatar}`" class="h-9 w-9 rounded-full object-cover" />
                        <span v-else>{{ authUser?.name?.charAt(0) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            {{ authUser?.name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ authUser?.roles?.[0] || 'Store Associate' }}
                        </p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile sidebar overlay -->
        <div v-show="isMobileSidebarOpen" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-600/75 dark:bg-gray-900/80" @click="isMobileSidebarOpen = false" />

            <div class="fixed inset-y-0 left-0 flex w-full max-w-xs bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex-col h-full z-50">
                <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 dark:border-gray-800">
                    <Link href="/dashboard" class="flex items-center space-x-2" @click="isMobileSidebarOpen = false">
                        <img v-if="settings?.logo" :src="`/storage/${settings.logo}`" class="h-8 w-auto" />
                        <span v-else class="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">N</span>
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
                        v-for="item in navigation"
                        :key="item.title"
                        :href="route(item.route)"
                        class="flex items-center space-x-3 px-3 py-2.5 text-sm font-medium rounded-lg"
                        :class="[
                            isRouteActive(item.route)
                                ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800/50'
                        ]"
                        @click="isMobileSidebarOpen = false"
                    >
                        <Heroicon :name="item.icon" class="shrink-0 h-5 w-5" />
                        <span>{{ item.title }}</span>
                    </Link>
                </nav>
            </div>
        </div>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
            <!-- Top Header Navbar -->
            <header class="h-16 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 transition-colors duration-200">
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

                <div class="hidden sm:block">
                    <span class="text-xs text-gray-400 font-medium">Retail POS & Inventory Core</span>
                </div>

                <!-- Right profile & theme toggle area -->
                <div class="flex items-center space-x-3">
                    <!-- Theme toggle button -->
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-colors"
                        :title="theme === 'light' ? 'Mode: Light (Click for Dark)' : theme === 'dark' ? 'Mode: Dark (Click for System)' : 'Mode: System (Click for Light)'"
                    >
                        <!-- Sun Icon for Light Mode -->
                        <svg v-if="theme === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <!-- Moon Icon for Dark Mode -->
                        <svg v-else-if="theme === 'dark'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <!-- System Desktop Icon for System Mode -->
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </button>

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
                            <div class="h-8 w-8 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 font-semibold border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <img v-if="authUser?.avatar" :src="`/storage/${authUser.avatar}`" class="h-8 w-8 rounded-full object-cover" />
                                <span v-else>{{ authUser?.name ? authUser.name.charAt(0) : 'U' }}</span>
                            </div>
                        </button>

                        <div v-show="isUserDropdownOpen" class="absolute right-0 mt-2 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10 z-50">
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

            <main class="flex-1 p-4 sm:p-6 md:p-8">
                <slot />
            </main>

            <footer class="bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 py-4 px-6 text-center text-xs text-gray-500 dark:text-gray-400 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                <p>&copy; {{ new Date().getFullYear() }} {{ settings?.shop_name || 'NovaPOS' }}. All rights reserved.</p>
                <p>Designed with Stripe & Notion minimalism.</p>
            </footer>
        </div>
    </div>
</template>
