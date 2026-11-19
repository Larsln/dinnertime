<script setup lang="ts">
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import AppButton from './AppButton.vue';

/**
 * Asks for confirmation before an action that cannot be undone.
 */
const open = defineModel<boolean>('open', { required: true });

defineProps<{ title: string; description: string; confirmLabel: string; cancelLabel: string }>();

defineEmits<{ confirm: [] }>();
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="fixed inset-0 z-40 bg-scrim" />
            <AlertDialogContent
                class="fixed top-1/2 left-1/2 z-50 w-[calc(100vw-2rem)] max-w-sm -translate-x-1/2 -translate-y-1/2 rounded-card border border-line bg-surface p-5 shadow-xl focus:outline-none"
            >
                <AlertDialogTitle class="font-display text-xl font-bold text-ink">{{
                    title
                }}</AlertDialogTitle>
                <AlertDialogDescription class="mt-2 text-sm text-ink-muted">{{
                    description
                }}</AlertDialogDescription>
                <div class="mt-6 flex justify-end gap-2">
                    <AlertDialogCancel as-child>
                        <AppButton>{{ cancelLabel }}</AppButton>
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <AppButton variant="danger" @click="$emit('confirm')">{{ confirmLabel }}</AppButton>
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
