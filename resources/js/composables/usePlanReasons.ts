import { useI18n } from 'vue-i18n';
import type { PlanReason } from '@/api/types';
import { fromIsoDate } from '@/lib/dates';
import { formatNumber } from '@/lib/format';

/**
 * Puts the reasons a meal goes with the rest of the plan into words.
 */
export function usePlanReasons() {
    const { t, locale } = useI18n();

    function list(names: string[]): string {
        return new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(names);
    }

    function weekday(date: string): string {
        return new Intl.DateTimeFormat(locale.value, { weekday: 'short' }).format(fromIsoDate(date));
    }

    function describe(reason: PlanReason): string {
        switch (reason.type) {
            case 'shares':
                return t('plan.reasons.shares', {
                    products: list(reason.products),
                    meals: list(reason.with.map((meal) => `${meal.title} (${weekday(meal.date)})`)),
                });
            case 'pantry':
                return t('plan.reasons.pantry', { products: list(reason.products) });
            case 'favorite':
                return t('plan.reasons.favorite');
            case 'rating':
                return t('plan.reasons.rating', { value: formatNumber(reason.value / 2, locale.value, 1) });
            case 'last_planned':
                return t('plan.reasons.lastPlanned', { count: reason.days });
            case 'not_recent':
                return t('plan.reasons.notRecent');
            case 'quick':
                return reason.minutes === null
                    ? t('plan.reasons.quick')
                    : t('plan.reasons.quickMinutes', { count: reason.minutes });
        }
    }

    return { describe };
}
