<script setup lang="ts">
defineProps<{
    modelValue: string;
    label?: string;
    rows?: number;
    placeholder?: string;
    error?: string;
    required?: boolean;
}>();

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ label }} <span v-if="required" class="text-red-500">*</span>
        </label>
        <textarea
            :value="modelValue"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
            :rows="rows || 3"
            :placeholder="placeholder"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-150"
            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': error }"
        />
        <p v-if="error" class="mt-1 text-xs text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </div>
</template>
