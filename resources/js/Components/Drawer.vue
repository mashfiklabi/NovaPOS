<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title?: string;
        closeable?: boolean;
    }>(),
    {
        show: false,
        closeable: true,
    }
);

const emit = defineEmits(['close']);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

watch(
    () => props.show,
    (show) => {
        if (show) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
);

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-show="show" class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <Transition
                enter-active-class="ease-in-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in-out duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-show="show" class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 transition-opacity" @click="close" />
            </Transition>

            <!-- Slide-over panel -->
            <Transition
                enter-active-class="transform transition ease-in-out duration-300 sm:duration-300"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transform transition ease-in-out duration-200"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div v-show="show" class="relative w-screen max-w-md bg-white shadow-2xl dark:bg-gray-900 flex flex-col h-full border-l border-gray-200 dark:border-gray-800">
                    <!-- Close button in header -->
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 dark:border-gray-800">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ title || 'Details' }}
                        </h2>
                        <button
                            type="button"
                            class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none dark:hover:text-gray-300"
                            @click="close"
                        >
                            <span class="sr-only">Close panel</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Main body -->
                    <div class="flex-1 overflow-y-auto p-6">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="border-t border-gray-100 px-6 py-4 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-950/50 flex items-center justify-end space-x-3">
                        <slot name="footer" />
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
