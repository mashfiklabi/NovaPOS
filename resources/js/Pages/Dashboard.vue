<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import { formatCurrency, formatDate } from '@/Composables/useFormatters';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppCard from '@/Components/AppCard.vue';
import AppTable from '@/Components/AppTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Heroicon from '@/Components/Heroicon.vue';
import Chart from 'chart.js/auto';

interface LowStockProduct {
    id: number;
    name: string;
    sku: string;
    current_stock: number;
    stock_alert_threshold: number;
}

interface RecentPurchase {
    id: number;
    po_number: string;
    supplier: string;
    purchase_date: string;
    grand_total: number;
    due_amount: number;
    status: string;
    payment_status: string;
}

interface RecentActivity {
    id: number;
    user: string;
    action: string;
    timestamp: string;
}

const props = defineProps<{
    metrics: {
        today_purchases_count: number;
        today_purchases_total: number;
        outstanding_purchase_due: number;
        total_products: number;
        active_products: number;
        low_stock_count: number;
        out_of_stock_count: number;
        total_users: number;
        total_roles: number;
    };
    low_stock_products: LowStockProduct[];
    recent_purchases: RecentPurchase[];
    chart_data: {
        labels: string[];
        purchases: number[];
    };
    recent_activities: RecentActivity[];
    permissions: {
        can_view_products: boolean;
        can_view_purchases: boolean;
        can_view_users: boolean;
    };
}>();


// Chart.js Setup
const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

onMounted(() => {
    if (chartCanvas.value) {
        const ctx = chartCanvas.value.getContext('2d');
        if (ctx) {
            const isDark = document.documentElement.classList.contains('dark');
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: props.chart_data.labels,
                    datasets: [
                        {
                            label: 'Daily Purchases',
                            data: props.chart_data.purchases,
                            backgroundColor: 'rgba(79, 70, 229, 0.85)',
                            hoverBackgroundColor: '#4f46e5',
                            borderRadius: 6,
                            barThickness: 24,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => formatCurrency(Number(context.raw))
                            }
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)',
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
                                callback: (val) => formatCurrency(Number(val))
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: isDark ? '#9ca3af' : '#6b7280',
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
</script>

<template>
    <AppLayout>
        <Head title="NovaPOS Dashboard" />

        <PageHeader title="Dashboard" subtitle="Overview of store operations, inventory metrics, and purchasing trends." :breadcrumbs="[{ name: 'Dashboard' }]">
            <template #actions>
                <div class="flex items-center space-x-2">
                    <Link
                        v-if="permissions.can_view_purchases"
                        href="/sales/create"
                        class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-sm transition-colors"
                    >
                        + New Sale / POS
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Nova 5 Metric Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Metric 1: Today's Purchases -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Today's Purchases</span>
                    <div class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <Heroicon name="ShoppingBagIcon" class="h-5 w-5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                    {{ formatCurrency(metrics.today_purchases_total) }}
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ metrics.today_purchases_count }} orders placed today</span>
                    <Link v-if="permissions.can_view_purchases" href="/purchases" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">View</Link>
                </div>
            </div>

            <!-- Metric 2: Outstanding Balance Due -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Outstanding Due</span>
                    <div class="p-2 rounded-lg bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400">
                        <Heroicon name="ScaleIcon" class="h-5 w-5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-red-600 dark:text-red-400 tracking-tight">
                    {{ formatCurrency(metrics.outstanding_purchase_due) }}
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>Unpaid supplier balances</span>
                    <Link v-if="permissions.can_view_purchases" href="/purchases?payment_status=unpaid" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Unpaid</Link>
                </div>
            </div>

            <!-- Metric 3: Active Catalog Products -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Catalog</span>
                    <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                        <Heroicon name="ArchiveBoxIcon" class="h-5 w-5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                    {{ metrics.active_products }} <span class="text-sm font-normal text-gray-400">/ {{ metrics.total_products }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>Active items in catalog</span>
                    <Link v-if="permissions.can_view_products" href="/products" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Products</Link>
                </div>
            </div>

            <!-- Metric 4: Low Stock Alert Count -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Low Stock Alert</span>
                    <div class="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                        <Heroicon name="TagIcon" class="h-5 w-5" />
                    </div>
                </div>
                <div class="text-2xl font-black tracking-tight" :class="metrics.low_stock_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white'">
                    {{ metrics.low_stock_count }}
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ metrics.out_of_stock_count }} completely out of stock</span>
                    <Link v-if="permissions.can_view_products" href="/products" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Check</Link>
                </div>
            </div>
        </div>

        <!-- Middle Row: 7-Day Trend Chart & Inventory Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Purchase Trend Chart (2 cols) -->
            <div class="lg:col-span-2">
                <AppCard title="Purchasing Trend (Last 7 Days)" subtitle="Daily supplier purchase volume">
                    <div class="h-72 relative mt-4">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </AppCard>
            </div>

            <!-- Inventory Breakdown Card (1 col) -->
            <div class="space-y-6">
                <AppCard title="Inventory Breakdown" subtitle="Real-time stock statuses">
                    <div class="space-y-4 mt-2">
                        <div class="flex justify-between items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800">
                            <div class="flex items-center space-x-3">
                                <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Active Items</span>
                            </div>
                            <span class="text-sm font-black text-gray-900 dark:text-white">{{ metrics.active_products }}</span>
                        </div>

                        <div class="flex justify-between items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800">
                            <div class="flex items-center space-x-3">
                                <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Low Stock Alert</span>
                            </div>
                            <span class="text-sm font-black text-amber-600 dark:text-amber-400">{{ metrics.low_stock_count }}</span>
                        </div>

                        <div class="flex justify-between items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800">
                            <div class="flex items-center space-x-3">
                                <span class="h-3 w-3 rounded-full bg-red-500"></span>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Out of Stock</span>
                            </div>
                            <span class="text-sm font-black text-red-600 dark:text-red-400">{{ metrics.out_of_stock_count }}</span>
                        </div>

                        <div v-if="permissions.can_view_purchases" class="pt-2">
                            <Link
                                href="/purchases/create"
                                class="w-full flex items-center justify-center py-2 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition-colors shadow-sm"
                            >
                                + Create Restock Purchase Order
                            </Link>
                        </div>
                    </div>
                </AppCard>
            </div>
        </div>

        <!-- Tables Row: Low Stock Alerts & Recent Purchases -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Low Stock Alert List -->
            <AppCard title="Low Stock Replenishment Alerts" subtitle="Products requiring restock orders">
                <div class="mt-4">
                    <div v-if="low_stock_products.length === 0" class="py-6">
                        <EmptyState
                            title="Inventory Healthy"
                            description="No products are currently below their stock alert thresholds."
                        />
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-xs">
                            <thead>
                                <tr class="text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="py-2.5 px-3">Product Name</th>
                                    <th class="py-2.5 px-3">SKU</th>
                                    <th class="py-2.5 px-3 text-right">Current Stock</th>
                                    <th class="py-2.5 px-3 text-right">Threshold</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60 font-medium">
                                <tr v-for="prod in low_stock_products" :key="prod.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="py-2.5 px-3 text-gray-900 dark:text-gray-100 font-bold">
                                        {{ prod.name }}
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-500 font-mono">
                                        {{ prod.sku }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-black" :class="prod.current_stock <= 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'">
                                        {{ prod.current_stock }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-500">
                                        {{ prod.stock_alert_threshold }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="prod.current_stock <= 0 ? 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400'"
                                        >
                                            {{ prod.current_stock <= 0 ? 'Out of Stock' : 'Low Stock' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </AppCard>

            <!-- Recent Purchase Orders -->
            <AppCard title="Recent Purchase Orders" subtitle="Latest inventory acquisitions">
                <div class="mt-4">
                    <div v-if="recent_purchases.length === 0" class="py-6">
                        <EmptyState
                            title="No Purchase Orders"
                            description="No purchase orders recorded yet."
                        />
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-xs">
                            <thead>
                                <tr class="text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="py-2.5 px-3">PO #</th>
                                    <th class="py-2.5 px-3">Supplier</th>
                                    <th class="py-2.5 px-3 text-right">Total ($)</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60 font-medium">
                                <tr v-for="po in recent_purchases" :key="po.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="py-2.5 px-3 text-indigo-600 dark:text-indigo-400 font-bold">
                                        <Link :href="`/purchases/${po.id}`" class="hover:underline">
                                            {{ po.po_number }}
                                        </Link>
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-900 dark:text-gray-100 truncate max-w-[120px]">
                                        {{ po.supplier }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-900 dark:text-gray-100 font-bold">
                                        {{ formatCurrency(po.grand_total) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold capitalize"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/50 dark:text-yellow-400': po.status === 'draft',
                                                'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-400': po.status === 'received',
                                                'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-400': po.status === 'cancelled'
                                            }"
                                        >
                                            {{ po.status }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <Link
                                            :href="`/purchases/${po.id}`"
                                            class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </AppCard>
        </div>

        <!-- Recent Audit Activity Log (if Super Admin or recent activities present) -->
        <AppCard v-if="recent_activities.length > 0" title="Recent Audit Logs" subtitle="System operation logs">
            <div class="mt-4">
                <AppTable :headers="['Time', 'User', 'Operation']">
                    <tr v-for="act in recent_activities" :key="act.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ formatDate(act.timestamp) }}
                        </td>
                        <td class="px-6 py-3 text-xs font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ act.user }}
                        </td>
                        <td class="px-6 py-3 text-xs text-gray-600 dark:text-gray-300">
                            {{ act.action }}
                        </td>
                    </tr>
                </AppTable>
            </div>
        </AppCard>
    </AppLayout>
</template>
