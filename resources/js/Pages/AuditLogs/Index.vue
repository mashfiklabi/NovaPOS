<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Table from '@/Components/Table.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Drawer from '@/Components/Drawer.vue';
import EmptyState from '@/Components/EmptyState.vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuditLog {
    id: number;
    uuid: string;
    action: string;
    model_type: string | null;
    model_id: number | null;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    user: User | null;
}

const props = defineProps<{
    logs: {
        data: AuditLog[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
    };
}>();

// Search state
const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/audit-logs', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

// Selected Log for detail Drawer
const selectedLog = ref<AuditLog | null>(null);
const isDrawerOpen = ref(false);

const openDetails = (log: AuditLog) => {
    selectedLog.value = log;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    selectedLog.value = null;
};

const formatValue = (val: any): string => {
    if (val === null || val === undefined) return 'NULL';
    if (typeof val === 'object') return JSON.stringify(val, null, 2);
    return String(val);
};

// Format Timestamp Helper
const formatTimestamp = (timestampString: string) => {
    return new Date(timestampString).toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <AppLayout>
        <Head title="System Audit History" />

        <PageHeader title="Audit Logs" :breadcrumbs="[{ name: 'Audit History' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Filter by action, user or model..." />
            </template>
        </PageHeader>

        <!-- Audit list card -->
        <Card no-padding>
            <div v-if="logs.data.length === 0" class="p-6">
                <EmptyState
                    title="No logs recorded"
                    description="When administrators configure roles, adjust parameters, or edit users, system-wide audits will appear here."
                />
            </div>

            <div v-else>
                <Table :headers="['Timestamp', 'User', 'Action', 'Model Reference', 'Network IP', 'Actions']">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ formatTimestamp(log.created_at) }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <div class="h-6 w-6 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center font-bold text-xs dark:bg-indigo-950/40 dark:text-indigo-400">
                                    {{ log.user ? log.user.name.charAt(0) : 'S' }}
                                </div>
                                <span class="font-medium text-gray-900 dark:text-gray-200">
                                    {{ log.user ? log.user.name : 'System/Scheduler' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap font-mono text-xs">
                            <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            <span v-if="log.model_type" class="text-xs">
                                {{ log.model_type.split('\\').pop() }} #{{ log.model_id }}
                            </span>
                            <span v-else class="text-gray-400">N/A</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap font-mono text-xs">
                            {{ log.ip_address || '127.0.0.1' }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <button
                                @click="openDetails(log)"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                Inspect Change
                            </button>
                        </td>
                    </tr>
                </Table>
                <Pagination :links="logs.links" />
            </div>
        </Card>

        <!-- Inspect Log Drawer -->
        <Drawer :show="isDrawerOpen" title="Audit Payload Details" @close="closeDrawer">
            <div v-if="selectedLog" class="space-y-6">
                <!-- Log Meta information -->
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950 space-y-2 border border-gray-100 dark:border-gray-800">
                    <p class="text-xs text-gray-500"><strong>Triggered By:</strong> {{ selectedLog.user ? selectedLog.user.name : 'System' }} ({{ selectedLog.user ? selectedLog.user.email : 'system' }})</p>
                    <p class="text-xs text-gray-500"><strong>Operation:</strong> <span class="font-mono bg-gray-200 px-1 rounded dark:bg-gray-800">{{ selectedLog.action }}</span></p>
                    <p class="text-xs text-gray-500"><strong>Target Record:</strong> {{ selectedLog.model_type ? selectedLog.model_type.split('\\').pop() : 'N/A' }} (ID: {{ selectedLog.model_id || 'N/A' }})</p>
                    <p class="text-xs text-gray-500"><strong>IP Address:</strong> {{ selectedLog.ip_address || '127.0.0.1' }}</p>
                    <p class="text-xs text-gray-500"><strong>User Agent/Browser:</strong> <span class="text-gray-400 leading-relaxed">{{ selectedLog.user_agent || 'Unknown browser' }}</span></p>
                </div>

                <!-- Attributes Audit payloads -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-200">Data Delta Difference</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Old values -->
                        <div>
                            <h4 class="text-xs font-medium text-gray-400 uppercase mb-2">Before Change</h4>
                            <div class="rounded-lg bg-red-50/50 p-3 border border-red-100 dark:bg-red-950/10 dark:border-red-900/30 font-mono text-xs text-red-700 dark:text-red-400 overflow-x-auto max-h-80">
                                <template v-if="selectedLog.old_values">
                                    <div v-for="(val, key) in selectedLog.old_values" :key="key" class="mb-1">
                                        <span class="font-semibold">{{ key }}:</span> {{ formatValue(val) }}
                                    </div>
                                </template>
                                <span v-else class="text-gray-400 italic">No historical data</span>
                            </div>
                        </div>

                        <!-- New values -->
                        <div>
                            <h4 class="text-xs font-medium text-gray-400 uppercase mb-2">After Change</h4>
                            <div class="rounded-lg bg-green-50/50 p-3 border border-green-100 dark:bg-green-950/10 dark:border-green-900/30 font-mono text-xs text-green-700 dark:text-green-400 overflow-x-auto max-h-80">
                                <template v-if="selectedLog.new_values">
                                    <div v-for="(val, key) in selectedLog.new_values" :key="key" class="mb-1">
                                        <span class="font-semibold">{{ key }}:</span> {{ formatValue(val) }}
                                    </div>
                                </template>
                                <span v-else class="text-gray-400 italic">Deleted/Null values</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="closeDrawer"
                >
                    Close
                </button>
            </template>
        </Drawer>
    </AppLayout>
</template>
