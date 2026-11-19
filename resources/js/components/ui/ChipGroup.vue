<script setup lang="ts" generic="T extends string | number">
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

/**
 * Row of chips of which any number can be switched on.
 */
const model = defineModel<T[]>({ required: true });

const props = defineProps<{ options: { value: T; label: string }[] }>();

const selected = computed({
    get: () => model.value.map(String),
    set: (values: string[]) => {
        model.value = props.options
            .filter((option) => values.includes(String(option.value)))
            .map((option) => option.value);
    },
});
</script>

<template>
    <ToggleGroupRoot v-model="selected" type="multiple" class="flex flex-wrap gap-2">
        <ToggleGroupItem
            v-for="option in options"
            :key="option.value"
            :value="String(option.value)"
            class="cursor-pointer rounded-full border border-line-strong bg-surface px-3 py-1 text-sm text-ink transition-colors hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent data-[state=on]:border-accent data-[state=on]:bg-accent data-[state=on]:text-on-accent"
        >
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
