/**
 * How a quantity is written: `kitchen` prefers fractions such as ½,
 * `metric` always uses decimals.
 */
export type QuantityStyle = 'kitchen' | 'metric';

const TOLERANCE = 0.02;
const SEPARATOR = ' ';

const FRACTIONS: readonly { value: number; glyph: string }[] = [
    { value: 1 / 2, glyph: '½' },
    { value: 1 / 3, glyph: '⅓' },
    { value: 2 / 3, glyph: '⅔' },
    { value: 1 / 4, glyph: '¼' },
    { value: 3 / 4, glyph: '¾' },
    { value: 1 / 8, glyph: '⅛' },
    { value: 3 / 8, glyph: '⅜' },
    { value: 5 / 8, glyph: '⅝' },
    { value: 7 / 8, glyph: '⅞' },
];

const GLYPHS = FRACTIONS.map((fraction) => fraction.glyph).join('');
const GLYPH_PATTERN = new RegExp(`^(\\d+)?\\s*([${GLYPHS}])$`);
const FRACTION_PATTERN = /^(?:(\d+)\s+)?(\d+)\/(\d+)$/;
const DECIMAL_PATTERN = /^\d+(?:[.,]\d+)?$/;

/**
 * Whole number or fraction glyph for a value, or null when the value is
 * neither whole nor close to a known fraction.
 */
function fractionText(value: number, locale: string): string | null {
    if (!Number.isFinite(value) || value < 0) {
        return null;
    }

    const whole = Math.floor(value + TOLERANCE);
    const rest = value - whole;
    const wholeText = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(whole);

    if (Math.abs(rest) < TOLERANCE) {
        return wholeText;
    }

    const fraction = FRACTIONS.find((candidate) => Math.abs(rest - candidate.value) < TOLERANCE);

    if (fraction === undefined) {
        return null;
    }

    return whole === 0 ? fraction.glyph : `${wholeText}${SEPARATOR}${fraction.glyph}`;
}

/**
 * Format a quantity the way cooks read it: whole numbers and common
 * fractions as glyphs such as "1 ½", anything else as a decimal with at
 * most two fraction digits. The metric style never uses fractions.
 */
export function formatKitchenQuantity(
    value: number,
    locale: string,
    style: QuantityStyle = 'kitchen',
): string {
    const fraction = style === 'kitchen' ? fractionText(value, locale) : null;

    return fraction ?? new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(value);
}

/**
 * Format a quantity for an input field: like the kitchen format, but
 * decimals carry no grouping separators and up to three fraction digits,
 * so that the text reads back as the same number.
 */
export function formatEditableQuantity(
    value: number,
    locale: string,
    style: QuantityStyle = 'kitchen',
): string {
    const fraction = style === 'kitchen' && value < 1000 ? fractionText(value, locale) : null;

    return (
        fraction ??
        new Intl.NumberFormat(locale, { maximumFractionDigits: 3, useGrouping: false }).format(value)
    );
}

/**
 * Read a quantity as typed by a cook: "1/2", "1 1/2", "½", "1½", "1,5",
 * "0.25" or "3". Returns null for empty, negative or unreadable input
 * and for a zero denominator.
 */
export function parseKitchenQuantity(text: string): number | null {
    const trimmed = text.trim();

    const glyph = GLYPH_PATTERN.exec(trimmed);

    if (glyph) {
        const fraction = FRACTIONS.find((candidate) => candidate.glyph === glyph[2]);

        return Number(glyph[1] ?? 0) + (fraction?.value ?? 0);
    }

    const written = FRACTION_PATTERN.exec(trimmed);

    if (written) {
        const denominator = Number(written[3]);

        return denominator === 0 ? null : Number(written[1] ?? 0) + Number(written[2]) / denominator;
    }

    return DECIMAL_PATTERN.test(trimmed) ? Number(trimmed.replace(',', '.')) : null;
}
