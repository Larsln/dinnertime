<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { Plus, Trash2 } from 'lucide-vue-next';
import { pantryApi } from '@/api/planning';
import type { PantryItem, Product } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { useToasts } from '@/composables/useToasts';
import { daysUntil, toIsoDate } from '@/lib/dates';
import { formatDay } from '@/lib/format';
import ProductPicker from '@/components/ProductPicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import DateField from '@/components/ui/DateField.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import NumberField from '@/components/ui/NumberField.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * Opened leftovers and other stock, soonest expiry first.
 */
const SOON_DAYS = 2;

const { t, locale } = useI18n();
const { amount } = useAmounts();
const { showError } = useToasts();
const queryClient = useQueryClient();

const items = useQuery({ queryKey: ['pantry'], queryFn: pantryApi.list });

const isAdding = ref(false);
const product = ref<Product | null>(null);
const quantity = ref<number | null>(null);
const openedOn = ref<string | null>(toIsoDate(new Date()));

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['pantry'] });
}

function expiryTone(item: PantryItem): 'danger' | 'warning' | 'neutral' {
    const days = daysUntil(item.expires_on ?? '');

    return days < 0 ? 'danger' : days <= SOON_DAYS ? 'warning' : 'neutral';
}

const add = useMutation({
    mutationFn: () =>
        pantryApi.create({
            product_id: product.value?.id ?? 0,
            amount: quantity.value ?? 0,
            opened_on: openedOn.value,
        }),
    onSuccess: () => {
        refresh();
        isAdding.value = false;
        product.value = null;
        quantity.value = null;
    },
    onError: showError,
});

const remove = useMutation({
    mutationFn: (id: number) => pantryApi.remove(id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <PageHeader :title="t('nav.pantry')" :subtitle="t('pantry.subtitle')">
        <AppButton variant="primary" @click="isAdding = true"
            ><Plus class="size-4" />{{ t('pantry.add') }}</AppButton
        >
    </PageHeader>

    <ul
        v-if="items.data.value?.length"
        class="divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white"
    >
        <li v-for="item in items.data.value" :key="item.id" class="flex items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium">{{ item.product.name }}</p>
                <p class="text-sm text-stone-500">{{ amount(item.amount, item.product.base_unit) }}</p>
            </div>
            <StatusBadge v-if="item.expires_on" :tone="expiryTone(item)">
                {{ t('pantry.expires', { date: formatDay(item.expires_on, locale) }) }}
            </StatusBadge>
            <AppButton
                size="icon"
                variant="ghost"
                :aria-label="t('common.delete')"
                @click="remove.mutate(item.id)"
            >
                <Trash2 class="size-4" />
            </AppButton>
        </li>
    </ul>
    <EmptyState v-else-if="!items.isPending.value" :title="t('pantry.empty')" :hint="t('pantry.emptyHint')" />

    <DialogPanel v-model:open="isAdding" :title="t('pantry.add')" :close-label="t('common.close')">
        <ProductPicker v-model="product" :label="t('products.product')" creatable />
        <NumberField
            v-model="quantity"
            :label="t('pantry.amount', { unit: product ? t(`baseUnits.${product.base_unit}`) : '' })"
            :step="10"
        />
        <DateField v-model="openedOn" :label="t('pantry.openedOn')" />
        <template #actions>
            <AppButton
                variant="primary"
                :disabled="product === null || !quantity || add.isPending.value"
                @click="add.mutate()"
            >
                {{ t('common.save') }}
            </AppButton>
        </template>
    </DialogPanel>
</template>
