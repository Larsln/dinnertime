<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Trash2, Users, UtensilsCrossed } from 'lucide-vue-next';
import type { MealPlanEntry } from '@/api/types';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';

/**
 * One planned meal: cover of the recipe, meal, title and servings, with
 * the switch for "cooked" and the button to remove it from the plan.
 */
const props = defineProps<{ entry: MealPlanEntry }>();

defineEmits<{ cooked: [cooked: boolean]; remove: [] }>();

const { t } = useI18n();

const isCooked = computed(() => props.entry.cooked_at !== null);
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
                <Users class="size-3.5" />{{ t('plan.servingsLong', { count: entry.servings }) }}
            </p>
        </div>
        <CheckboxField
            :model-value="isCooked"
            :label="t('plan.cooked')"
            @update:model-value="(cooked) => $emit('cooked', cooked)"
        />
        <AppButton size="icon" variant="ghost" :aria-label="t('common.delete')" @click="$emit('remove')">
            <Trash2 class="size-4" />
        </AppButton>
    </li>
</template>
