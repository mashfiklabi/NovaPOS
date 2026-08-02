<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Chart from 'chart.js/auto';
import { PageProps } from '@/types';

const props = defineProps<{
    metrics: {
        today_sales: number;
        today_purchases: number;
        total_products: number;
        total_customers: number;
        total_suppliers: number;
    };
    low_stock_alerts: Array<{
        id: number;
        name: string;
        sku: string;
        stock: number;
        min_stock: number;
    }>;
    recent_sales: Array<{
        id: number;
        invoice_no: string;
        customer: string;
        items: number;
        total: number;
        status: string;
        time: string;
    }>;
    sales_chart_data: {
        labels: string[];
        sales: number[];
        purchases: number[];
    };
}>();

const page = usePage<PageProps>();
const settings = computed(() => page.props.settings);

// Format money helper
const formatMoney = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: settings.value?.currency || 'USD',
    }).format(value);
};

// Chart reference
const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

onMounted(() => {
    if (chartCanvas.value) {
        const ctx = chartCanvas.value.getContext('2d');
        if (ctx) {
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: props.sales_chart_data.labels,
                    datasets: [
                        {
                            label: 'Sales',
                            data: props.sales_chart_data.sales,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.05)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 2,
                        },
                        {
                            label: 'Purchases',
                            data: props.sales_chart_data.purchases,
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
                                font: {
                                    weight: 'bold'
                                }
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
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <PageHeader title="Overview" :breadcrumbs="[{ name: 'Dashboard' }]">
            <template #actions>
                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                    Live system status OK
                </span>
            </template>
        </PageHeader>

        <!-- Metrics cards row -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5 mb-8">
            <Card title="Today's Sales" class="relative overflow-hidden">
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ formatMoney(metrics.today_sales) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/30 dark:text-green-400">
                        +12.5%
                    </span>
                </div>
                <p class="mt-1 text-xs text-gray-500">vs yesterday</p>
            </Card>

            <Card title="Today's Purchase">
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ formatMoney(metrics.today_purchases) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950/30 dark:text-blue-400">
                        Stable
                    </span>
                </div>
                <p class="mt-1 text-xs text-gray-500">supplier invoices</p>
            </Card>

            <Card title="Total Products">
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ metrics.total_products }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-gray-500">active catalog items</p>
            </Card>

            <Card title="Customers">
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ metrics.total_customers }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-gray-500">registered customers</p>
            </Card>

            <Card title="Suppliers">
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ metrics.total_suppliers }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-gray-500">vendors configured</p>
            </Card>
        </div>

        <!-- Charts and tables row -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
            <!-- Sales & Purchases Trend Line Chart -->
            <div class="lg:col-span-2">
                <Card title="Sales & Purchases Flow">
                    <div class="h-80 relative mt-4">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </Card>
            </div>

            <!-- Low Stock Alerts -->
            <div>
                <Card title="Low Stock Alerts" subtitle="Items requiring immediate restock">
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-my-5 divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="item in low_stock_alerts" :key="item.id" class="py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ item.name }}
                                        </p>
                                        <p class="truncate text-xs text-gray-500">
                                            SKU: {{ item.sku }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-950/30 dark:text-red-400">
                                            Stock: {{ item.stock }}
                                        </span>
                                        <span class="mt-1 text-[10px] text-gray-400">
                                            Target: {{ item.min_stock }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Recent transactions table card -->
        <Card title="Recent POS Sessions & Transactions" subtitle="Track real-time checkout activities">
            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                    <thead class="bg-gray-50/75 dark:bg-gray-950/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Invoice No</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Items</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Total</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        <tr v-for="sale in recent_sales" :key="sale.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ sale.invoice_no }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                {{ sale.customer }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                {{ sale.items }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatMoney(sale.total) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="[
                                        sale.status === 'Paid'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                    ]"
                                >
                                    {{ sale.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-400">
                                {{ sale.time }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
