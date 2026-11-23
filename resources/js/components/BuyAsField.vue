<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { X } from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import type { Product } from '@/api/types';
import AppButton from '@/components/ui/AppButton.vue';
import ComboboxField from '@/components/ui/ComboboxField.vue';

/**
 * Chooses the product another one is bought as, such as onions for red
 * onions. Only products that are bought as themselves are offered, since
 * this works one level deep.
 */
type BuyAs = Pick<Product, 'id' | 'name'>;

const model = defineModel<BuyAs | null>({ required: true });

const props = defineProps<{ product: Product }>();

const { t } = useI18n();

async function search(term: string): Promise<BuyAs[]> {
    return (await productsApi.list({ search: term })).data.filter(
        (candidate) => candidate.id !== props.product.id && candidate.buy_as_id === null,
    );
}
</script>

<template>
    <div>
        <div class="flex items-end gap-2">
            <ComboboxField
                v-model="model"
                class="flex-1"
                :search="search"
                :display="(candidate) => candidate.name"
                :label="t('products.buyAs')"
                :placeholder="t('products.buyAsNone')"
                :empty-text="t('common.noResults')"
            />
            <AppButton
                v-if="model"
                size="icon"
                variant="ghost"
                :aria-label="t('products.buyAsClear')"
                @click="model = null"
            >
                <X class="size-4" />
            </AppButton>
        </div>
        <p class="mt-1 text-xs text-ink-muted">{{ t('products.buyAsHint') }}</p>
    </div>
</template>
