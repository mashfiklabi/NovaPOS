<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        type?: 'submit' | 'button' | 'reset';
        variant?: 'primary' | 'secondary' | 'danger' | 'success';
        size?: 'sm' | 'md' | 'lg';
        disabled?: boolean;
        loading?: boolean;
    }>(),
    {
        type: 'button',
        variant: 'primary',
        size: 'md',
        disabled: false,
        loading: false,
    }
);

const baseClasses = 'inline-flex items-center justify-center rounded-lg font-semibold transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

const variantClasses = computed(() => {
    return {
        primary: 'bg-indigo-600 hover:bg-indigo-500 text-white focus:ring-indigo-500 border border-transparent',
        secondary: 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-gray-200 dark:border-gray-800 focus:ring-indigo-500',
        danger: 'bg-red-600 hover:bg-red-500 text-white focus:ring-red-500 border border-transparent',
        success: 'bg-emerald-600 hover:bg-emerald-500 text-white focus:ring-emerald-500 border border-transparent',
    }[props.variant];
});

const sizeClasses = computed(() => {
    return {
        sm: 'px-3 py-1.5 text-xs',
        md: 'px-4 py-2 text-sm',
        lg: 'px-5 py-2.5 text-base',
    }[props.size];
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :class="[baseClasses, variantClasses, sizeClasses]"
    >
        <!-- Loading spinner -->
        <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.14 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <slot />
    </button>
</template>
