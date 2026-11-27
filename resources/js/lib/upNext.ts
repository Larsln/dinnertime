import type { MealPlanEntry } from '@/api/types';
import { MEAL_TYPES } from './meals';
import { mealsOf } from './planSides';

/**
 * The meal to cook next: the first planned meal from the given day on
 * that is not cooked yet, earlier days first and the meals of a day in the
 * order they are eaten. Sides are not meals of their own.
 */
export function nextMeal(entries: MealPlanEntry[], today: string): MealPlanEntry | null {
    const open = mealsOf(entries)
        .filter((entry) => entry.cooked_at === null && entry.date >= today)
        .sort(
            (a, b) => a.date.localeCompare(b.date) || MEAL_TYPES.indexOf(a.meal) - MEAL_TYPES.indexOf(b.meal),
        );

    return open[0] ?? null;
}

/**
 * Whether anything is planned for the day, cooked or not.
 */
export function isPlanned(entries: MealPlanEntry[], day: string): boolean {
    return entries.some((entry) => entry.date === day);
}
