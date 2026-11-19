<script setup lang="ts">
import { Primitive } from 'reka-ui';
import { X } from 'lucide-vue-next';

/**
 * Chip naming something that is switched on, taken away again by
 * pressing it.
 */
defineProps<{ label: string; removeLabel: string }>();

defineEmits<{ remove: [] }>();
</script>

<template>
    <Primitive
        as="button"
        type="button"
        :aria-label="removeLabel"
        class="inline-flex h-8 cursor-pointer items-center gap-1 rounded-full bg-accent-soft pr-2 pl-3 text-sm font-medium text-accent-deep transition-colors hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
        @click="$emit('remove')"
    >
        {{ label }}
        <X class="size-3.5" aria-hidden="true" />
    </Primitive>
</template>
