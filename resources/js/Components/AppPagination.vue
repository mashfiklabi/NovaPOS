<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}>();
</script>

<template>
    <div v-if="links.length > 3" class="flex items-center justify-between border-t border-gray-100 bg-white px-4 py-3 sm:px-6 dark:border-gray-800 dark:bg-gray-900 rounded-b-xl transition-colors duration-200">
        <div class="flex flex-1 justify-between sm:hidden">
            <template v-for="(link, key) in links" :key="key">
                <Link
                    v-if="link.url && (key === 0 || key === links.length - 1)"
                    :href="link.url"
                    class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    v-html="link.label"
                />
            </template>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-end">
            <nav class="isolate inline-flex -space-x-px rounded-md shadow-xs" aria-label="Pagination">
                <template v-for="(link, key) in links" :key="key">
                    <div
                        v-if="link.url === null"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-200 dark:bg-gray-900 dark:border-gray-850"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium border"
                        :class="[
                            link.active
                                ? 'z-10 bg-indigo-600 text-white border-indigo-600 dark:bg-indigo-500 dark:border-indigo-500'
                                : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800'
                        ]"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </div>
</template>
