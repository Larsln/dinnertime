<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Clock, UtensilsCrossed } from 'lucide-vue-next';
import type { RecipeSummary } from '@/api/types';
import { totalMinutes } from '@/lib/dinnerRound';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * A recipe as a card to decide on: cover, title, total time and whether
 * everything it needs is in the pantry.
 */
const props = withDefaults(defineProps<{ recipe: RecipeSummary; missingCount?: number | null }>(), {
    missingCount: null,
});

const { t } = useI18n();

const minutes = computed(() => totalMinutes(props.recipe));
</script>

<template>
    <article class="overflow-hidden rounded-card border border-line bg-surface shadow-lg">
        <div class="flex aspect-[4/3] items-center justify-center bg-accent-soft">
            <img
                v-if="recipe.cover_url"
                :src="recipe.cover_url"
                alt=""
                draggable="false"
                class="size-full object-cover"
            />
            <UtensilsCrossed v-else class="size-14 text-accent/40" />
        </div>
        <div class="space-y-2 p-4">
            <h2 class="line-clamp-2 font-display text-2xl leading-tight font-bold tracking-tight text-ink">
                {{ recipe.title }}
            </h2>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-muted">
                <span v-if="minutes > 0" class="inline-flex items-center gap-1">
                    <Clock class="size-4" />{{ t('recipes.minutes', { count: minutes }) }}
                </span>
                <StatusBadge v-if="missingCount === 0" tone="success">
                    {{ t('dinner.allAvailable') }}
                </StatusBadge>
                <StatusBadge v-else-if="missingCount !== null" tone="warning">
                    {{ t('dinner.missing', missingCount) }}
                </StatusBadge>
            </p>
        </div>
    </article>
</template>
