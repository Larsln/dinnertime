<script setup lang="ts">
import { useId } from 'vue';
import { CheckboxIndicator, CheckboxRoot, Label } from 'reka-ui';
import { Check } from 'lucide-vue-next';

/**
 * Checkbox with an optional caption next to it.
 */
const model = defineModel<boolean>({ required: true });

withDefaults(defineProps<{ label?: string; ariaLabel?: string }>(), {
    label: undefined,
    ariaLabel: undefined,
});

const id = useId();
</script>

<template>
    <div class="flex items-center gap-2">
        <CheckboxRoot
            :id="id"
            v-model="model"
            :aria-label="ariaLabel"
            class="flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-ink-muted bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent data-[state=checked]:border-accent data-[state=checked]:bg-accent"
        >
            <CheckboxIndicator><Check class="size-3.5 text-on-accent" /></CheckboxIndicator>
        </CheckboxRoot>
        <Label v-if="label" :for="id" class="cursor-pointer text-sm text-ink">{{ label }}</Label>
    </div>
</template>
