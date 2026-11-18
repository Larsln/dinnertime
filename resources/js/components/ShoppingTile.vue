<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger, Primitive } from 'reka-ui';
import { Info, Trash2 } from 'lucide-vue-next';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { useToasts } from '@/composables/useToasts';
import { formatDay, formatNumber } from '@/lib/format';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * Tile of the shopping list. Tapping it checks the product off or brings
 * it back; the small info button tells which recipes need it and what
 * remains.
 */
const props = defineProps<{ item: ShoppingListItem }>();

const { t, locale } = useI18n();
const { amount } = useAmounts();
const { showError } = useToasts();
const queryClient = useQueryClient();

const isChecked = computed(() => props.item.checked_at !== null);
const baseUnit = computed(() => props.item.product?.base_unit ?? 'g');
const hasDetails = computed(() => props.item.uses.length > 0 || props.item.is_manual);

const purchase = computed(() => {
    const extras = props.item.extra_amounts.map(
        (extra) => `${formatNumber(extra.quantity, locale.value)} ${extra.unit_code}`,
    );

    if (props.item.packages.length > 0) {
        return [...props.item.packages.map(describePackage), ...extras].join(' + ');
    }

    return [
        ...(props.item.purchase_amount !== null ? [amount(props.item.purchase_amount, baseUnit.value)] : []),
        ...extras,
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
    <li class="relative">
        <Primitive
            as="button"
            type="button"
            :aria-pressed="isChecked"
            class="flex aspect-square w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl p-2 text-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            :class="
                isChecked
                    ? 'bg-stone-200 text-stone-500 hover:bg-stone-300'
                    : 'bg-emerald-600 text-white hover:bg-emerald-700'
            "
            @click="toggle.mutate()"
        >
            <span
                class="line-clamp-2 text-sm leading-tight font-semibold"
                :class="{ 'line-through': isChecked }"
            >
                {{ item.product?.name ?? item.label }}
            </span>
            <span v-if="purchase" class="line-clamp-2 text-xs leading-tight opacity-90">
                {{ purchase }}<template v-if="item.is_approximate"> ≈</template>
            </span>
        </Primitive>

        <PopoverRoot v-if="hasDetails">
            <PopoverTrigger
                :aria-label="t('shopping.showUses')"
                class="absolute top-1 right-1 flex size-6 cursor-pointer items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-emerald-600"
                :class="
                    isChecked ? 'text-stone-500 hover:bg-stone-300' : 'text-white/80 hover:bg-emerald-700'
                "
            >
                <Info class="size-4" />
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent
                    :side-offset="4"
                    class="z-50 w-64 rounded-xl border border-stone-200 bg-white p-3 text-sm text-stone-700 shadow-lg"
                >
                    <p class="font-semibold text-stone-900">{{ item.product?.name ?? item.label }}</p>
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
                    <AppButton
                        v-if="item.is_manual"
                        class="mt-2"
                        size="sm"
                        variant="ghost"
                        @click="remove.mutate()"
                    >
                        <Trash2 class="size-4" />{{ t('common.delete') }}
                    </AppButton>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </li>
</template>
