<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { X } from 'lucide-vue-next';
import type { MealPlanEntry } from '@/api/types';
import { daysUntil } from '@/lib/dates';
import { formatNumber } from '@/lib/format';
import PortionVerdictField from '@/components/PortionVerdictField.vue';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * One side served with a planned meal: its recipe scaled to its own
 * portions, how the portion was once it was eaten, and a button to take
 * it off the meal.
 */
const props = defineProps<{ side: MealPlanEntry }>();

defineEmits<{ remove: [] }>();

const { t, locale } = useI18n();

const isEaten = computed(() => props.side.cooked_at !== null || daysUntil(props.side.date) <= 0);
</script>

<template>
    <li class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        <span class="text-ink-muted">{{ t('plan.sides.with') }}</span>
        <RouterLink
            :to="{ name: 'recipe', params: { id: side.recipe.id }, query: { servings: side.portions } }"
            class="font-medium text-ink hover:text-accent-deep"
        >
            {{ side.recipe.title }}
        </RouterLink>
        <span class="text-xs text-ink-muted">
            {{ t('plan.servingsLong', { count: formatNumber(side.portions, locale) }) }}
        </span>
        <AppButton
            size="icon"
            variant="ghost"
            class="size-6!"
            :aria-label="t('plan.sides.remove', { name: side.recipe.title })"
            @click="$emit('remove')"
        >
            <X class="size-3.5" />
        </AppButton>
        <PortionVerdictField v-if="isEaten" class="basis-full" :entry="side" />
    </li>
</template>
