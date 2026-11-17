<script setup lang="ts">
import { computed, useId } from 'vue';
import { parseDate, type DateValue } from '@internationalized/date';
import { DateFieldInput, DateFieldRoot } from 'reka-ui';
import { currentLocale } from '@/i18n';
import FieldLabel from './FieldLabel.vue';

/**
 * Date input bound to a `YYYY-MM-DD` string.
 */
const model = defineModel<string | null>({ required: true });

withDefaults(defineProps<{ label?: string }>(), { label: undefined });

const id = useId();

const date = computed({
    get: () => (model.value ? parseDate(model.value) : undefined),
    set: (value: DateValue | undefined) => {
        model.value = value ? value.toString() : null;
    },
});
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <DateFieldRoot
            :id="id"
            v-slot="{ segments }"
            v-model="date"
            :locale="currentLocale()"
            granularity="day"
            class="flex h-10 items-center rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-900 focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-600/20"
        >
            <DateFieldInput
                v-for="segment in segments"
                :key="segment.part"
                :part="segment.part"
                class="rounded px-0.5 focus:bg-emerald-100 focus:outline-none data-[placeholder]:text-stone-400"
            >
                {{ segment.value }}
            </DateFieldInput>
        </DateFieldRoot>
    </div>
</template>
