<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BaseUnit, ShoppingAmounts } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { useShoppingAmounts } from '@/composables/useShoppingAmounts';
import { formatDay } from '@/lib/format';

/**
 * What to buy of one product, how much the recipes need, which recipes
 * use it and what remains.
 */
const props = defineProps<{ amounts: ShoppingAmounts; baseUnit: BaseUnit; approximate: boolean }>();

const { t, locale } = useI18n();
const { amount } = useAmounts();
const { full } = useShoppingAmounts();

const purchase = computed(() => full(props.amounts, props.baseUnit));
</script>

<template>
    <div>
        <p v-if="purchase" class="mt-1"><template v-if="approximate">≈ </template>{{ purchase }}</p>
        <p v-if="amounts.needed_amount !== null" class="mt-1">
            {{ t('shopping.needed', { amount: amount(amounts.needed_amount, baseUnit) }) }}
        </p>
        <ul class="mt-1 space-y-0.5 text-ink-muted">
            <li v-for="(use, index) in amounts.uses" :key="index">
                {{ formatDay(use.date, locale) }} · {{ use.title }} · {{ amount(use.amount, baseUnit) }}
            </li>
        </ul>
        <p v-if="amounts.leftover_amount > 0" class="mt-1 text-warn-deep">
            {{ t('shopping.leftover', { amount: amount(amounts.leftover_amount, baseUnit) }) }}
        </p>
        <p v-if="amounts.wasted_amount > 0" class="mt-1 text-danger-deep">
            {{ t('shopping.wasted', { amount: amount(amounts.wasted_amount, baseUnit) }) }}
        </p>
    </div>
</template>
