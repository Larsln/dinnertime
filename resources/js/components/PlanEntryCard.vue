<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, ArrowRight, Pencil, Trash2, Users, UtensilsCrossed } from 'lucide-vue-next';
import type { MealPlanEntry, PlanReason } from '@/api/types';
import { daysUntil } from '@/lib/dates';
import { formatNumber } from '@/lib/format';
import PlanReasons from '@/components/PlanReasons.vue';
import PortionVerdictField from '@/components/PortionVerdictField.vue';
import ActionMenu from '@/components/ui/ActionMenu.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';

/**
 * One planned meal: cover of the recipe, meal, title and portions, why it
 * goes with the rest of the plan, how the portion was once it was eaten, the switch for "cooked" and a menu to
 * change, move or remove it.
 */
const props = withDefaults(defineProps<{ entry: MealPlanEntry; reasons?: PlanReason[] }>(), {
    reasons: () => [],
});

const emit = defineEmits<{ cooked: [cooked: boolean]; edit: []; move: [days: number]; remove: [] }>();

const { t, locale } = useI18n();

const isCooked = computed(() => props.entry.cooked_at !== null);
const isEaten = computed(() => isCooked.value || daysUntil(props.entry.date) <= 0);

const actions = computed(() => [
    { label: t('plan.editEntry'), icon: Pencil, select: () => emit('edit') },
    { label: t('plan.dayLater'), icon: ArrowRight, select: () => emit('move', 1) },
    { label: t('plan.dayEarlier'), icon: ArrowLeft, select: () => emit('move', -1) },
    { label: t('common.delete'), icon: Trash2, danger: true, select: () => emit('remove') },
]);
</script>

<template>
    <li
        class="flex items-center gap-4 rounded-card border border-line p-3"
        :class="isCooked ? 'bg-sunken' : 'bg-surface'"
    >
        <RouterLink
            :to="{ name: 'recipe', params: { id: entry.recipe.id } }"
            class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-field bg-accent-soft"
            :aria-label="entry.recipe.title"
        >
            <img
                v-if="entry.recipe.cover_url"
                :src="entry.recipe.cover_url"
                alt=""
                class="size-full object-cover"
                loading="lazy"
            />
            <UtensilsCrossed v-else class="size-6 text-accent/40" />
        </RouterLink>
        <div class="min-w-0 flex-1">
            <p
                class="text-xs font-bold tracking-wider uppercase"
                :class="isCooked ? 'text-ink-muted' : 'text-accent-deep'"
            >
                {{ t(`meals.${entry.meal}`) }}
            </p>
            <RouterLink
                :to="{ name: 'recipe', params: { id: entry.recipe.id } }"
                class="line-clamp-2 font-semibold hover:text-accent-deep"
                :class="isCooked ? 'text-ink-muted line-through' : 'text-ink'"
            >
                {{ entry.recipe.title }}
            </RouterLink>
            <p
                class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs text-ink-muted"
                :class="isCooked ? 'bg-paper' : 'bg-sunken'"
            >
                <Users class="size-3.5" />{{
                    t('plan.servingsLong', { count: formatNumber(entry.portions, locale) })
                }}
            </p>
            <PortionVerdictField v-if="isEaten" class="mt-2" :entry="entry" />
            <PlanReasons v-if="!isCooked" class="mt-2" :reasons="reasons" />
        </div>
        <CheckboxField
            :model-value="isCooked"
            :label="t('plan.cooked')"
            @update:model-value="(cooked) => $emit('cooked', cooked)"
        />
        <ActionMenu :label="t('plan.entryActions')" :actions="actions" />
    </li>
</template>
