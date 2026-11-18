<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Toggle } from 'reka-ui';
import { Dices, Lock, LockOpen, UtensilsCrossed } from 'lucide-vue-next';
import { fromIsoDate } from '@/lib/dates';
import type { PreviewRow } from '@/lib/planWizard';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * Third step of planning a week: the suggested recipes, each of which can
 * be locked or replaced before the plan is saved.
 */
const rows = defineModel<PreviewRow[]>({ required: true });

defineProps<{ candidateCount: number; busy: boolean }>();

defineEmits<{ reroll: [row: PreviewRow]; rerollAll: [] }>();

const { t, locale } = useI18n();

function weekday(date: string): string {
    return new Intl.DateTimeFormat(locale.value, { weekday: 'short' }).format(fromIsoDate(date));
}

function minutesOf(row: PreviewRow): number {
    return (row.recipe.prep_minutes ?? 0) + (row.recipe.cook_minutes ?? 0);
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-stone-600">
                {{ t('plan.candidates', { recipes: candidateCount, meals: rows.length }) }}
            </p>
            <AppButton size="sm" :disabled="busy" @click="$emit('rerollAll')">
                <Dices class="size-4" />{{ t('plan.rerollAll') }}
            </AppButton>
        </div>
        <ul class="space-y-2">
            <li
                v-for="row in rows"
                :key="`${row.date}|${row.meal}`"
                class="flex items-center gap-3 rounded-xl border border-stone-200 p-2"
            >
                <div class="w-9 shrink-0 text-center">
                    <p class="text-base font-semibold tabular-nums">{{ fromIsoDate(row.date).getDate() }}</p>
                    <p class="text-xs text-stone-500">{{ weekday(row.date) }}</p>
                </div>
                <div
                    class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-stone-100"
                >
                    <img
                        v-if="row.recipe.cover_url"
                        :src="row.recipe.cover_url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <UtensilsCrossed v-else class="size-5 text-stone-300" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 text-sm font-medium text-stone-900">{{ row.recipe.title }}</p>
                    <p class="text-xs text-stone-500">
                        {{ t(`meals.${row.meal}`) }}
                        <template v-if="minutesOf(row) > 0">
                            · {{ t('recipes.minutes', { count: minutesOf(row) }) }}</template
                        >
                    </p>
                </div>
                <Toggle
                    v-model="row.locked"
                    :aria-label="t('plan.lock')"
                    class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-stone-400 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-600 data-[state=on]:bg-emerald-50 data-[state=on]:text-emerald-700"
                >
                    <component :is="row.locked ? Lock : LockOpen" class="size-4" />
                </Toggle>
                <AppButton
                    size="icon"
                    variant="ghost"
                    :aria-label="t('plan.reroll')"
                    :disabled="busy || row.locked"
                    @click="$emit('reroll', row)"
                >
                    <Dices class="size-4" />
                </AppButton>
            </li>
        </ul>
    </div>
</template>
