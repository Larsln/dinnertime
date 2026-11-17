<script setup lang="ts" generic="T extends string | number">
import { computed, useId } from 'vue';
import {
    SelectContent,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import { Check, ChevronDown } from 'lucide-vue-next';
import FieldLabel from './FieldLabel.vue';

/**
 * Dropdown for choosing one of a few options. With `emptyLabel` set, an
 * additional entry clears the selection.
 */
const model = defineModel<T | null>({ required: true });

const props = withDefaults(
    defineProps<{
        options: { value: T; label: string }[];
        label?: string;
        placeholder?: string;
        emptyLabel?: string;
    }>(),
    { label: undefined, placeholder: undefined, emptyLabel: undefined },
);

const EMPTY = '__empty__';
const id = useId();

const selected = computed({
    get: () => (model.value === null ? (props.emptyLabel ? EMPTY : undefined) : String(model.value)),
    set: (value: string | undefined) => {
        model.value = props.options.find((option) => String(option.value) === value)?.value ?? null;
    },
});
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <SelectRoot v-model="selected">
            <SelectTrigger
                :id="id"
                class="flex h-10 w-full cursor-pointer items-center justify-between gap-2 rounded-lg border border-stone-300 bg-white px-3 text-left text-sm text-stone-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 data-[placeholder]:text-stone-400"
            >
                <SelectValue :placeholder="placeholder" class="truncate" />
                <SelectIcon><ChevronDown class="size-4 text-stone-500" /></SelectIcon>
            </SelectTrigger>
            <SelectPortal>
                <SelectContent
                    position="popper"
                    :side-offset="4"
                    class="z-50 max-h-72 min-w-(--reka-select-trigger-width) overflow-hidden rounded-lg border border-stone-200 bg-white shadow-lg"
                >
                    <SelectViewport class="p-1">
                        <SelectItem
                            v-if="emptyLabel"
                            :value="EMPTY"
                            class="relative flex cursor-pointer items-center rounded-md py-2 pr-8 pl-3 text-sm text-stone-500 outline-none data-[highlighted]:bg-emerald-50"
                        >
                            <SelectItemText>{{ emptyLabel }}</SelectItemText>
                        </SelectItem>
                        <SelectItem
                            v-for="option in options"
                            :key="option.value"
                            :value="String(option.value)"
                            class="relative flex cursor-pointer items-center rounded-md py-2 pr-8 pl-3 text-sm text-stone-900 outline-none data-[highlighted]:bg-emerald-50"
                        >
                            <SelectItemText>{{ option.label }}</SelectItemText>
                            <SelectItemIndicator class="absolute right-2">
                                <Check class="size-4 text-emerald-600" />
                            </SelectItemIndicator>
                        </SelectItem>
                    </SelectViewport>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>
    </div>
</template>
