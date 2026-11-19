import type { BaseUnit, Ingredient, ProductOffer } from '@/api/types';

const LARGE_UNIT_THRESHOLD = 1000;

const BASE_UNIT_LABELS: Record<Exclude<BaseUnit, 'piece'>, { small: string; large: string }> = {
    g: { small: 'g', large: 'kg' },
    ml: { small: 'ml', large: 'l' },
};

/**
 * Format a number with at most two decimals in the given locale.
 */
export function formatNumber(value: number, locale: string, maximumFractionDigits = 2): string {
    return new Intl.NumberFormat(locale, { maximumFractionDigits }).format(value);
}

/**
 * Format an amount given in a product's base unit, switching to kilograms
 * or liters from a thousand upwards.
 *
 * @param pieceLabel Label appended to amounts counted in pieces.
 */
export function formatAmount(amount: number, baseUnit: BaseUnit, locale: string, pieceLabel: string): string {
    if (baseUnit === 'piece') {
        return `${formatNumber(amount, locale)} ${pieceLabel}`;
    }

    const labels = BASE_UNIT_LABELS[baseUnit];

    return amount >= LARGE_UNIT_THRESHOLD
        ? `${formatNumber(amount / LARGE_UNIT_THRESHOLD, locale)} ${labels.large}`
        : `${formatNumber(amount, locale)} ${labels.small}`;
}

/**
 * Store, package size and price of an offer in one line, leaving out
 * what is not known.
 */
export function formatOffer(
    offer: ProductOffer,
    storeName: string,
    locale: string,
    currency: string,
    pieceLabel: string,
): string {
    const unit = offer.unit === 'piece' ? pieceLabel : (offer.unit ?? '');

    return [
        storeName,
        offer.amount === null ? null : `${formatNumber(offer.amount, locale)} ${unit}`.trim(),
        offer.price === null
            ? null
            : new Intl.NumberFormat(locale, { style: 'currency', currency }).format(offer.price),
    ]
        .filter((part) => part !== null)
        .join(' · ');
}

/**
 * Quantity and unit of an ingredient line, scaled by a factor.
 */
export function formatQuantity(ingredient: Ingredient, scale: number, locale: string): string {
    if (ingredient.quantity === null) {
        return '';
    }

    const quantity = formatNumber(ingredient.quantity * scale, locale);

    return ingredient.unit ? `${quantity} ${ingredient.unit.code}` : quantity;
}

/**
 * Format a `YYYY-MM-DD` date in full, e.g. "Wednesday, 18 November".
 */
export function formatLongDay(isoDate: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(
        new Date(`${isoDate}T00:00:00`),
    );
}

/**
 * Format a `YYYY-MM-DD` date as weekday and day, e.g. "Mon, 16 Nov".
 */
export function formatDay(isoDate: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'short' }).format(
        new Date(`${isoDate}T00:00:00`),
    );
}
