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
            class="cursor-pointer rounded-full border border-stone-300 bg-white px-3 py-1 text-sm text-stone-700 transition-colors hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 data-[state=on]:border-emerald-600 data-[state=on]:bg-emerald-600 data-[state=on]:text-white"
        >
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
