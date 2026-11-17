const MILLISECONDS_PER_DAY = 86_400_000;

/**
 * Format a date as the `YYYY-MM-DD` string the API expects, in local time.
 */
export function toIsoDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * Parse a `YYYY-MM-DD` string as a local date.
 */
export function fromIsoDate(value: string): Date {
    const [year = 1970, month = 1, day = 1] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function addDays(date: Date, days: number): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

/**
 * Monday of the week the date lies in.
 */
export function startOfWeek(date: Date): Date {
    return addDays(date, -((date.getDay() + 6) % 7));
}

/**
 * The seven days of the week starting at the given Monday.
 */
export function weekDays(monday: Date): Date[] {
    return Array.from({ length: 7 }, (_, index) => addDays(monday, index));
}

/**
 * Whole days from today until the date, negative when it lies in the past.
 */
export function daysUntil(isoDate: string): number {
    const today = fromIsoDate(toIsoDate(new Date()));

    return Math.round((fromIsoDate(isoDate).getTime() - today.getTime()) / MILLISECONDS_PER_DAY);
}
