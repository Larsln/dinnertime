<script setup lang="ts">
import { useId } from 'vue';
import FieldLabel from './FieldLabel.vue';

/**
 * Multi-line free-text input.
 */
const model = defineModel<string>({ required: true });

withDefaults(defineProps<{ label?: string; rows?: number; placeholder?: string }>(), {
    label: undefined,
    rows: 3,
    placeholder: undefined,
});

const id = useId();
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 placeholder:text-stone-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20"
        />
    </div>
</template>
