<script setup lang="ts" generic="T extends string">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';

/**
 * Row of capsules of which exactly one is chosen.
 */
const model = defineModel<T>({ required: true });

defineProps<{ label: string; options: { value: T; label: string }[] }>();
</script>

<template>
    <RadioGroupRoot
        v-model="model"
        :aria-label="label"
        orientation="horizontal"
        class="grid auto-cols-fr grid-flow-col gap-1 rounded-full bg-sunken p-1"
    >
        <RadioGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="h-9 cursor-pointer rounded-full px-4 text-sm font-medium text-ink-muted transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent data-[state=checked]:bg-surface data-[state=checked]:text-ink data-[state=checked]:shadow-sm"
        >
            {{ option.label }}
        </RadioGroupItem>
    </RadioGroupRoot>
</template>
