<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { mealPlanApi } from '@/api/planning';
import type { ShoppingList } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';
import { formatLongDay, formatNumber } from '@/lib/format';
import { MEAL_TYPES } from '@/lib/meals';
import { ingredientsOf } from '@/lib/shoppingMeals';
import DialogPanel from '@/components/ui/DialogPanel.vue';

/**
 * The meals a shopping list is for, each with what the list holds for it
 * and what it takes from the stock.
 */
const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{ list: ShoppingList }>();

const { t, locale } = useI18n();
const { amount } = useAmounts();

const period = computed(() => ({ from: props.list.starts_on, to: props.list.ends_on }));

const entries = useQuery({
    queryKey: ['meal-plan', period],
    queryFn: () => mealPlanApi.list(period.value),
    enabled: open,
});

const meals = computed(() =>
    [...(entries.data.value ?? [])]
        .sort(
            (a, b) => a.date.localeCompare(b.date) || MEAL_TYPES.indexOf(a.meal) - MEAL_TYPES.indexOf(b.meal),
        )
        .map((entry) => ({ entry, ingredients: ingredientsOf(props.list, entry) })),
);
</script>

<template>
    <DialogPanel
        v-model:open="open"
        :title="t('shopping.mealsTitle')"
        :description="t('shopping.mealsDescription')"
        :close-label="t('common.close')"
        size="lg"
    >
        <p v-if="!entries.isPending.value && meals.length === 0" class="text-sm text-ink-muted">
            {{ t('shopping.noMeals') }}
        </p>
        <article v-for="{ entry, ingredients } in meals" :key="entry.id">
            <p class="text-sm text-ink-muted">
                {{ formatLongDay(entry.date, locale) }} · {{ t(`meals.${entry.meal}`) }} ·
                {{ t('plan.servingsLong', { count: formatNumber(entry.portions, locale) }) }}
            </p>
            <h3 class="font-semibold text-ink">{{ entry.recipe.title }}</h3>
            <ul v-if="ingredients.length > 0" class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-sm">
                <li
                    v-for="(ingredient, index) in ingredients"
                    :key="index"
                    :class="{
                        'text-ink-muted': ingredient.state !== 'open',
                        'line-through': ingredient.state === 'done' || ingredient.state === 'skipped',
                    }"
                >
                    {{ ingredient.name }}
                    <template v-if="ingredient.amount !== null">
                        {{ amount(ingredient.amount, ingredient.baseUnit) }}
                    </template>
                    <template v-if="ingredient.state === 'stocked'">
                        · {{ t('shopping.stockedMark') }}</template
                    >
                </li>
            </ul>
            <p v-else class="mt-1 text-sm text-ink-muted">{{ t('shopping.nothingListed') }}</p>
        </article>
    </DialogPanel>
</template>
