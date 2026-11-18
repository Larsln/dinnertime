import { computed, type ComputedRef } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { householdSettingsApi } from '@/api/settings';

export const HOUSEHOLD_SETTINGS_KEY = ['household-settings'];

const FALLBACK_SERVINGS = 2;

/**
 * What the household decided once for everyday use, such as the number of
 * servings a meal is planned for unless another number is chosen.
 */
export function useHouseholdSettings(): { defaultServings: ComputedRef<number> } {
    const settings = useQuery({
        queryKey: HOUSEHOLD_SETTINGS_KEY,
        queryFn: householdSettingsApi.get,
        staleTime: Infinity,
    });

    return {
        defaultServings: computed(() => settings.data.value?.default_servings ?? FALLBACK_SERVINGS),
    };
}
