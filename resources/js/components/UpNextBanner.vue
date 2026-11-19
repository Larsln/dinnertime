<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Users, UtensilsCrossed } from 'lucide-vue-next';
import type { MealPlanEntry } from '@/api/types';
import { daysUntil } from '@/lib/dates';
import { formatLongDay } from '@/lib/format';

/**
 * The next meal to cook as a wide banner that opens its recipe scaled to
 * the planned number of servings.
 */
const props = defineProps<{ entry: MealPlanEntry }>();

const { t, locale } = useI18n();

const day = computed(() => {
    const distance = daysUntil(props.entry.date);

    if (distance === 0) {
        return t('plan.today');
    }

    return distance === 1 ? t('upNext.tomorrow') : formatLongDay(props.entry.date, locale.value);
});
</script>

<template>
    <RouterLink
        :to="{ name: 'recipe', params: { id: entry.recipe.id }, query: { servings: entry.servings } }"
        class="group grid overflow-hidden rounded-card border border-line bg-surface transition-shadow hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:grid-cols-[2fr_3fr]"
    >
        <div
            class="relative flex aspect-[16/9] items-center justify-center bg-accent-soft sm:aspect-auto sm:min-h-48"
        >
            <img
                v-if="entry.recipe.cover_url"
                :src="entry.recipe.cover_url"
                alt=""
                class="absolute inset-0 size-full object-cover"
            />
            <UtensilsCrossed v-else class="size-12 text-accent/40" />
        </div>
        <div class="flex flex-col justify-center gap-2 p-5 sm:p-6">
            <p class="text-xs font-bold tracking-wider text-accent-deep uppercase">
                {{ day }} · {{ t(`meals.${entry.meal}`) }}
            </p>
            <h2
                class="line-clamp-3 font-display text-2xl leading-tight font-bold tracking-tight text-ink group-hover:text-accent-deep sm:text-3xl"
            >
                {{ entry.recipe.title }}
            </h2>
            <p
                class="inline-flex items-center gap-1 self-start rounded-full bg-sunken px-2.5 py-1 text-sm text-ink-muted"
            >
                <Users class="size-4" />{{ t('plan.servingsLong', { count: entry.servings }) }}
            </p>
        </div>
    </RouterLink>
</template>
