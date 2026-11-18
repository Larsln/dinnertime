<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { Plus, Trash2 } from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import type { Product } from '@/api/types';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useToasts } from '@/composables/useToasts';
import { formatOffer } from '@/lib/format';
import AppButton from '@/components/ui/AppButton.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Where a product can be bought: the stores with package size and price,
 * and a row to add another one.
 */
const props = defineProps<{ product: Product }>();

const { t, locale } = useI18n();
const { showError } = useToasts();
const { currency } = useHouseholdSettings();
const queryClient = useQueryClient();

const store = ref('');
const amount = ref<number | null>(null);
const price = ref<number | null>(null);

const stores = useQuery({ queryKey: ['stores'], queryFn: productsApi.stores });

function refresh(): void {
    for (const key of ['products', 'review', 'stores', 'shopping-list']) {
        queryClient.invalidateQueries({ queryKey: [key] });
    }
}

const add = useMutation({
    mutationFn: () =>
        productsApi.addOffer(props.product.id, {
            store: store.value.trim(),
            amount_in_base: amount.value,
            price: price.value,
        }),
    onSuccess: () => {
        amount.value = null;
        price.value = null;
        refresh();
    },
    onError: showError,
});

const remove = useMutation({
    mutationFn: (id: number) => productsApi.removeOffer(id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <div>
        <FieldLabel>{{ t('products.offers') }}</FieldLabel>
        <ul v-if="product.offers?.length" class="mb-2 space-y-1 text-sm text-stone-700">
            <li v-for="offer in product.offers" :key="offer.id" class="flex items-center gap-2">
                <span class="flex-1">{{
                    formatOffer(offer, product.base_unit, locale, currency, t('units.piece'))
                }}</span>
                <AppButton
                    size="icon"
                    variant="ghost"
                    :aria-label="t('common.delete')"
                    @click="remove.mutate(offer.id)"
                >
                    <Trash2 class="size-4" />
                </AppButton>
            </li>
        </ul>
        <div v-if="stores.data.value?.length" class="mb-2 flex flex-wrap gap-1">
            <AppButton
                v-for="name in stores.data.value"
                :key="name"
                size="sm"
                :variant="store === name ? 'primary' : 'ghost'"
                @click="store = name"
            >
                {{ name }}
            </AppButton>
        </div>
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="add.mutate()">
            <TextField
                v-model="store"
                class="min-w-32 flex-1"
                :placeholder="t('products.offerStore')"
                required
            />
            <NumberField v-model="amount" class="w-28" :placeholder="t('products.offerSize')" />
            <NumberField v-model="price" class="w-28" :step="0.01" :placeholder="t('products.offerPrice')" />
            <AppButton type="submit" size="sm" :disabled="store.trim() === '' || add.isPending.value">
                <Plus class="size-4" />{{ t('common.add') }}
            </AppButton>
        </form>
    </div>
</template>
