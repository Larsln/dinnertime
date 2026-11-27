<script setup lang="ts" generic="T extends string">
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

/**
 * Row of small chips of which at most one is chosen. Choosing the chosen
 * one again takes the choice back.
 */
const model = defineModel<T | null>({ required: true });

defineProps<{ label: string; options: { value: T; label: string }[]; disabled?: boolean }>();

const selected = computed({
    get: () => model.value ?? undefined,
    set: (value: T | undefined) => {
        model.value = value || null;
    },
});
</script>

<template>
    <ToggleGroupRoot
        v-model="selected"
        type="single"
        :aria-label="label"
        :disabled="disabled"
        class="flex flex-wrap gap-1"
    >
        <ToggleGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="cursor-pointer rounded-full border border-line-strong bg-surface px-2 py-0.5 text-xs text-ink-muted transition-colors hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent data-[state=on]:border-accent data-[state=on]:bg-accent data-[state=on]:text-on-accent"
        >
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
