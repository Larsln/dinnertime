<script setup lang="ts">
import { useId } from 'vue';
import FieldLabel from './FieldLabel.vue';

/**
 * Single-line free-text input. The shared wrapper for all text entry.
 */
const model = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        label?: string;
        type?: 'text' | 'password' | 'url' | 'search';
        placeholder?: string;
        autocomplete?: string;
        required?: boolean;
    }>(),
    { label: undefined, type: 'text', placeholder: undefined, autocomplete: 'off', required: false },
);

const id = useId();
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-900 placeholder:text-stone-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20"
        />
    </div>
</template>
