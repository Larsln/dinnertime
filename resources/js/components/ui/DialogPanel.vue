<script setup lang="ts">
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { X } from 'lucide-vue-next';

/**
 * Modal dialog with a title, content and an optional row of actions.
 */
const open = defineModel<boolean>('open', { required: true });

withDefaults(defineProps<{ title: string; description?: string; closeLabel: string; size?: 'md' | 'lg' }>(), {
    description: undefined,
    size: 'md',
});
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-scrim" />
            <DialogContent
                :class="size === 'lg' ? 'max-w-xl' : 'max-w-md'"
                class="fixed top-1/2 left-1/2 z-50 max-h-[90dvh] w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-card border border-line bg-surface p-5 shadow-xl focus:outline-none"
            >
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <DialogTitle class="font-display text-xl font-bold text-ink">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-sm text-ink-muted">
                            {{ description }}
                        </DialogDescription>
                    </div>
                    <DialogClose
                        :aria-label="closeLabel"
                        class="cursor-pointer rounded-full p-1.5 text-ink-muted hover:bg-sunken hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >
                        <X class="size-5" />
                    </DialogClose>
                </div>
                <div class="space-y-4">
                    <slot />
                </div>
                <div v-if="$slots.actions" class="mt-6 flex justify-end gap-2">
                    <slot name="actions" />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
