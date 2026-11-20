<script setup lang="ts">
import { Toggle } from 'reka-ui';
import { Star } from 'lucide-vue-next';

/**
 * Star that marks something as a favourite of the household. Filled when
 * on; the label names what the star stands for.
 */
const model = defineModel<boolean>({ required: true });

defineProps<{ label: string }>();
</script>

<template>
    <Toggle
        v-model="model"
        :aria-label="label"
        :title="label"
        class="group grid size-9 shrink-0 cursor-pointer place-items-center rounded-full text-ink-muted transition-colors hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent data-[state=on]:text-accent"
    >
        <Star class="size-5 group-data-[state=on]:fill-current" />
    </Toggle>
</template>
