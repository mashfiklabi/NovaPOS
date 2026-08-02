<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    breadcrumbs?: Array<{
        name: string;
        href?: string;
    }>;
}>();
</script>

<template>
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <!-- Breadcrumbs -->
            <nav v-if="breadcrumbs && breadcrumbs.length" class="mb-2 flex" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-1 text-xs text-gray-500 dark:text-gray-400">
                    <li>
                        <Link href="/dashboard" class="hover:text-gray-700 dark:hover:text-gray-200">
                            Home
                        </Link>
                    </li>
                    <li v-for="(crumb, index) in breadcrumbs" :key="index" class="flex items-center">
                        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        <Link
                            v-if="crumb.href"
                            :href="crumb.href"
                            class="ml-1 hover:text-gray-700 dark:hover:text-gray-200"
                        >
                            {{ crumb.name }}
                        </Link>
                        <span v-else class="ml-1 text-gray-700 dark:text-gray-300 font-medium">
                            {{ crumb.name }}
                        </span>
                    </li>
                </ol>
            </nav>

            <!-- Page Title -->
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                {{ title }}
            </h1>
        </div>

        <!-- Action slots -->
        <div class="flex items-center space-x-3 shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
