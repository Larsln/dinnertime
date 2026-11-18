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
        class="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-3"
        :class="{ 'opacity-60': isCooked }"
    >
        <RouterLink
            :to="{ name: 'recipe', params: { id: entry.recipe.id } }"
            class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-stone-100"
            :aria-label="entry.recipe.title"
        >
            <img
                v-if="entry.recipe.cover_url"
                :src="entry.recipe.cover_url"
                alt=""
                class="size-full object-cover"
                loading="lazy"
            />
            <UtensilsCrossed v-else class="size-6 text-stone-300" />
        </RouterLink>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">
                {{ t(`meals.${entry.meal}`) }}
            </p>
            <RouterLink
                :to="{ name: 'recipe', params: { id: entry.recipe.id } }"
                class="line-clamp-2 font-medium text-stone-900 hover:text-emerald-700"
                :class="{ 'line-through': isCooked }"
            >
                {{ entry.recipe.title }}
            </RouterLink>
            <p
                class="mt-1 inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600"
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
