<script setup lang="ts">
import { watch } from 'vue';
import { useToasts } from '@/composables/useToasts';

/**
 * Renders the pending notifications and removes each after a few seconds.
 */
const DISPLAY_MILLISECONDS = 5000;

const { toasts, dismiss } = useToasts();

watch(
    () => toasts.value.at(-1),
    (toast) => {
        if (toast) {
            setTimeout(() => dismiss(toast.id), DISPLAY_MILLISECONDS);
        }
    },
);
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 lg:bottom-6"
        aria-live="polite"
    >
        <p
            v-for="toast in toasts"
            :key="toast.id"
            class="rounded-xl px-4 py-2 text-sm font-medium text-white shadow-lg"
            :class="toast.kind === 'error' ? 'bg-red-600' : 'bg-stone-900'"
        >
            {{ toast.text }}
        </p>
    </div>
</template>
