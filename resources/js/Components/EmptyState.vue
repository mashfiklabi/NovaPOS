<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 py-12 px-4 text-center dark:border-gray-800 bg-white dark:bg-gray-900 transition-colors duration-200">
        <div class="rounded-full bg-gray-50 p-3 dark:bg-gray-950">
            <svg class="h-8 w-8 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a1.5 1.5 0 00-1.5-1.5H5.25A1.5 1.5 0 003.75 7.5m16.5 0V4.5A1.5 1.5 0 0018 3H6a1.5 1.5 0 00-1.5 1.5V7.5m12-3V1.5m-6 3V1.5m0 12h.008v.008H12v-.008z" />
            </svg>
        </div>
        <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ title }}
        </h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-6">
            <slot name="actions" />
        </div>
    </div>
</template>
