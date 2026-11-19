<script setup lang="ts" generic="T extends { id: number }">
import { ref, shallowRef, useId, watch } from 'vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxViewport,
} from 'reka-ui';
import { Plus, Search } from 'lucide-vue-next';
import FieldLabel from './FieldLabel.vue';

/**
 * Search field that suggests records from the server while typing.
 *
 * With `createLabel` set, the typed text can be turned into a new record.
 * With `pill` set, it is drawn as a capsule-shaped search field.
 */
const model = defineModel<T | null>({ required: true });

const props = withDefaults(
    defineProps<{
        search: (term: string) => Promise<T[]>;
        display: (item: T) => string;
        label?: string;
        placeholder?: string;
        emptyText?: string;
        createLabel?: (term: string) => string;
        pill?: boolean;
    }>(),
    {
        label: undefined,
        placeholder: undefined,
        emptyText: undefined,
        createLabel: undefined,
        pill: false,
    },
);

const emit = defineEmits<{ create: [term: string] }>();

const CREATE = { id: -1 };
const DEBOUNCE_MILLISECONDS = 200;

const id = useId();
const term = ref('');
const results = shallowRef<T[]>([]);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(term, (value) => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        results.value = value.trim() === '' ? [] : await props.search(value.trim());
    }, DEBOUNCE_MILLISECONDS);
});

function select(value: T | typeof CREATE | null): void {
    if (value?.id === CREATE.id) {
        emit('create', term.value.trim());
    } else {
        model.value = value as T | null;
    }
}

function displayValue(value: T | null): string {
    return value ? props.display(value) : '';
}
</script>

<template>
    <div>
        <FieldLabel v-if="label" :for="id">{{ label }}</FieldLabel>
        <ComboboxRoot :model-value="model" ignore-filter open-on-focus by="id" @update:model-value="select">
            <ComboboxAnchor
                class="flex items-center border border-line-strong bg-surface focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20"
                :class="pill ? 'h-11 rounded-full pl-4' : 'h-10 rounded-field'"
            >
                <Search v-if="pill" class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
                <ComboboxInput
                    :id="id"
                    v-model="term"
                    :display-value="displayValue"
                    :placeholder="placeholder"
                    autocomplete="off"
                    class="h-full w-full min-w-0 bg-transparent px-3 text-sm text-ink placeholder:text-ink-muted focus:outline-none"
                />
            </ComboboxAnchor>
            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="4"
                    class="z-50 max-h-72 w-(--reka-combobox-trigger-width) overflow-y-auto rounded-tile border border-line bg-surface shadow-lg"
                >
                    <ComboboxViewport class="p-1">
                        <ComboboxEmpty v-if="emptyText" class="px-3 py-2 text-sm text-ink-muted">
                            {{ emptyText }}
                        </ComboboxEmpty>
                        <ComboboxItem
                            v-for="item in results"
                            :key="item.id"
                            :value="item"
                            class="cursor-pointer rounded-field px-3 py-2 text-sm text-ink outline-none data-[highlighted]:bg-accent-soft"
                        >
                            <slot name="item" :item="item">{{ display(item) }}</slot>
                        </ComboboxItem>
                        <ComboboxItem
                            v-if="createLabel && term.trim() !== ''"
                            :value="CREATE"
                            class="flex cursor-pointer items-center gap-2 rounded-field px-3 py-2 text-sm text-accent-deep outline-none data-[highlighted]:bg-accent-soft"
                        >
                            <Plus class="size-4" />
                            {{ createLabel(term.trim()) }}
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </div>
</template>
