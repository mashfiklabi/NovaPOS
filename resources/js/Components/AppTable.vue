<script setup lang="ts">
defineProps<{
    headers: string[];
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 transition-colors duration-200">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
            <thead class="bg-gray-50/75 dark:bg-gray-950/50">
                <tr>
                    <th
                        v-for="(header, index) in headers"
                        :key="index"
                        scope="col"
                        class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        {{ header }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                <slot />
            </tbody>
        </table>
    </div>
</template>
