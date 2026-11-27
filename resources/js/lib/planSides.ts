import type { MealPlanEntry } from '@/api/types';

/**
 * The sides among planned entries, grouped by the meal they are served
 * with. A side whose meal is not among the entries is left out.
 */
export function sidesByMeal(entries: MealPlanEntry[]): Map<number, MealPlanEntry[]> {
    const ids = new Set(entries.map((entry) => entry.id));
    const groups = new Map<number, MealPlanEntry[]>();

    for (const entry of entries) {
        if (entry.parent_id !== null && ids.has(entry.parent_id)) {
            groups.set(entry.parent_id, [...(groups.get(entry.parent_id) ?? []), entry]);
        }
    }

    return groups;
}

/**
 * The planned entries that stand for themselves: every meal, and a side
 * whose meal is not among the entries.
 */
export function mealsOf(entries: MealPlanEntry[]): MealPlanEntry[] {
    const ids = new Set(entries.map((entry) => entry.id));

    return entries.filter((entry) => entry.parent_id === null || !ids.has(entry.parent_id));
}
