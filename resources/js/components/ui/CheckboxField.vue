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
            class="flex size-5 shrink-0 cursor-pointer items-center justify-center rounded border border-stone-400 bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600"
        >
            <CheckboxIndicator><Check class="size-3.5 text-white" /></CheckboxIndicator>
        </CheckboxRoot>
        <Label v-if="label" :for="id" class="cursor-pointer text-sm text-stone-800">{{ label }}</Label>
    </div>
</template>
