<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { productsApi } from '@/api/products';
import { useDebounced } from '@/composables/useDebounced';
import ProductCard from '@/components/ProductCard.vue';
import ProductRow from '@/components/ProductRow.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Review list of suggested values and the catalog of all products.
 */
const TAB_CLASS =
    'cursor-pointer rounded-lg px-4 py-2 text-sm font-medium text-stone-600 hover:text-stone-900 data-[state=active]:bg-white data-[state=active]:text-stone-900 data-[state=active]:shadow-sm';

const { t } = useI18n();

const tab = ref('review');
const search = ref('');
const debouncedSearch = useDebounced(search);

const review = useQuery({ queryKey: ['review'], queryFn: () => productsApi.review() });

const products = useQuery({
    queryKey: ['products', debouncedSearch],
    queryFn: () => productsApi.list({ search: debouncedSearch.value }),
    placeholderData: keepPreviousData,
});
</script>

<template>
    <PageHeader :title="t('nav.products')" :subtitle="t('products.subtitle')" />

    <TabsRoot v-model="tab">
        <TabsList class="mb-5 inline-flex gap-1 rounded-xl bg-stone-200/70 p-1">
            <TabsTrigger value="review" :class="TAB_CLASS">
                {{ t('products.reviewTab', { count: review.data.value?.meta.total ?? 0 }) }}
            </TabsTrigger>
            <TabsTrigger value="all" :class="TAB_CLASS">{{ t('products.allTab') }}</TabsTrigger>
        </TabsList>

        <TabsContent value="review" class="space-y-4">
            <ProductCard v-for="product in review.data.value?.data" :key="product.id" :product="product" />
            <EmptyState
                v-if="review.data.value?.data.length === 0"
                :title="t('products.reviewEmpty')"
                :hint="t('products.reviewEmptyHint')"
            />
        </TabsContent>

        <TabsContent value="all">
            <TextField
                v-model="search"
                class="mb-4"
                type="search"
                :placeholder="t('products.searchPlaceholder')"
            />
            <ul class="divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white">
                <ProductRow
                    v-for="product in products.data.value?.data"
                    :key="product.id"
                    :product="product"
                />
            </ul>
        </TabsContent>
    </TabsRoot>
</template>
