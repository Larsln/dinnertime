<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { householdSettingsApi } from '@/api/settings';
import { HOUSEHOLD_SETTINGS_KEY, useHouseholdSettings } from '@/composables/useHouseholdSettings';
import { useToasts } from '@/composables/useToasts';
import NumberField from '@/components/ui/NumberField.vue';

/**
 * The number of servings meals are planned and shopped for by default.
 */
const { t } = useI18n();
const queryClient = useQueryClient();
const { showError } = useToasts();
const { defaultServings } = useHouseholdSettings();

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
</script>

<template>
    <section class="rounded-2xl border border-stone-200 bg-white p-4">
        <h2 class="mb-2 font-semibold">{{ t('settings.household.title') }}</h2>
        <NumberField
            v-model="servings"
            :label="t('settings.household.servings')"
            :min="1"
            :max="50"
            steppers
        />
        <p class="mt-2 text-sm text-stone-500">{{ t('settings.household.servingsHint') }}</p>
    </section>
</template>
