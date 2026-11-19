<script setup lang="ts">
import { useId } from 'vue';
import { NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput, NumberFieldRoot } from 'reka-ui';
import { Minus, Plus } from 'lucide-vue-next';
import { currentLocale } from '@/i18n';
import FieldLabel from './FieldLabel.vue';

/**
 * Numeric input that may be empty. Stepper buttons are optional.
 */
const model = defineModel<number | null>({ required: true });

withDefaults(
    defineProps<{
        label?: string;
        min?: number;
        max?: number;
        step?: number;
        steppers?: boolean;
        placeholder?: string;
    }>(),
    { label: undefined, min: 0, max: undefined, step: 1, steppers: false, placeholder: undefined },
);

const id = useId();
const FORMAT: Intl.NumberFormatOptions = { maximumFractionDigits: 3, useGrouping: false };
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <NumberFieldRoot
            :id="id"
            v-model="model"
            :min="min"
            :max="max"
            :step="step"
            :step-snapping="false"
            :locale="currentLocale()"
            :format-options="FORMAT"
            class="flex h-10 items-center rounded-field border border-line-strong bg-surface focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20"
        >
            <NumberFieldDecrement
                v-if="steppers"
                class="flex h-full w-9 cursor-pointer items-center justify-center text-ink-muted hover:text-ink disabled:opacity-30"
            >
                <Minus class="size-4" />
            </NumberFieldDecrement>
            <NumberFieldInput
                :placeholder="placeholder"
                class="h-full w-full min-w-0 bg-transparent px-3 text-sm text-ink placeholder:text-ink-muted focus:outline-none"
                :class="{ 'px-0 text-center': steppers }"
            />
            <NumberFieldIncrement
                v-if="steppers"
                class="flex h-full w-9 cursor-pointer items-center justify-center text-ink-muted hover:text-ink disabled:opacity-30"
            >
                <Plus class="size-4" />
            </NumberFieldIncrement>
        </NumberFieldRoot>
    </div>
</template>
