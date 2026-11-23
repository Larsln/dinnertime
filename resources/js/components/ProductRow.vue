<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { ChevronDown } from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import type { Product } from '@/api/types';
import ProductCard from '@/components/ProductCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * Line of the product list that unfolds into the editor on demand.
 */
const props = defineProps<{ product: Product }>();

const { t } = useI18n();
const isExpanded = ref(false);

const detail = useQuery({
    queryKey: ['product', () => props.product.id],
    queryFn: () => productsApi.get(props.product.id),
    enabled: isExpanded,
});
</script>

<template>
    <li>
        <div class="flex items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium">{{ product.name }}</p>
                <p class="truncate text-sm text-ink-muted">
                    {{ product.store_section?.name ?? t('products.noSection') }}
                    <template v-if="product.aliases?.length"> · {{ product.aliases.join(', ') }}</template>
                    <template v-if="product.buy_as">
                        · {{ t('products.boughtAs', { name: product.buy_as.name }) }}</template
                    >
                </p>
            </div>
            <StatusBadge v-if="product.is_pantry_staple">{{ t('products.staple') }}</StatusBadge>
            <StatusBadge v-if="product.status === 'suggested'" tone="warning">{{
                t('products.suggested')
            }}</StatusBadge>
            <AppButton
                size="icon"
                variant="ghost"
                :aria-label="t('common.edit')"
                :aria-expanded="isExpanded"
                @click="isExpanded = !isExpanded"
            >
                <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': isExpanded }" />
            </AppButton>
        </div>
        <div v-if="isExpanded && detail.data.value" class="px-2 pb-3">
            <ProductCard :product="detail.data.value" />
        </div>
    </li>
</template>
