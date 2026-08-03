<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import AppCard from '@/Components/AppCard.vue';
import AppTable from '@/Components/AppTable.vue';
import Chart from 'chart.js/auto';

interface RecentActivity {
    id: number;
    user: string;
    action: string;
    ip: string;
    browser: string;
    timestamp: string;
}

interface LatestLogin {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    last_login_at: string | null;
}

const props = defineProps<{
    metrics: {
        users: number;
        roles: number;
        permissions: number;
        settings: number;
    };
    recent_activities: RecentActivity[];
    latest_logins: LatestLogin[];
    chart_data: {
        labels: string[];
        sales: number[];
        purchases: number[];
    };
}>();

// Chart setup
const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

onMounted(() => {
    if (chartCanvas.value) {
        const ctx = chartCanvas.value.getContext('2d');
        if (ctx) {
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: props.chart_data.labels,
                    datasets: [
                        {
                            label: 'Sales ($)',
                            data: props.chart_data.sales,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.05)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 2,
                        },
                        {
                            label: 'Purchases ($)',
                            data: props.chart_data.purchases,
                            borderColor: '#06b6d4',
                            backgroundColor: 'rgba(6, 182, 212, 0.05)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#4b5563',
                            }
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: 'rgba(156, 163, 175, 0.1)',
                            },
                            ticks: {
                                color: '#9ca3af',
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#9ca3af',
                            }
                        }
                    }
                }
            });
        }
    }
});

onUnmounted(() => {
    if (chartInstance) {
        chartInstance.destroy();
    }
});

// Format timestamp
const formatTime = (timeString: string) => {
    return new Date(timeString).toLocaleString('en-US', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};
</script>

<template>
    <AppLayout>
        <Head title="System Dashboard" />

        <PageHeader title="Dashboard" :breadcrumbs="[{ name: 'Dashboard' }]" />

        <!-- Stat Card Rows -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <StatCard
                title="System Users"
                :value="metrics.users"
                description="Active store & admin accounts"
                :trend="{ label: 'Manage Accounts', type: 'stable' }"
            />
            <StatCard
                title="Spatie Roles"
                :value="metrics.roles"
                description="Custom RBAC authorization roles"
                :trend="{ label: 'Configured', type: 'stable' }"
            />
            <StatCard
                title="Spatie Permissions"
                :value="metrics.permissions"
                description="Granular permission gates"
                :trend="{ label: 'Operational', type: 'stable' }"
            />
            <StatCard
                title="Settings Parameters"
                :value="metrics.settings"
                description="Shop localization properties"
                :trend="{ label: 'Configured', type: 'stable' }"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
            <!-- Line Chart of Checkout Flow -->
            <div class="lg:col-span-2">
                <AppCard title="Checkout Flow Analytics" subtitle="A visual summary of store sales vs inventory purchases.">
                    <div class="h-80 relative mt-4">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </AppCard>
            </div>

            <!-- Latest User Logins -->
            <div>
                <AppCard title="Latest Access & Logins" subtitle="Verify authorized personnel checkouts">
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-my-5 divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="user in latest_logins" :key="user.id" class="py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="h-8 w-8 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center font-bold text-sm text-gray-700 dark:text-gray-300">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold text-gray-900 dark:text-gray-100">
                                            {{ user.name }}
                                        </p>
                                        <p class="truncate text-[10px] text-gray-500">
                                            {{ user.email }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-gray-400">
                                            {{ user.last_login_at ? formatTime(user.last_login_at) : 'Never' }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <li v-if="latest_logins.length === 0" class="py-4 text-center text-xs text-gray-400">
                                No user logins recorded yet.
                            </li>
                        </ul>
                    </div>
                </AppCard>
            </div>
        </div>

        <!-- Recent Activities Card -->
        <AppCard title="Recent Audits & Operations Logs" subtitle="Track user actions, changes to settings, or modifications to accounts.">
            <div class="mt-4">
                <AppTable :headers="['Trigger Time', 'Trigger User', 'Action Description', 'IP Address', 'Browser/User-Agent']">
                    <tr v-for="act in recent_activities" :key="act.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">
                            {{ formatTime(act.timestamp) }}
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ act.user }}
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-600 dark:text-gray-300">
                            {{ act.action }}
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500 font-mono whitespace-nowrap">
                            {{ act.ip }}
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-400 max-w-xs truncate whitespace-nowrap">
                            {{ act.browser }}
                        </td>
                    </tr>
                    <tr v-if="recent_activities.length === 0">
                        <td colspan="5" class="py-8 text-center text-xs text-gray-400">
                            No recent activity recorded yet.
                        </td>
                    </tr>
                </AppTable>
            </div>
        </AppCard>
    </AppLayout>
</template>
