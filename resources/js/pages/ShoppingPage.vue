<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { Info, PackageCheck, RefreshCw } from 'lucide-vue-next';
import { ApiError } from '@/api/client';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useToasts } from '@/composables/useToasts';
import { addDays, startOfWeek, toIsoDate } from '@/lib/dates';
import { formatDay } from '@/lib/format';
import ShoppingAddField from '@/components/ShoppingAddField.vue';
import ShoppingMealsDialog from '@/components/ShoppingMealsDialog.vue';
import ShoppingStockedNote from '@/components/ShoppingStockedNote.vue';
import ShoppingTile from '@/components/ShoppingTile.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';

/**
 * The current shopping list as tiles grouped by store section. What is
 * checked off or skipped moves to the end. On request a note lists what
 * the meals take from the stock.
 */
const NOT_FOUND = 404;
const GRID_CLASS = 'grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-7 lg:grid-cols-9';

const { t, locale } = useI18n();
const queryClient = useQueryClient();
const { showError, showSuccess } = useToasts();
const { showsStockedNeeds } = useHouseholdSettings();

const list = useQuery({
    queryKey: ['shopping-list'],
    queryFn: () =>
        shoppingApi.current().catch((error) => {
            if (error instanceof ApiError && error.status === NOT_FOUND) {
                return null;
            }

            throw error;
        }),
});

const isStocking = ref(false);
const isShowingMeals = ref(false);

function isSettled(item: ShoppingListItem): boolean {
    return item.checked_at !== null || item.skipped_at !== null;
}

const sections = computed(() => {
    const groups = new Map<string, ShoppingListItem[]>();

    for (const item of (list.data.value?.items ?? []).filter((entry) => !isSettled(entry))) {
        const title = item.product
            ? (item.product.store_section?.name ?? t('shopping.noSection'))
            : t('shopping.other');

        groups.set(title, [...(groups.get(title) ?? []), item]);
    }

    return [...groups.entries()].map(([title, items]) => ({ title, items }));
});

const checkedItems = computed(() => (list.data.value?.items ?? []).filter(isSettled));

const openCount = computed(() => (list.data.value?.items ?? []).filter((item) => !isSettled(item)).length);

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
}

function thisWeek(): { from: string; to: string } {
    const monday = startOfWeek(new Date());

    return { from: toIsoDate(monday), to: toIsoDate(addDays(monday, 6)) };
}

const sync = useMutation({
    mutationFn: () =>
        shoppingApi.sync(
            list.data.value ? { from: list.data.value.starts_on, to: list.data.value.ends_on } : thisWeek(),
        ),
    onSuccess: refresh,
    onError: showError,
});

const addManual = useMutation({
    mutationFn: (entry: { label: string } | { product_id: number }) =>
        shoppingApi.addItem(list.data.value?.id ?? 0, entry),
    onSuccess: refresh,
    onError: showError,
});

const stock = useMutation({
    mutationFn: () => shoppingApi.stockLeftovers(list.data.value?.id ?? 0),
    onSuccess: () => {
        refresh();
        queryClient.invalidateQueries({ queryKey: ['pantry'] });
        showSuccess(t('shopping.stocked'));
    },
    onError: showError,
});
</script>

<template>
    <template v-if="list.data.value">
        <PageHeader
            :title="t('nav.shopping')"
            :subtitle="`${formatDay(list.data.value.starts_on, locale)} – ${formatDay(list.data.value.ends_on, locale)} · ${t('shopping.open', { count: openCount })}`"
        >
            <AppButton @click="isShowingMeals = true"
                ><Info class="size-4" />{{ t('shopping.meals') }}</AppButton
            >
            <AppButton :disabled="sync.isPending.value" @click="sync.mutate()"
                ><RefreshCw class="size-4" />{{ t('shopping.refresh') }}</AppButton
            >
            <AppButton v-if="list.data.value.leftovers_stocked_at === null" @click="isStocking = true">
                <PackageCheck class="size-4" />{{ t('shopping.stockLeftovers') }}
            </AppButton>
        </PageHeader>

        <ShoppingAddField
            class="mb-6"
            @product="(product) => addManual.mutate({ product_id: product.id })"
            @text="(label) => addManual.mutate({ label })"
        />

        <section v-for="section in sections" :key="section.title" class="mb-6">
            <h2 class="mb-2 font-display text-xl font-bold text-ink">
                {{ section.title }}
            </h2>
            <ul :class="GRID_CLASS">
                <ShoppingTile v-for="item in section.items" :key="item.id" :item="item" />
            </ul>
        </section>

        <ShoppingStockedNote
            v-if="showsStockedNeeds && list.data.value.in_stock.length > 0"
            :needs="list.data.value.in_stock"
        />

        <section v-if="checkedItems.length > 0" class="mb-6">
            <h2 class="mb-2 text-[15px] font-semibold text-done-mark">
                {{ t('shopping.done', { count: checkedItems.length }) }}
            </h2>
            <ul :class="GRID_CLASS">
                <ShoppingTile v-for="item in checkedItems" :key="item.id" :item="item" />
            </ul>
        </section>

        <ShoppingMealsDialog v-model:open="isShowingMeals" :list="list.data.value" />
        <ConfirmDialog
            v-model:open="isStocking"
            :title="t('shopping.stockLeftovers')"
            :description="t('shopping.stockDescription')"
            :confirm-label="t('shopping.stockLeftovers')"
            :cancel-label="t('common.cancel')"
            @confirm="stock.mutate()"
        />
    </template>

    <template v-else-if="!list.isPending.value">
        <PageHeader :title="t('nav.shopping')" />
        <EmptyState :title="t('shopping.empty')" :hint="t('shopping.emptyHint')">
            <AppButton variant="primary" :disabled="sync.isPending.value" @click="sync.mutate()">
                {{ t('shopping.createForThisWeek') }}
            </AppButton>
        </EmptyState>
    </template>
</template>
