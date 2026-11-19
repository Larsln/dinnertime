<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Check, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { productsApi, type OfferInput } from '@/api/products';
import type { BaseUnit, OfferUnit, Product, ProductOffer } from '@/api/types';
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
 * The package sizes of a product: each with the store that sells it, or
 * sold everywhere, and its price. The entry row adds a size or corrects
 * the one that was loaded into it.
 */
const props = defineProps<{ product: Product }>();

const { t, locale } = useI18n();
const { showError } = useToasts();
const { currency } = useHouseholdSettings();
const queryClient = useQueryClient();

const editingId = ref<number | null>(null);
const store = ref('');
const amount = ref<number | null>(null);
const unit = ref<OfferUnit | null>(props.product.base_unit);

watch(
    () => props.product.base_unit,
    (baseUnit) => {
        unit.value = baseUnit;
    },
);
const price = ref<number | null>(null);

const UNITS: Record<BaseUnit, OfferUnit[]> = { g: ['g', 'kg'], ml: ['ml', 'l'], piece: ['piece'] };
const unitOptions = computed(() =>
    UNITS[props.product.base_unit].map((value) => ({
        value,
        label: value === 'piece' ? t('units.piece') : value,
    })),
);

const { activeStores, storeName, isActive } = useStores();

function describe(offer: ProductOffer): string {
    return formatOffer(
        offer,
        storeName(offer) ?? t('stores.everywhere'),
        locale.value,
        currency.value,
        t('units.piece'),
    );
}

function refresh(): void {
    for (const key of ['products', 'review', 'stores', 'shopping-list']) {
        queryClient.invalidateQueries({ queryKey: [key] });
    }
}

function edit(offer: ProductOffer): void {
    editingId.value = offer.id;
    store.value = storeName(offer) ?? '';
    amount.value = offer.amount;
    unit.value = offer.unit ?? props.product.base_unit;
    price.value = offer.price;
}

function reset(): void {
    editingId.value = null;
    store.value = '';
    amount.value = null;
    unit.value = props.product.base_unit;
    price.value = null;
}

function input(): OfferInput {
    const name = store.value.trim();

    return {
        ...(name === '' ? { store_id: null } : { store: name }),
        amount: amount.value,
        unit: amount.value === null ? null : unit.value,
        price: price.value,
    };
}

const save = useMutation({
    mutationFn: () =>
        editingId.value === null
            ? productsApi.addOffer(props.product.id, input())
            : productsApi.updateOffer(editingId.value, input()),
    onSuccess: () => {
        if (editingId.value === null) {
            amount.value = null;
            price.value = null;
        } else {
            reset();
        }

        refresh();
    },
    onError: showError,
});

const remove = useMutation({
    mutationFn: (id: number) => productsApi.removeOffer(id),
    onSuccess: (_, id) => {
        if (editingId.value === id) {
            reset();
        }

        refresh();
    },
    onError: showError,
});
</script>

<template>
    <section>
        <FieldLabel>{{ t('products.offers') }}</FieldLabel>
        <ul v-if="product.offers?.length" class="mb-2 space-y-1 text-sm text-ink">
            <li
                v-for="offer in product.offers"
                :key="offer.id"
                class="flex items-center gap-1 rounded-field pl-2"
                :class="{ 'bg-accent-soft': editingId === offer.id }"
            >
                <span class="flex-1" :class="{ 'text-ink-muted': !isActive(offer) }">
                    {{ describe(offer) }}
                    <template v-if="!isActive(offer)"> · {{ t('stores.paused') }}</template>
                </span>
                <AppButton size="icon" variant="ghost" :aria-label="t('common.edit')" @click="edit(offer)">
                    <Pencil class="size-4" />
                </AppButton>
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
        <div class="mb-2 flex flex-wrap gap-1">
            <AppButton
                size="sm"
                :variant="store.trim() === '' ? 'primary' : 'ghost'"
                :aria-pressed="store.trim() === ''"
                @click="store = ''"
            >
                {{ t('stores.everywhere') }}
            </AppButton>
            <AppButton
                v-for="entry in activeStores"
                :key="entry.id"
                size="sm"
                :variant="store === entry.name ? 'primary' : 'ghost'"
                :aria-pressed="store === entry.name"
                @click="store = entry.name"
            >
                {{ entry.name }}
            </AppButton>
        </div>
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="save.mutate()">
            <TextField v-model="store" class="min-w-32 flex-1" :placeholder="t('products.offerStore')" />
            <NumberField v-model="amount" class="w-28" :step="0.001" :placeholder="t('products.offerSize')" />
            <SelectField v-model="unit" class="w-24" :options="unitOptions" />
            <NumberField v-model="price" class="w-28" :step="0.01" :placeholder="t('products.offerPrice')" />
            <template v-if="editingId !== null">
                <AppButton type="submit" size="sm" variant="primary" :disabled="save.isPending.value">
                    <Check class="size-4" />{{ t('common.save') }}
                </AppButton>
                <AppButton size="sm" variant="ghost" @click="reset">{{ t('common.cancel') }}</AppButton>
            </template>
            <AppButton v-else type="submit" size="sm" :disabled="save.isPending.value">
                <Plus class="size-4" />{{ t('common.add') }}
            </AppButton>
        </form>
    </section>
</template>
