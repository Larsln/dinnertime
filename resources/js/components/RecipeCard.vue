<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Clock, Heart, Star, UtensilsCrossed } from 'lucide-vue-next';
import type { RecipeSummary } from '@/api/types';
import { formatNumber } from '@/lib/format';

/**
 * Tile linking to a recipe, with cover, title, course, rating, total time and meal tags.
 */
const props = defineProps<{ recipe: RecipeSummary }>();

const { t, locale } = useI18n();

const totalMinutes = computed(() => (props.recipe.prep_minutes ?? 0) + (props.recipe.cook_minutes ?? 0));
const meals = computed(() => (props.recipe.tags ?? []).filter((tag) => tag.type === 'meal'));
</script>

<template>
    <RouterLink
        :to="{ name: 'recipe', params: { id: recipe.id } }"
        class="group overflow-hidden rounded-card border border-line bg-surface transition-shadow hover:shadow-md"
    >
        <div class="relative flex aspect-[4/3] items-center justify-center bg-accent-soft">
            <Heart
                v-if="recipe.is_favorite"
                class="absolute top-2 right-2 size-5 fill-accent text-accent drop-shadow"
                :aria-label="t('recipes.favorite')"
            />
            <img
                v-if="recipe.cover_url"
                :src="recipe.cover_url"
                :alt="recipe.title"
                class="size-full object-cover"
                loading="lazy"
            />
            <UtensilsCrossed v-else class="size-10 text-accent/40" />
        </div>
        <div class="p-3">
            <h2 class="line-clamp-2 leading-snug font-semibold text-ink group-hover:text-accent-deep">
                {{ recipe.title }}
            </h2>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
                <span v-if="recipe.course_name" class="font-medium text-ink">{{ recipe.course_name }}</span>
                <span v-if="recipe.average_rating != null" class="inline-flex items-center gap-1">
                    <Star class="size-3.5 fill-accent text-accent" />
                    {{ formatNumber(recipe.average_rating / 2, locale, 1) }}
                </span>
                <span v-if="totalMinutes > 0" class="inline-flex items-center gap-1">
                    <Clock class="size-3.5" />
                    {{ t('recipes.minutes', { count: totalMinutes }) }}
                </span>
                <span v-for="meal in meals" :key="meal.id">{{ meal.name }}</span>
            </p>
        </div>
    </RouterLink>
</template>
