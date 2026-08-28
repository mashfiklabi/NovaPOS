<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import Heroicon from '@/Components/Heroicon.vue';
import { useTheme } from '@/Composables/useTheme';

interface AppNotification {
    id: string;
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string;
}

// Get page props with full type safety
const page = usePage<PageProps & {
    notifications?: AppNotification[];
    unread_notifications_count?: number;
    auth: PageProps['auth'] & { can_view_notifications?: boolean };
}>();

const authUser = computed(() => page.props.auth.user);
const settings = computed(() => page.props.settings);
const navigation = computed(() => page.props.navigation || []);

const canViewNotifications = computed(() => {
    return page.props.auth.can_view_notifications ?? false;
});

const notifications = computed(() => page.props.notifications || []);
const unreadCount = computed(() => page.props.unread_notifications_count || 0);

// Theme composable
const { theme, isDark, toggleTheme, initTheme } = useTheme();

// Sidebar collapse state
const isSidebarCollapsed = ref(localStorage.getItem('novapos_sidebar_collapsed') === 'true');

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('novapos_sidebar_collapsed', String(isSidebarCollapsed.value));
};

onMounted(() => {
    initTheme();
});

// Navigation & Dropdown states
const isMobileSidebarOpen = ref(false);
const isUserDropdownOpen = ref(false);
const isNotificationDropdownOpen = ref(false);

// Check active route
const isRouteActive = (route_name: string) => {
    if (route_name === 'dashboard') {
        return page.url === '/dashboard';
    }
    const prefix = route_name.split('.')[0];
    return page.url.startsWith('/' + prefix);
};

const markAsRead = (notification: AppNotification) => {
    router.post(`/notifications/${notification.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (notification.url) {
                router.get(notification.url);
            }
        }
    });
};

const markAllAsRead = () => {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 flex transition-colors duration-200">

        <!-- Desktop Sidebar -->
        <aside
            class="hidden lg:flex lg:flex-col bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 shrink-0 h-screen sticky top-0 transition-all duration-200 z-40"
            :class="isSidebarCollapsed ? 'w-20' : 'w-64'"
        >
            <!-- Brand header & Collapse Toggle -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 dark:border-gray-800">
                <Link href="/dashboard" class="flex items-center space-x-2 overflow-hidden">
                    <img v-if="settings?.logo" :src="`/storage/${settings.logo}`" class="h-8 w-auto shrink-0" />
                    <span v-else class="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-lg shrink-0">N</span>
                    <span v-show="!isSidebarCollapsed" class="text-lg font-bold tracking-tight text-gray-900 dark:text-gray-100 truncate">
                        {{ settings?.shop_name || 'NovaPOS' }}
                    </span>
                </Link>

                <button
                    type="button"
                    @click="toggleSidebar"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-colors"
                    :title="isSidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
                >
                    <Heroicon v-if="isSidebarCollapsed" name="ChevronRightIcon" class="h-5 w-5" />
                    <Heroicon v-else name="ChevronLeftIcon" class="h-5 w-5" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.title"
                    :href="route(item.route)"
                    :title="item.title"
                    class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-150"
                    :class="[
                        isSidebarCollapsed ? 'justify-center' : 'space-x-3',
                        isRouteActive(item.route)
                            ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 font-semibold'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/50 dark:hover:text-gray-200'
                    ]"
                >
                    <Heroicon :name="item.icon" class="shrink-0 h-5 w-5" />
                    <span v-show="!isSidebarCollapsed" class="truncate">{{ item.title }}</span>
                </Link>
            </nav>

            <!-- Bottom User Profile Footer -->
            <div class="p-3 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center p-1 rounded-lg" :class="isSidebarCollapsed ? 'justify-center' : 'space-x-3'">
                    <div class="h-9 w-9 bg-gray-200 dark:bg-gray-800 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 font-semibold border border-gray-300 dark:border-gray-700 shrink-0">
                        <img v-if="authUser?.avatar" :src="`/storage/${authUser.avatar}`" class="h-9 w-9 rounded-full object-cover" />
                        <span v-else>{{ authUser?.name?.charAt(0) }}</span>
                    </div>
                    <div v-show="!isSidebarCollapsed" class="flex-1 min-w-0">
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
        <div v-show="isMobileSidebarOpen" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
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
            <!-- Uniform Top Header Navbar -->
            <header class="h-16 shrink-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 transition-colors duration-200">
                <div class="flex items-center space-x-3">
                    <button
                        type="button"
                        class="lg:hidden text-gray-500 hover:text-gray-600 focus:outline-none"
                        @click="isMobileSidebarOpen = true"
                    >
                        <Heroicon name="Bars3Icon" class="h-6 w-6" />
                    </button>

                    <div class="hidden sm:block">
                        <span class="text-xs text-gray-400 font-medium">Retail POS & Inventory Core</span>
                    </div>
                </div>

                <!-- Right Profile, Notifications & Theme Toggle Area -->
                <div class="flex items-center space-x-3">
                    <!-- Notifications Dropdown (Super Admin & Manager only) -->
                    <div v-if="canViewNotifications" class="relative">
                        <button
                            type="button"
                            @click="isNotificationDropdownOpen = !isNotificationDropdownOpen"
                            class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-colors"
                            title="Notifications"
                        >
                            <Heroicon name="BellIcon" class="w-5 h-5" />
                            <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-600"></span>
                            </span>
                        </button>

                        <div
                            v-show="isNotificationDropdownOpen"
                            class="absolute right-0 mt-2 w-80 rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700 py-2 z-50 overflow-hidden"
                        >
                            <div class="fixed inset-0 z-[-1]" @click="isNotificationDropdownOpen = false" />

                            <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">Notifications</span>
                                <button
                                    v-if="unreadCount > 0"
                                    type="button"
                                    @click="markAllAsRead"
                                    class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    Mark all read
                                </button>
                            </div>

                            <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/50">
                                <div
                                    v-for="notif in notifications"
                                    :key="notif.id"
                                    @click="markAsRead(notif)"
                                    class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer transition-colors"
                                    :class="!notif.read ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''"
                                >
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ notif.title }}</p>
                                        <span class="text-[10px] text-gray-400">{{ notif.created_at }}</span>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 line-clamp-2">{{ notif.message }}</p>
                                </div>

                                <div v-if="notifications.length === 0" class="p-6 text-center text-xs text-gray-400">
                                    No notifications recorded yet.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Light / Dark theme toggle button -->
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-colors"
                        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <Heroicon v-if="isDark" name="SunIcon" class="w-5 h-5" />
                        <Heroicon v-else name="MoonIcon" class="w-5 h-5" />
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
