<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { Plus } from 'lucide-vue-next';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { productsApi } from '@/api/products';
import { toolsApi } from '@/api/tools';
import { useToasts } from '@/composables/useToasts';
import { useDebounced } from '@/composables/useDebounced';
import ProductCard from '@/components/ProductCard.vue';
import ProductRow from '@/components/ProductRow.vue';
import ToolRow from '@/components/ToolRow.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Review list of suggested values, the catalog of all products and the
 * catalog of tools.
 */
const TAB_CLASS =
    'cursor-pointer rounded-lg px-4 py-2 text-sm font-medium text-stone-600 hover:text-stone-900 data-[state=active]:bg-white data-[state=active]:text-stone-900 data-[state=active]:shadow-sm';

const { t } = useI18n();
const queryClient = useQueryClient();
const { showError } = useToasts();

const tab = ref('review');
const search = ref('');
const debouncedSearch = useDebounced(search);

const review = useQuery({ queryKey: ['review'], queryFn: () => productsApi.review() });

const toolSearch = ref('');
const debouncedToolSearch = useDebounced(toolSearch);

const tools = useQuery({
    queryKey: ['tools', debouncedToolSearch],
    queryFn: () => toolsApi.list(debouncedToolSearch.value),
    placeholderData: keepPreviousData,
});

const addTool = useMutation({
    mutationFn: () => toolsApi.create(toolSearch.value.trim()),
    onSuccess: () => {
        toolSearch.value = '';
        queryClient.invalidateQueries({ queryKey: ['tools'] });
    },
    onError: showError,
});

const addProduct = useMutation({
    mutationFn: () => productsApi.create({ name: search.value.trim() }),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['products'] });
        queryClient.invalidateQueries({ queryKey: ['review'] });
    },
    onError: showError,
});

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
            <TabsTrigger value="tools" :class="TAB_CLASS">{{ t('products.toolsTab') }}</TabsTrigger>
        </TabsList>

        <TabsContent value="review" class="space-y-4">
            <p v-if="review.data.value?.data.length" class="text-sm text-stone-500">
                {{ t('products.reviewHint') }}
            </p>
            <ProductCard v-for="product in review.data.value?.data" :key="product.id" :product="product" />
            <EmptyState
                v-if="review.data.value?.data.length === 0"
                :title="t('products.reviewEmpty')"
                :hint="t('products.reviewEmptyHint')"
            />
        </TabsContent>

        <TabsContent value="all">
            <form class="mb-4 flex gap-2" @submit.prevent="addProduct.mutate()">
                <TextField
                    v-model="search"
                    class="flex-1"
                    type="search"
                    :placeholder="t('products.searchPlaceholder')"
                />
                <AppButton type="submit" :disabled="search.trim() === '' || addProduct.isPending.value">
                    <Plus class="size-4" />{{ t('products.add') }}
                </AppButton>
            </form>
            <ul class="divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white">
                <ProductRow
                    v-for="product in products.data.value?.data"
                    :key="product.id"
                    :product="product"
                />
            </ul>
        </TabsContent>
        <TabsContent value="tools">
            <form class="mb-4 flex gap-2" @submit.prevent="addTool.mutate()">
                <TextField
                    v-model="toolSearch"
                    class="flex-1"
                    type="search"
                    :placeholder="t('tools.searchPlaceholder')"
                />
                <AppButton type="submit" :disabled="toolSearch.trim() === '' || addTool.isPending.value">
                    <Plus class="size-4" />{{ t('tools.add') }}
                </AppButton>
            </form>
            <ul
                v-if="tools.data.value?.length"
                class="divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white"
            >
                <ToolRow v-for="tool in tools.data.value" :key="tool.id" :tool="tool" />
            </ul>
            <EmptyState v-else-if="!tools.isPending.value" :title="t('tools.empty')" />
        </TabsContent>
    </TabsRoot>
</template>
