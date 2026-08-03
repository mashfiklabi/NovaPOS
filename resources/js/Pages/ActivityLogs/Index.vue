<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppTable from '@/Components/AppTable.vue';
import AppPagination from '@/Components/AppPagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import EmptyState from '@/Components/EmptyState.vue';

interface Causer {
    id: number;
    name: string;
    email: string;
}

interface ActivityItem {
    id: number;
    description: string;
    event: string | null;
    causer: Causer | null;
    properties: Record<string, any>;
    created_at: string;
}

const props = defineProps<{
    activities: {
        data: ActivityItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string | null;
    };
}>();

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get('/activity-logs', { search: value }, {
        preserveState: true,
        replace: true,
    });
});

const formatTime = (timeString: string) => {
    return new Date(timeString).toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <AppLayout>
        <Head title="System Audits & Activity History" />

        <PageHeader title="Activity Logs" :breadcrumbs="[{ name: 'Activity History' }]">
            <template #actions>
                <SearchInput v-model="search" placeholder="Search trigger, action or user..." />
            </template>
        </PageHeader>

        <AppCard no-padding>
            <div v-if="activities.data.length === 0" class="p-6">
                <EmptyState
                    title="No operations logged"
                    description="Whenever store administrators modify catalog settings, configure user roles, or checkouts occur, dynamic actions are logged here."
                />
            </div>

            <div v-else>
                <AppTable :headers="['Timestamp', 'User', 'Event Type', 'Description', 'Properties']">
                    <tr v-for="act in activities.data" :key="act.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-500 whitespace-nowrap">
                            {{ formatTime(act.created_at) }}
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <div class="h-6 w-6 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center font-bold text-xs dark:bg-indigo-950/40 dark:text-indigo-400">
                                    {{ act.causer ? act.causer.name.charAt(0) : 'S' }}
                                </div>
                                <span class="font-semibold text-gray-900 dark:text-gray-200">
                                    {{ act.causer ? act.causer.name : 'System' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap font-mono text-xs uppercase">
                            <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                {{ act.event || 'Log' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                            {{ act.description }}
                        </td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-400 max-w-xs truncate whitespace-nowrap">
                            {{ act.properties && Object.keys(act.properties).length ? JSON.stringify(act.properties) : '{}' }}
                        </td>
                    </tr>
                </AppTable>
                <AppPagination :links="activities.links" />
            </div>
        </AppCard>
    </AppLayout>
</template>
