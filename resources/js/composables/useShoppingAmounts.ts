import { useI18n } from 'vue-i18n';
import type { BaseUnit, ShoppingAmounts } from '@/api/types';
import { formatNumber } from '@/lib/format';
import { useAmounts } from './useAmounts';

/**
 * Puts what to buy of a product into words, short for a tile or in full
 * for the details.
 */
export function useShoppingAmounts() {
    const { locale } = useI18n();
    const { amount } = useAmounts();

    function extras(amounts: ShoppingAmounts): string[] {
        return amounts.extra_amounts.map(
            (extra) => `${formatNumber(extra.quantity, locale.value)} ${extra.unit_code}`,
        );
    }

    function total(amounts: ShoppingAmounts, baseUnit: BaseUnit): string {
        return amounts.purchase_amount !== null ? amount(amounts.purchase_amount, baseUnit) : '';
    }

    /**
     * Count, store and size of a package. The store is left out for sizes
     * sold everywhere, the size for single pieces of a store, where it adds
     * nothing to the name.
     */
    function describePackage(entry: ShoppingAmounts['packages'][number], baseUnit: BaseUnit): string {
        const label = entry.label.trim();
        const isSinglePiece = baseUnit === 'piece' && entry.amount === 1;
        const size = label !== '' && isSinglePiece ? '' : amount(entry.amount, baseUnit);

        return [`${entry.count} ×`, label, size].filter((part) => part !== '').join(' ');
    }

    /**
     * The amount in as few characters as possible.
     */
    function short(amounts: ShoppingAmounts, baseUnit: BaseUnit): string {
        const only = amounts.packages.length === 1 ? amounts.packages[0] : undefined;
        const packed =
            only && only.count > 1
                ? `${only.count} × ${amount(only.amount, baseUnit)}`
                : total(amounts, baseUnit);

        return [...(packed ? [packed] : []), ...extras(amounts)].join(' + ');
    }

    /**
     * What to buy in full: every package with its size, plus needs in
     * units that cannot be converted.
     */
    function full(amounts: ShoppingAmounts, baseUnit: BaseUnit): string {
        const packages = amounts.packages.map((entry) => describePackage(entry, baseUnit));
        const fallback = total(amounts, baseUnit);

        return [...(packages.length > 0 ? packages : fallback ? [fallback] : []), ...extras(amounts)].join(
            ' + ',
        );
    }

    return { short, full };
}
