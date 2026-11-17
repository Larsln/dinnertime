<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { useToasts } from '@/composables/useToasts';
import { formatDay } from '@/lib/format';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * One position of the shopping list: what to buy in which packages, what
 * remains of it and which recipes use it.
 */
const props = defineProps<{ item: ShoppingListItem }>();

const { t, locale } = useI18n();
const { amount } = useAmounts();
const { showError } = useToasts();
const queryClient = useQueryClient();

const isExpanded = ref(false);
const isChecked = computed(() => props.item.checked_at !== null);
const baseUnit = computed(() => props.item.product?.base_unit ?? 'g');

const purchase = computed(() => {
    if (props.item.packages.length > 0) {
        return props.item.packages.map(describePackage).join(' + ');
    }

    return props.item.purchase_amount !== null ? amount(props.item.purchase_amount, baseUnit.value) : '';
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

const setChecked = useMutation({
    mutationFn: (checked: boolean) => shoppingApi.setChecked(props.item.id, checked),
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
    <li class="px-4 py-3">
        <div class="flex items-center gap-3">
            <CheckboxField
                :model-value="isChecked"
                :aria-label="item.product?.name ?? item.label ?? ''"
                @update:model-value="(checked) => setChecked.mutate(checked)"
            />
            <div class="min-w-0 flex-1" :class="{ 'text-stone-400 line-through': isChecked }">
                <p class="font-medium">
                    {{ item.product?.name ?? item.label }}
                    <span v-if="item.is_approximate" :title="t('shopping.approximate')" class="text-amber-600"
                        >≈</span
                    >
                </p>
                <p v-if="purchase" class="text-sm text-stone-500">{{ purchase }}</p>
            </div>
            <StatusBadge v-if="item.wasted_amount > 0" tone="danger">
                {{ t('shopping.wasted', { amount: amount(item.wasted_amount, baseUnit) }) }}
            </StatusBadge>
            <StatusBadge v-if="item.leftover_amount > 0" tone="warning">
                {{ t('shopping.leftover', { amount: amount(item.leftover_amount, baseUnit) }) }}
            </StatusBadge>
            <AppButton
                v-if="item.uses.length > 0"
                size="icon"
                variant="ghost"
                :aria-label="t('shopping.showUses')"
                :aria-expanded="isExpanded"
                @click="isExpanded = !isExpanded"
            >
                <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': isExpanded }" />
            </AppButton>
            <AppButton
                v-else-if="item.is_manual"
                size="icon"
                variant="ghost"
                :aria-label="t('common.delete')"
                @click="remove.mutate()"
            >
                <Trash2 class="size-4" />
            </AppButton>
        </div>
        <ul v-if="isExpanded" class="mt-2 ml-8 space-y-1 text-sm text-stone-600">
            <li v-if="item.needed_amount !== null" class="font-medium">
                {{ t('shopping.needed', { amount: amount(item.needed_amount, baseUnit) }) }}
            </li>
            <li v-for="(use, index) in item.uses" :key="index">
                {{ formatDay(use.date, locale) }} · {{ use.title }} · {{ amount(use.amount, baseUnit) }}
            </li>
        </ul>
    </li>
</template>
