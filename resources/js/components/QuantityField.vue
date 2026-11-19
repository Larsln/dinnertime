<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Unit } from '@/api/types';
import { quantityStyle } from '@/lib/format';
import { formatEditableQuantity, parseKitchenQuantity } from '@/lib/kitchenQuantity';
import TextField from '@/components/ui/TextField.vue';

/**
 * Quantity of an ingredient, typed as decimal or fraction such as "1 1/2".
 * While the field is not being edited it shows the value the way the
 * recipe does: with fractions, or as decimal for weights and volumes.
 * Input that cannot be read leaves the value untouched and raises the
 * `invalid` flag.
 */
const model = defineModel<number | null>({ required: true });
const invalid = defineModel<boolean>('invalid', { default: false });

const props = defineProps<{ unit: Unit | null; placeholder?: string }>();

const { locale } = useI18n();

const text = ref(displayText());
const isEditing = ref(false);

function displayText(): string {
    return model.value === null
        ? ''
        : formatEditableQuantity(model.value, locale.value, quantityStyle(props.unit));
}

function read(typed: string): void {
    if (!isEditing.value) {
        return;
    }

    const parsed = parseKitchenQuantity(typed);

    if (typed.trim() === '') {
        model.value = null;
        invalid.value = false;
    } else if (parsed === null) {
        invalid.value = true;
    } else {
        model.value = parsed;
        invalid.value = false;
    }
}

function finishEditing(): void {
    isEditing.value = false;

    if (!invalid.value) {
        text.value = displayText();
    }
}

watch(text, read);

watch([model, () => props.unit, locale], () => {
    if (!isEditing.value && !invalid.value) {
        text.value = displayText();
    }
});
</script>

<template>
    <TextField
        v-model="text"
        :placeholder="placeholder"
        :invalid="invalid && !isEditing"
        @focusin="isEditing = true"
        @focusout="finishEditing"
    />
</template>
