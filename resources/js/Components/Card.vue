<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        noPadding?: boolean;
    }>(),
    {
        noPadding: false,
    }
);

const paddingClass = computed(() => (props.noPadding ? '' : 'p-6'));
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 transition-colors duration-200">
        <div v-if="title || subtitle || $slots.header" class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <slot name="header">
                <div>
                    <h3 v-if="title" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ title }}
                    </h3>
                    <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ subtitle }}
                    </p>
                </div>
            </slot>
        </div>
        <div :class="paddingClass">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-gray-100 bg-gray-50/50 px-6 py-4 dark:border-gray-800 dark:bg-gray-900/50">
            <slot name="footer" />
        </div>
    </div>
</template>
