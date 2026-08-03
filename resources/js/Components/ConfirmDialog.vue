<script setup lang="ts">
import AppModal from './AppModal.vue';
import AppButton from './AppButton.vue';

withDefaults(
    defineProps<{
        show: boolean;
        title?: string;
        message: string;
        confirmText?: string;
        cancelText?: string;
        processing?: boolean;
    }>(),
    {
        title: 'Are you sure?',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        processing: false,
    }
);

defineEmits(['close', 'confirm']);
</script>

<template>
    <AppModal :show="show" :title="title" @close="$emit('close')" maxWidth="sm">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ message }}
            </p>
        </div>

        <template #footer>
            <AppButton variant="secondary" :disabled="processing" @click="$emit('close')">
                {{ cancelText }}
            </AppButton>
            <AppButton variant="danger" :loading="processing" @click="$emit('confirm')" class="ml-3">
                {{ confirmText }}
            </AppButton>
        </template>
    </AppModal>
</template>
