<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuPortal,
    ContextMenuRoot,
    ContextMenuTrigger,
    Primitive,
} from 'reka-ui';
import { Trash2 } from 'lucide-vue-next';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { useToasts } from '@/composables/useToasts';
import { formatDay, formatNumber } from '@/lib/format';
import ProductIcon from '@/components/ProductIcon.vue';

/**
 * Tile of the shopping list: a line icon, the product's name and a short
 * amount. Tapping it checks the product off or brings it back. A long
 * press or right click tells what exactly to buy, which recipes need it
 * and what remains.
 */
const props = defineProps<{ item: ShoppingListItem }>();

const { t, locale } = useI18n();
const { amount } = useAmounts();
const { showError } = useToasts();
const queryClient = useQueryClient();

const isChecked = computed(() => props.item.checked_at !== null);
const baseUnit = computed(() => props.item.product?.base_unit ?? 'g');
const name = computed(() => props.item.product?.name ?? props.item.label ?? '');

const amountText = computed(() =>
    props.item.purchase_amount !== null ? amount(props.item.purchase_amount, baseUnit.value) : '',
);

const extras = computed(() =>
    props.item.extra_amounts.map(
        (extra) => `${formatNumber(extra.quantity, locale.value)} ${extra.unit_code}`,
    ),
);

/**
 * The amount in as few characters as possible, for the tile.
 */
const shortAmount = computed(() => {
    const only = props.item.packages.length === 1 ? props.item.packages[0] : undefined;
    const packed =
        only && only.count > 1 ? `${only.count} × ${amount(only.amount, baseUnit.value)}` : amountText.value;

    return [...(packed ? [packed] : []), ...extras.value].join(' + ');
});

/**
 * What to buy in full: every package with its size, plus needs in units
 * that cannot be converted.
 */
const purchase = computed(() => {
    const packages = props.item.packages.map(describePackage);

    return [
        ...(packages.length > 0 ? packages : amountText.value ? [amountText.value] : []),
        ...extras.value,
    ].join(' + ');
});

/**
 * Count, label and size of a package. The size is left out for single
 * pieces, where it only repeats the label.
 */
function describePackage(entry: ShoppingListItem['packages'][number]): string {
    const isSinglePiece = baseUnit.value === 'piece' && entry.amount === 1;

    return isSinglePiece
        ? `${entry.count} × ${entry.label}`
        : `${entry.count} × ${entry.label} ${amount(entry.amount, baseUnit.value)}`;
}

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
}

const toggle = useMutation({
    mutationFn: () => shoppingApi.setChecked(props.item.id, !isChecked.value),
    onSuccess: refresh,
    onError: showError,
});

const remove = useMutation({
    mutationFn: () => shoppingApi.removeItem(props.item.id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <li>
        <ContextMenuRoot>
            <ContextMenuTrigger as-child>
                <Primitive
                    as="button"
                    type="button"
                    :aria-pressed="isChecked"
                    class="flex aspect-square w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-xl p-1.5 text-center transition-colors select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    :class="
                        isChecked
                            ? 'bg-stone-200 text-stone-400 hover:bg-stone-300'
                            : 'bg-emerald-600 text-white hover:bg-emerald-700'
                    "
                    @click="toggle.mutate()"
                >
                    <ProductIcon
                        :icon="item.product?.icon"
                        :section-key="item.product?.store_section?.key"
                        class="size-9"
                    />
                    <span class="line-clamp-1 w-full text-xs leading-tight font-medium">{{ name }}</span>
                    <span v-if="shortAmount" class="w-full truncate text-[10px] leading-none opacity-75">
                        {{ shortAmount }}<template v-if="item.is_approximate"> ≈</template>
                    </span>
                </Primitive>
            </ContextMenuTrigger>
            <ContextMenuPortal>
                <ContextMenuContent
                    class="z-50 w-64 rounded-xl border border-stone-200 bg-white p-3 text-sm text-stone-700 shadow-lg"
                >
                    <p class="font-semibold text-stone-900">{{ name }}</p>
                    <p v-if="purchase" class="mt-1">{{ purchase }}</p>
                    <p v-if="item.needed_amount !== null" class="mt-1">
                        {{ t('shopping.needed', { amount: amount(item.needed_amount, baseUnit) }) }}
                    </p>
                    <ul class="mt-1 space-y-0.5 text-stone-600">
                        <li v-for="(use, index) in item.uses" :key="index">
                            {{ formatDay(use.date, locale) }} · {{ use.title }} ·
                            {{ amount(use.amount, baseUnit) }}
                        </li>
                    </ul>
                    <p v-if="item.leftover_amount > 0" class="mt-1 text-amber-700">
                        {{ t('shopping.leftover', { amount: amount(item.leftover_amount, baseUnit) }) }}
                    </p>
                    <p v-if="item.wasted_amount > 0" class="mt-1 text-red-700">
                        {{ t('shopping.wasted', { amount: amount(item.wasted_amount, baseUnit) }) }}
                    </p>
                    <p v-if="item.is_approximate" class="mt-1 text-stone-500">
                        {{ t('shopping.approximate') }}
                    </p>
                    <ContextMenuItem
                        v-if="item.is_manual"
                        class="mt-2 flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-red-700 outline-none data-[highlighted]:bg-red-50"
                        @select="remove.mutate()"
                    >
                        <Trash2 class="size-4" />{{ t('common.delete') }}
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenuPortal>
        </ContextMenuRoot>
    </li>
</template>
