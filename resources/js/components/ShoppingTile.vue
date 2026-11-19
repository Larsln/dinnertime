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
import { Check, Trash2 } from 'lucide-vue-next';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { useToasts } from '@/composables/useToasts';
import { formatDay, formatNumber, formatOffer } from '@/lib/format';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useStores } from '@/composables/useStores';
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
const { currency } = useHouseholdSettings();
const { storeName, isActive } = useStores();
const { showError } = useToasts();
const queryClient = useQueryClient();

const isChecked = computed(() => props.item.checked_at !== null);
const hasInfo = computed(
    () => Boolean(props.item.product?.note) || (props.item.product?.offers ?? []).some(isActive),
);
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
 * Count, store and size of a package. The store is left out for sizes
 * sold everywhere, the size for single pieces of a store, where it adds
 * nothing to the name.
 */
function describePackage(entry: ShoppingListItem['packages'][number]): string {
    const label = entry.label.trim();
    const isSinglePiece = baseUnit.value === 'piece' && entry.amount === 1;
    const size = label !== '' && isSinglePiece ? '' : amount(entry.amount, baseUnit.value);

    return [`${entry.count} ×`, label, size].filter((part) => part !== '').join(' ');
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
                    class="relative flex aspect-square w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-tile border p-1.5 text-center transition-colors select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    :class="
                        isChecked
                            ? 'border-line bg-sunken text-done-ink hover:bg-line'
                            : 'border-transparent bg-accent text-on-accent hover:bg-accent-hover'
                    "
                    @click="toggle.mutate()"
                >
                    <span
                        v-if="hasInfo"
                        class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-current opacity-80"
                    />
                    <Check v-if="isChecked" class="size-9 text-done-mark" aria-hidden="true" />
                    <ProductIcon
                        v-else
                        :icon="item.product?.icon"
                        :section-key="item.product?.store_section?.key"
                        class="size-9"
                    />
                    <span
                        class="line-clamp-1 w-full text-xs leading-tight font-semibold"
                        :class="{ 'line-through': isChecked }"
                        >{{ name }}</span
                    >
                    <span v-if="shortAmount" class="w-full truncate text-[10px] leading-none font-medium">
                        <template v-if="item.is_approximate">≈ </template>{{ shortAmount }}
                    </span>
                </Primitive>
            </ContextMenuTrigger>
            <ContextMenuPortal>
                <ContextMenuContent
                    class="z-50 w-64 rounded-tile border border-line bg-surface p-3 text-sm text-ink shadow-lg"
                >
                    <p class="font-semibold text-ink">{{ name }}</p>
                    <p v-if="purchase" class="mt-1">
                        <template v-if="item.is_approximate">≈ </template>{{ purchase }}
                    </p>
                    <p v-if="item.needed_amount !== null" class="mt-1">
                        {{ t('shopping.needed', { amount: amount(item.needed_amount, baseUnit) }) }}
                    </p>
                    <ul class="mt-1 space-y-0.5 text-ink-muted">
                        <li v-for="(use, index) in item.uses" :key="index">
                            {{ formatDay(use.date, locale) }} · {{ use.title }} ·
                            {{ amount(use.amount, baseUnit) }}
                        </li>
                    </ul>
                    <p v-if="item.leftover_amount > 0" class="mt-1 text-warn-deep">
                        {{ t('shopping.leftover', { amount: amount(item.leftover_amount, baseUnit) }) }}
                    </p>
                    <p v-if="item.wasted_amount > 0" class="mt-1 text-danger-deep">
                        {{ t('shopping.wasted', { amount: amount(item.wasted_amount, baseUnit) }) }}
                    </p>
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
                    <ContextMenuItem
                        v-if="item.is_manual"
                        class="mt-2 flex cursor-pointer items-center gap-2 rounded-field px-2 py-1.5 text-danger-deep outline-none data-[highlighted]:bg-danger-soft"
                        @select="remove.mutate()"
                    >
                        <Trash2 class="size-4" />{{ t('common.delete') }}
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenuPortal>
        </ContextMenuRoot>
    </li>
</template>
