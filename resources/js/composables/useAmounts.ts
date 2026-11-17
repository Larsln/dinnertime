import { useI18n } from 'vue-i18n';
import type { BaseUnit } from '@/api/types';
import { formatAmount } from '@/lib/format';

/**
 * Formats product amounts in the interface language.
 */
export function useAmounts() {
    const { t, locale } = useI18n();

    return {
        amount: (value: number, baseUnit: BaseUnit) =>
            formatAmount(value, baseUnit, locale.value, t('units.piece')),
    };
}
