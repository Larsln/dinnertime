<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { mealPlanApi } from '@/api/planning';
import type { MealPlanEntry, MealType, RecipeSummary } from '@/api/types';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useToasts } from '@/composables/useToasts';
import { MEAL_TYPES } from '@/lib/meals';
import RecipePicker from '@/components/RecipePicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import DateField from '@/components/ui/DateField.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import SelectField from '@/components/ui/SelectField.vue';

/**
 * Schedules a recipe for a meal, or changes a planned one: another
 * recipe, another day or meal, more or fewer servings. Recipe and day can
 * be preset by the place the dialog is opened from.
 */
const open = defineModel<boolean>('open', { required: true });

const props = withDefaults(
    defineProps<{ date: string; recipe?: RecipeSummary | null; entry?: MealPlanEntry | null }>(),
    { recipe: null, entry: null },
);

const { t } = useI18n();
const { showError, showSuccess } = useToasts();
const queryClient = useQueryClient();
const { defaultServings } = useHouseholdSettings();

const selectedRecipe = ref<RecipeSummary | null>(null);
const selectedDate = ref<string | null>(null);
const meal = ref<MealType | null>('dinner');
const servings = ref<number | null>(defaultServings.value);

const mealOptions = computed(() => MEAL_TYPES.map((value) => ({ value, label: t(`meals.${value}`) })));

watch(open, (isOpen) => {
    if (isOpen) {
        selectedRecipe.value = props.entry?.recipe ?? props.recipe;
        selectedDate.value = props.entry?.date ?? props.date;
        meal.value = props.entry?.meal ?? 'dinner';
        servings.value = props.entry?.servings ?? defaultServings.value;
    }
});

const save = useMutation({
    mutationFn: () => {
        const values = {
            date: selectedDate.value ?? props.date,
            meal: meal.value ?? 'dinner',
            recipe_id: selectedRecipe.value?.id ?? 0,
            servings: servings.value ?? defaultServings.value,
        };

        return props.entry ? mealPlanApi.update(props.entry.id, values) : mealPlanApi.create(values);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['meal-plan'] });
        queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
        showSuccess(t(props.entry ? 'plan.changed' : 'plan.added'));
        open.value = false;
    },
    onError: showError,
});
</script>

<template>
    <DialogPanel
        v-model:open="open"
        :title="t(entry ? 'plan.editEntry' : 'plan.addEntry')"
        :close-label="t('common.close')"
    >
        <RecipePicker v-if="!recipe" v-model="selectedRecipe" :label="t('plan.recipe')" />
        <DateField v-model="selectedDate" :label="t('plan.date')" />
        <SelectField v-model="meal" :options="mealOptions" :label="t('plan.meal')" />
        <NumberField v-model="servings" :label="t('recipes.servings')" :min="1" :max="50" steppers />
        <template #actions>
            <AppButton
                variant="primary"
                :disabled="selectedRecipe === null || save.isPending.value"
                @click="save.mutate()"
            >
                {{ t('common.save') }}
            </AppButton>
        </template>
    </DialogPanel>
</template>
