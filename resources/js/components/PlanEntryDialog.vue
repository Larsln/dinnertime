<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { mealPlanApi } from '@/api/planning';
import type { MealPlanEntry, MealType, RecipeSummary } from '@/api/types';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useToasts } from '@/composables/useToasts';
import { MEAL_TYPES } from '@/lib/meals';
import PlanEatersField from '@/components/PlanEatersField.vue';
import RecipePicker from '@/components/RecipePicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import DateField from '@/components/ui/DateField.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import SelectField from '@/components/ui/SelectField.vue';

/**
 * Schedules a recipe for a meal, or changes a planned one: another
 * recipe, another day or meal, other people who eat. Recipe and day can
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
const { defaultServings, members } = useHouseholdSettings();

const selectedRecipe = ref<RecipeSummary | null>(null);
const selectedDate = ref<string | null>(null);
const meal = ref<MealType | null>('dinner');
const eaterIds = ref<number[]>([]);
const guests = ref<number | null>(0);

const headCount = computed(() => eaterIds.value.length + (guests.value ?? 0));

const mealOptions = computed(() => MEAL_TYPES.map((value) => ({ value, label: t(`meals.${value}`) })));

watch(open, (isOpen) => {
    if (isOpen) {
        selectedRecipe.value = props.entry?.recipe ?? props.recipe;
        selectedDate.value = props.entry?.date ?? props.date;
        meal.value = props.entry?.meal ?? 'dinner';
        eaterIds.value = props.entry
            ? (props.entry.eaters ?? []).map((eater) => eater.user_id)
            : members.value.map((member) => member.id);
        guests.value = props.entry
            ? props.entry.eaters === null
                ? props.entry.servings
                : props.entry.guests
            : Math.max(0, defaultServings.value - members.value.length);
    }
});

const save = useMutation({
    mutationFn: () => {
        const values = {
            date: selectedDate.value ?? props.date,
            meal: meal.value ?? 'dinner',
            recipe_id: selectedRecipe.value?.id ?? 0,
            eater_ids: eaterIds.value,
            guests: guests.value ?? 0,
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
        <PlanEatersField v-model:eater-ids="eaterIds" v-model:guests="guests" :members="members" />
        <template #actions>
            <AppButton
                variant="primary"
                :disabled="selectedRecipe === null || headCount < 1 || save.isPending.value"
                @click="save.mutate()"
            >
                {{ t('common.save') }}
            </AppButton>
        </template>
    </DialogPanel>
</template>
