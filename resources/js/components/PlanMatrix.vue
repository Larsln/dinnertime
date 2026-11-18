<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Toggle } from 'reka-ui';
import { Check, Plus } from 'lucide-vue-next';
import type { MealType } from '@/api/types';
import { fromIsoDate } from '@/lib/dates';
import { MEAL_TYPES } from '@/lib/meals';
import { presetSlots, slotKey, type DayPreset, type PlanSlot } from '@/lib/planSlots';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * Grid of days and meals in which the slots to generate are ticked.
 *
 * Slots that are planned already cannot be chosen. Presets tick whole
 * groups of days and meals at once.
 */
const model = defineModel<PlanSlot[]>({ required: true });

const props = defineProps<{ days: string[]; planned: Set<string> }>();

const MAIN_MEALS: MealType[] = ['breakfast', 'lunch', 'dinner'];
const DAY_PRESETS: DayPreset[] = ['all', 'weekdays', 'weekend'];

const { t, locale } = useI18n();

const showsAllMeals = ref(false);
const dayPreset = ref<DayPreset>('all');

const meals = computed(() => (showsAllMeals.value ? MEAL_TYPES : MAIN_MEALS));
const selected = computed(() => new Set(model.value.map(slotKey)));

const mealPresets = computed<{ key: string; meals: MealType[] }[]>(() => [
    { key: 'dinner', meals: ['dinner'] },
    { key: 'lunch', meals: ['lunch'] },
    { key: 'allMeals', meals: meals.value },
]);

function dayLabel(date: string): string {
    return new Intl.DateTimeFormat(locale.value, { weekday: 'short', day: 'numeric' }).format(
        fromIsoDate(date),
    );
}

function toggle(slot: PlanSlot, isOn: boolean): void {
    const others = model.value.filter((entry) => slotKey(entry) !== slotKey(slot));

    model.value = isOn ? [...others, slot] : others;
}

function applyMeals(presetMeals: MealType[]): void {
    model.value = presetSlots(props.days, dayPreset.value, presetMeals, props.planned);
}

function applyDays(preset: DayPreset): void {
    const chosenMeals = [...new Set(model.value.map((slot) => slot.meal))];

    dayPreset.value = preset;
    model.value = presetSlots(
        props.days,
        preset,
        chosenMeals.length > 0 ? chosenMeals : ['dinner'],
        props.planned,
    );
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <AppButton
                v-for="preset in DAY_PRESETS"
                :key="preset"
                size="sm"
                :variant="dayPreset === preset ? 'primary' : 'secondary'"
                @click="applyDays(preset)"
            >
                {{ t(`plan.days.${preset}`) }}
            </AppButton>
        </div>
        <div class="flex flex-wrap gap-2">
            <AppButton
                v-for="preset in mealPresets"
                :key="preset.key"
                size="sm"
                @click="applyMeals(preset.meals)"
            >
                {{ t(`plan.mealPresets.${preset.key}`) }}
            </AppButton>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-separate border-spacing-1 text-sm">
                <thead>
                    <tr>
                        <th />
                        <th
                            v-for="meal in meals"
                            :key="meal"
                            class="px-1 pb-1 text-xs font-medium text-stone-500"
                        >
                            {{ t(`meals.${meal}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="day in days" :key="day">
                        <th class="pr-2 text-left font-medium whitespace-nowrap text-stone-700">
                            {{ dayLabel(day) }}
                        </th>
                        <td v-for="meal in meals" :key="meal">
                            <span
                                v-if="planned.has(slotKey({ date: day, meal }))"
                                class="flex h-10 items-center justify-center rounded-lg bg-stone-100 text-xs text-stone-500"
                            >
                                {{ t('plan.planned') }}
                            </span>
                            <Toggle
                                v-else
                                :model-value="selected.has(slotKey({ date: day, meal }))"
                                :aria-label="`${dayLabel(day)} ${t(`meals.${meal}`)}`"
                                class="flex h-10 w-full min-w-14 cursor-pointer items-center justify-center rounded-lg border border-stone-300 bg-white transition-colors hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 data-[state=on]:border-emerald-600 data-[state=on]:bg-emerald-600"
                                @update:model-value="(isOn) => toggle({ date: day, meal }, isOn)"
                            >
                                <Check
                                    v-if="selected.has(slotKey({ date: day, meal }))"
                                    class="size-4 text-white"
                                />
                            </Toggle>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppButton v-if="!showsAllMeals" size="sm" variant="ghost" @click="showsAllMeals = true">
            <Plus class="size-4" />{{ t('plan.showMoreMeals') }}
        </AppButton>
    </div>
</template>
