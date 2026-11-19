<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { Plus } from 'lucide-vue-next';
import { TabsContent, TabsRoot } from 'reka-ui';
import { productsApi } from '@/api/products';
import { storesApi } from '@/api/stores';
import { toolsApi } from '@/api/tools';
import { STORES_KEY, useStores } from '@/composables/useStores';
import { useToasts } from '@/composables/useToasts';
import { useDebounced } from '@/composables/useDebounced';
import ProductCard from '@/components/ProductCard.vue';
import ProductRow from '@/components/ProductRow.vue';
import StoreRow from '@/components/StoreRow.vue';
import ToolRow from '@/components/ToolRow.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import TabBar from '@/components/ui/TabBar.vue';
import TabItem from '@/components/ui/TabItem.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Review list of suggested values, the catalog of all products and the
 * catalog of tools.
 */
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

const { stores } = useStores();
const storeName = ref('');

const addStore = useMutation({
    mutationFn: () => storesApi.create(storeName.value.trim()),
    onSuccess: () => {
        storeName.value = '';
        queryClient.invalidateQueries({ queryKey: STORES_KEY });
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
        <TabBar class="mb-5 inline-flex max-w-full overflow-x-auto">
            <TabItem value="review">
                {{ t('products.reviewTab', { count: review.data.value?.meta.total ?? 0 }) }}
            </TabItem>
            <TabItem value="all">{{ t('products.allTab') }}</TabItem>
            <TabItem value="tools">{{ t('products.toolsTab') }}</TabItem>
            <TabItem value="stores">{{ t('stores.tab') }}</TabItem>
        </TabBar>

        <TabsContent value="review" class="space-y-4">
            <p v-if="review.data.value?.data.length" class="text-sm text-ink-muted">
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
            <ul class="divide-y divide-line rounded-card border border-line bg-surface">
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
                class="divide-y divide-line rounded-card border border-line bg-surface"
            >
                <ToolRow v-for="tool in tools.data.value" :key="tool.id" :tool="tool" />
            </ul>
            <EmptyState v-else-if="!tools.isPending.value" :title="t('tools.empty')" />
        </TabsContent>
        <TabsContent value="stores">
            <form class="mb-4 flex gap-2" @submit.prevent="addStore.mutate()">
                <TextField v-model="storeName" class="flex-1" :placeholder="t('stores.name')" />
                <AppButton type="submit" :disabled="storeName.trim() === '' || addStore.isPending.value">
                    <Plus class="size-4" />{{ t('stores.add') }}
                </AppButton>
            </form>
            <p v-if="stores.length" class="mb-4 text-sm text-ink-muted">{{ t('stores.hint') }}</p>
            <ul v-if="stores.length" class="divide-y divide-line rounded-card border border-line bg-surface">
                <StoreRow v-for="store in stores" :key="store.id" :store="store" />
            </ul>
            <EmptyState v-else :title="t('stores.empty')" :hint="t('stores.emptyHint')" />
        </TabsContent>
    </TabsRoot>
</template>
