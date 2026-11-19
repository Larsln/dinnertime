<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { productsApi } from '@/api/products';
import type { Product } from '@/api/types';
import ComboboxField from '@/components/ui/ComboboxField.vue';
import ProductIcon from '@/components/ProductIcon.vue';

/**
 * Adds a position to the shopping list: a product found while typing, or
 * the typed text itself for anything that is not a product.
 */
const emit = defineEmits<{ product: [product: Product]; text: [label: string] }>();

const { t } = useI18n();

const SUGGESTIONS = 6;
const additions = ref(0);

const picked = computed<Product | null>({
    get: () => null,
    set: (product) => {
        if (product) {
            emit('product', product);
            additions.value++;
        }
    },
});

async function search(term: string): Promise<Product[]> {
    return (await productsApi.list({ search: term })).data.slice(0, SUGGESTIONS);
}

function addText(label: string): void {
    emit('text', label);
    additions.value++;
}
</script>

<template>
    <ComboboxField
        :key="additions"
        v-model="picked"
        :search="search"
        :display="(product) => product.name"
        :label="t('shopping.add')"
        :placeholder="t('shopping.addPlaceholder')"
        pill
        :create-label="(name) => t('shopping.addText', { name })"
        @create="addText"
    >
        <template #item="{ item }">
            <span class="flex items-center gap-2">
                <ProductIcon
                    :icon="item.icon"
                    :section-key="item.store_section?.key"
                    class="size-5 text-ink-muted"
                />
                {{ item.name }}
            </span>
        </template>
    </ComboboxField>
</template>
