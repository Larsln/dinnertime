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
            class="rounded-full px-5 py-2.5 text-sm font-medium shadow-lg"
            :class="toast.kind === 'error' ? 'bg-danger text-on-accent' : 'bg-ink text-paper'"
        >
            {{ toast.text }}
        </p>
    </div>
</template>
