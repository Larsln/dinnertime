<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useQueryClient } from '@tanstack/vue-query';
import { productsApi } from '@/api/products';
import type { Product } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import ComboboxField from '@/components/ui/ComboboxField.vue';

/**
 * Finds a product by name or alias. With `creatable` set, an unknown name
 * can be added as a new product.
 */
const model = defineModel<Product | null>({ required: true });

withDefaults(defineProps<{ label?: string; creatable?: boolean }>(), { label: undefined, creatable: false });

const { t } = useI18n();
const { showError } = useToasts();
const queryClient = useQueryClient();

async function search(term: string): Promise<Product[]> {
    return (await productsApi.list({ search: term })).data;
}

async function create(name: string): Promise<void> {
    try {
        model.value = await productsApi.create({ name });
        queryClient.invalidateQueries({ queryKey: ['review'] });
    } catch (error) {
        showError(error);
    }
}
</script>

<template>
    <ComboboxField
        v-model="model"
        :search="search"
        :display="(product) => product.name"
        :label="label"
        :placeholder="t('products.searchPlaceholder')"
        :empty-text="creatable ? undefined : t('common.noResults')"
        :create-label="creatable ? (name) => t('products.create', { name }) : undefined"
        @create="create"
    >
        <template #item="{ item }">
            <span>{{ item.name }}</span>
            <span v-if="item.aliases?.length" class="ml-2 text-xs text-stone-500">{{
                item.aliases.join(', ')
            }}</span>
        </template>
    </ComboboxField>
</template>
