<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Plus, Trash2 } from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import type { OfferUnit, Product } from '@/api/types';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useStores } from '@/composables/useStores';
import { useToasts } from '@/composables/useToasts';
import { formatOffer } from '@/lib/format';
import AppButton from '@/components/ui/AppButton.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import SelectField from '@/components/ui/SelectField.vue';
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
const unit = ref<OfferUnit | null>(props.product.base_unit);
const price = ref<number | null>(null);

const UNITS: OfferUnit[] = ['g', 'kg', 'ml', 'l', 'piece'];
const unitOptions = computed(() =>
    UNITS.map((value) => ({ value, label: value === 'piece' ? t('units.piece') : value })),
);

const { stores, storeName, isActive } = useStores();
const activeStores = computed(() => stores.value.filter((entry) => entry.is_active));

function refresh(): void {
    for (const key of ['products', 'review', 'stores', 'shopping-list']) {
        queryClient.invalidateQueries({ queryKey: [key] });
    }
}

const add = useMutation({
    mutationFn: () =>
        productsApi.addOffer(props.product.id, {
            store: store.value.trim(),
            amount: amount.value,
            unit: amount.value === null ? null : unit.value,
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
                <span class="flex-1" :class="{ 'text-stone-400': !isActive(offer) }">
                    {{ formatOffer(offer, storeName(offer), locale, currency, t('units.piece')) }}
                    <template v-if="!isActive(offer)"> · {{ t('stores.paused') }}</template>
                </span>
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
        <div v-if="activeStores.length" class="mb-2 flex flex-wrap gap-1">
            <AppButton
                v-for="entry in activeStores"
                :key="entry.id"
                size="sm"
                :variant="store === entry.name ? 'primary' : 'ghost'"
                @click="store = entry.name"
            >
                {{ entry.name }}
            </AppButton>
        </div>
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="add.mutate()">
            <TextField
                v-model="store"
                class="min-w-32 flex-1"
                :placeholder="t('products.offerStore')"
                required
            />
            <NumberField v-model="amount" class="w-28" :step="0.001" :placeholder="t('products.offerSize')" />
            <SelectField v-model="unit" class="w-24" :options="unitOptions" />
            <NumberField v-model="price" class="w-28" :step="0.01" :placeholder="t('products.offerPrice')" />
            <AppButton type="submit" size="sm" :disabled="store.trim() === '' || add.isPending.value">
                <Plus class="size-4" />{{ t('common.add') }}
            </AppButton>
        </form>
    </div>
</template>
