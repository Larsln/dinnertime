<script setup lang="ts">
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

/**
 * Scale from 0 (bad) to 10 (good). Tapping the chosen value again
 * withdraws the rating.
 */
const model = defineModel<number | null>({ required: true });

defineProps<{ label: string }>();

const VALUES = Array.from({ length: 11 }, (_, value) => value);

const selected = computed({
    get: () => (model.value === null ? undefined : String(model.value)),
    set: (value: string | undefined) => {
        model.value = value === undefined || value === '' ? null : Number(value);
    },
});
</script>

<template>
    <ToggleGroupRoot v-model="selected" type="single" :aria-label="label" class="flex flex-wrap gap-1">
        <ToggleGroupItem
            v-for="value in VALUES"
            :key="value"
            :value="String(value)"
            class="size-8 cursor-pointer rounded-lg border border-stone-300 bg-white text-sm font-medium text-stone-700 tabular-nums transition-colors hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 data-[state=on]:border-emerald-600 data-[state=on]:bg-emerald-600 data-[state=on]:text-white"
        >
            {{ value }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
