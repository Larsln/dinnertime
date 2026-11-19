import { formatNumber } from './format';
import { formatKitchenQuantity } from './kitchenQuantity';

/**
 * Part of a step text: plain wording or an ingredient amount that scales
 * with the servings.
 */
export interface StepSegment {
    text: string;
    isAmount: boolean;
}

const FRACTION_LIMIT = 20;
const MARKER = /\{(\d+(?:[.,]\d+)?|\d+\/\d+)\}/g;

/**
 * Value of a marked amount, written as decimal number or simple fraction.
 */
function amountValue(written: string): number | null {
    const [numerator, denominator] = written.split('/');

    if (denominator === undefined) {
        return Number(written.replace(',', '.'));
    }

    return Number(denominator) === 0 ? null : Number(numerator) / Number(denominator);
}

/**
 * Write a scaled amount with kitchen fractions. Large amounts, which are
 * usually grams or milliliters, stay decimal.
 */
function amountText(value: number, locale: string): string {
    return value >= FRACTION_LIMIT ? formatNumber(value, locale) : formatKitchenQuantity(value, locale);
}

/**
 * Split a step text at its amounts, written as numbers in curly braces
 * like "{250} g flour", and scale them by a factor.
 */
export function scaledStepSegments(text: string, scale: number, locale: string): StepSegment[] {
    const segments: StepSegment[] = [];
    let end = 0;

    for (const match of text.matchAll(MARKER)) {
        const value = amountValue(match[1] ?? '');

        if (value === null) {
            continue;
        }

        if (match.index > end) {
            segments.push({ text: text.slice(end, match.index), isAmount: false });
        }

        segments.push({ text: amountText(value * scale, locale), isAmount: true });
        end = match.index + match[0].length;
    }

    if (end < text.length) {
        segments.push({ text: text.slice(end), isAmount: false });
    }

    return segments;
}
