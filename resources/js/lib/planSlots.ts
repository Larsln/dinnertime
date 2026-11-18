import type { MealType } from '@/api/types';
import { fromIsoDate } from './dates';

/**
 * One meal on one day that the generator can fill.
 */
export interface PlanSlot {
    date: string;
    meal: MealType;
}

export type DayPreset = 'all' | 'weekdays' | 'weekend';

const SATURDAY = 6;
const SUNDAY = 0;

/**
 * Key that identifies a slot in a set.
 */
export function slotKey(slot: PlanSlot): string {
    return `${slot.date}|${slot.meal}`;
}

/**
 * Whether a day belongs to a preset of days.
 */
export function matchesDayPreset(date: string, preset: DayPreset): boolean {
    const weekday = fromIsoDate(date).getDay();
    const isWeekend = weekday === SATURDAY || weekday === SUNDAY;

    return preset === 'all' || (preset === 'weekend') === isWeekend;
}

/**
 * The slots a combination of day and meal presets selects, leaving out
 * the ones that are planned already.
 */
export function presetSlots(
    days: string[],
    preset: DayPreset,
    meals: MealType[],
    planned: Set<string>,
): PlanSlot[] {
    return days
        .filter((date) => matchesDayPreset(date, preset))
        .flatMap((date) => meals.map((meal) => ({ date, meal })))
        .filter((slot) => !planned.has(slotKey(slot)));
}
