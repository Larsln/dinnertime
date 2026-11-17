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

withDefaults(defineProps<{ title: string; description?: string; closeLabel: string }>(), {
    description: undefined,
});
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-stone-900/40" />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-50 max-h-[90dvh] w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl bg-white p-5 shadow-xl focus:outline-none"
            >
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <DialogTitle class="text-lg font-semibold text-stone-900">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-sm text-stone-600">
                            {{ description }}
                        </DialogDescription>
                    </div>
                    <DialogClose
                        :aria-label="closeLabel"
                        class="cursor-pointer rounded-lg p-1 text-stone-500 hover:bg-stone-100 hover:text-stone-900"
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
