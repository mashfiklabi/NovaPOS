<script setup lang="ts">
withDefaults(
    defineProps<{
        rows?: number;
    }>(),
    {
        rows: 3,
    }
);
</script>

<template>
    <div class="animate-pulse space-y-4 w-full">
        <div v-for="i in rows" :key="i" class="flex space-x-4 items-center">
            <div class="h-10 bg-gray-200 dark:bg-gray-800 rounded-md flex-1"></div>
            <div class="h-10 bg-gray-200 dark:bg-gray-800 rounded-md w-24"></div>
            <div class="h-10 bg-gray-200 dark:bg-gray-800 rounded-md w-16"></div>
        </div>
    </div>
</template>
