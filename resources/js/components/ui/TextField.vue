<script setup lang="ts">
import { useId } from 'vue';
import { Search } from 'lucide-vue-next';
import FieldLabel from './FieldLabel.vue';

/**
 * Single-line free-text input. The shared wrapper for all text entry.
 * The `search` type is drawn as a capsule with a magnifier. An invalid
 * field is outlined in the danger colour.
 */
const model = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        label?: string;
        type?: 'text' | 'password' | 'url' | 'search';
        placeholder?: string;
        autocomplete?: string;
        required?: boolean;
        invalid?: boolean;
    }>(),
    {
        label: undefined,
        type: 'text',
        placeholder: undefined,
        autocomplete: 'off',
        required: false,
        invalid: false,
    },
);

const id = useId();
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <div class="relative">
            <Search
                v-if="type === 'search'"
                class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-muted"
                aria-hidden="true"
            />
            <input
                :id="id"
                v-model="model"
                :type="type"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :required="required"
                :aria-invalid="invalid || undefined"
                class="h-10 w-full border text-sm text-ink placeholder:text-ink-muted focus:outline-none focus:ring-2"
                :class="[
                    type === 'search' ? 'rounded-full bg-sunken pr-4 pl-10' : 'rounded-field bg-surface px-3',
                    invalid
                        ? 'border-danger focus:border-danger focus:ring-danger/20'
                        : [
                              'focus:border-accent focus:ring-accent/20',
                              type === 'search' ? 'border-line' : 'border-line-strong',
                          ],
                ]"
            />
        </div>
    </div>
</template>
