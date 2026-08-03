<script setup lang="ts">
defineProps<{
    title: string;
    value: string | number;
    description?: string;
    trend?: {
        label: string;
        type: 'up' | 'down' | 'stable';
    };
}>();
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 transition-colors duration-200">
        <dt class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            {{ title }}
        </dt>
        <dd class="mt-2 flex items-baseline justify-between">
            <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                {{ value }}
            </span>
            <span
                v-if="trend"
                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                :class="[
                    trend.type === 'up' ? 'bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400' : '',
                    trend.type === 'down' ? 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400' : '',
                    trend.type === 'stable' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400' : '',
                ]"
            >
                {{ trend.label }}
            </span>
        </dd>
        <p v-if="description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ description }}
        </p>
    </div>
</template>
