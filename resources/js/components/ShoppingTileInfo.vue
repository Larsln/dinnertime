<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ShoppingListItem } from '@/api/types';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useStores } from '@/composables/useStores';
import { formatOffer } from '@/lib/format';
import ShoppingAmountDetails from '@/components/ShoppingAmountDetails.vue';

/**
 * What exactly to buy for a position of the shopping list: its amounts
 * and those of the products bought as it, the packages stores sell and
 * the note of the product.
 */
const props = defineProps<{ item: ShoppingListItem; hasOwnAmounts: boolean }>();

const { t, locale } = useI18n();
const { currency } = useHouseholdSettings();
const { storeName, isActive } = useStores();

const baseUnit = computed(() => props.item.product?.base_unit ?? 'g');
</script>

<template>
    <ShoppingAmountDetails
        v-if="hasOwnAmounts"
        :amounts="item"
        :base-unit="baseUnit"
        :approximate="item.is_approximate"
    />
    <section
        v-for="variant in item.variants"
        :key="variant.product_id"
        class="mt-2 border-t border-line pt-2"
    >
        <p class="font-semibold text-ink">{{ variant.name }}</p>
        <ShoppingAmountDetails
            :amounts="variant"
            :base-unit="variant.base_unit"
            :approximate="item.is_approximate"
        />
    </section>
    <p v-if="item.is_approximate" class="mt-1 text-ink-muted">
        {{ t('shopping.approximate') }}
    </p>
    <ul v-if="item.product?.offers?.length" class="mt-2 border-t border-line pt-2">
        <li
            v-for="offer in item.product.offers"
            :key="offer.id"
            :class="{ 'text-ink-muted': !isActive(offer) }"
        >
            {{
                formatOffer(
                    offer,
                    storeName(offer) ?? t('stores.everywhere'),
                    locale,
                    currency,
                    t('units.piece'),
                )
            }}
            <template v-if="!isActive(offer)"> · {{ t('stores.paused') }}</template>
        </li>
    </ul>
    <p v-if="item.product?.note" class="mt-2 border-t border-line pt-2 whitespace-pre-line">
        {{ item.product.note }}
    </p>
</template>
