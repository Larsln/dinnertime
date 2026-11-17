<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Clock, UtensilsCrossed } from 'lucide-vue-next';
import type { RecipeSummary } from '@/api/types';

/**
 * Tile linking to a recipe, with cover, title, total time and meal tags.
 */
const props = defineProps<{ recipe: RecipeSummary }>();

const { t } = useI18n();

const totalMinutes = computed(() => (props.recipe.prep_minutes ?? 0) + (props.recipe.cook_minutes ?? 0));
const meals = computed(() => (props.recipe.tags ?? []).filter((tag) => tag.type === 'meal'));
</script>

<template>
    <RouterLink
        :to="{ name: 'recipe', params: { id: recipe.id } }"
        class="group overflow-hidden rounded-2xl border border-stone-200 bg-white transition-shadow hover:shadow-md"
    >
        <div class="flex aspect-[4/3] items-center justify-center bg-stone-100">
            <img
                v-if="recipe.cover_url"
                :src="recipe.cover_url"
                :alt="recipe.title"
                class="size-full object-cover"
                loading="lazy"
            />
            <UtensilsCrossed v-else class="size-10 text-stone-300" />
        </div>
        <div class="p-3">
            <h2 class="line-clamp-2 font-medium text-stone-900 group-hover:text-emerald-700">
                {{ recipe.title }}
            </h2>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500">
                <span v-if="totalMinutes > 0" class="inline-flex items-center gap-1">
                    <Clock class="size-3.5" />
                    {{ t('recipes.minutes', { count: totalMinutes }) }}
                </span>
                <span v-for="meal in meals" :key="meal.id">{{ meal.name }}</span>
            </p>
        </div>
    </RouterLink>
</template>
