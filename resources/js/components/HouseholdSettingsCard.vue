<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { householdSettingsApi } from '@/api/settings';
import { HOUSEHOLD_SETTINGS_KEY, useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useToasts } from '@/composables/useToasts';
import NumberField from '@/components/ui/NumberField.vue';
import SwitchField from '@/components/ui/SwitchField.vue';

/**
 * The number of servings meals are planned and shopped for by default,
 * and whether the shopping list notes what is at home already.
 */
const { t } = useI18n();
const queryClient = useQueryClient();
const { showError } = useToasts();
const { defaultServings, showsStockedNeeds } = useHouseholdSettings();

const save = useMutation({
    mutationFn: householdSettingsApi.setDefaultServings,
    onSuccess: (updated) => queryClient.setQueryData(HOUSEHOLD_SETTINGS_KEY, updated),
    onError: showError,
});

const servings = computed({
    get: (): number | null => defaultServings.value,
    set: (value: number | null) => {
        if (value) {
            save.mutate(value);
        }
    },
});

const saveStocked = useMutation({
    mutationFn: householdSettingsApi.setShowsStockedNeeds,
    onSuccess: (updated) => {
        queryClient.setQueryData(HOUSEHOLD_SETTINGS_KEY, updated);
        queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
    },
    onError: showError,
});

const stocked = computed({
    get: (): boolean => showsStockedNeeds.value,
    set: (value: boolean) => saveStocked.mutate(value),
});
</script>

<template>
    <section class="rounded-card border border-line bg-surface p-4">
        <h2 class="mb-2 font-display text-lg font-bold">{{ t('settings.household.title') }}</h2>
        <NumberField
            v-model="servings"
            :label="t('settings.household.servings')"
            :min="1"
            :max="50"
            steppers
        />
        <p class="mt-2 text-sm text-ink-muted">{{ t('settings.household.servingsHint') }}</p>
        <SwitchField
            v-model="stocked"
            class="mt-2"
            :label="t('settings.household.stocked')"
            :hint="t('settings.household.stockedHint')"
        />
    </section>
</template>
