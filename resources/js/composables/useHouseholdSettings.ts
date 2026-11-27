import { computed, type ComputedRef } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { householdSettingsApi, type HouseholdMember } from '@/api/settings';

export const HOUSEHOLD_SETTINGS_KEY = ['household-settings'];

const FALLBACK_SERVINGS = 2;
const FALLBACK_CURRENCY = 'EUR';

/**
 * What the household decided once for everyday use, such as the number of
 * servings a meal is planned for unless another number is chosen, the
 * currency prices are noted in and whether the shopping list notes what
 * is at home already.
 */
export function useHouseholdSettings(): {
    defaultServings: ComputedRef<number>;
    currency: ComputedRef<string>;
    showsStockedNeeds: ComputedRef<boolean>;
    members: ComputedRef<HouseholdMember[]>;
} {
    const settings = useQuery({
        queryKey: HOUSEHOLD_SETTINGS_KEY,
        queryFn: householdSettingsApi.get,
        staleTime: Infinity,
    });

    return {
        defaultServings: computed(() => settings.data.value?.default_servings ?? FALLBACK_SERVINGS),
        currency: computed(() => settings.data.value?.currency ?? FALLBACK_CURRENCY),
        showsStockedNeeds: computed(() => settings.data.value?.show_stocked_needs ?? false),
        members: computed(() => settings.data.value?.members ?? []),
    };
}
