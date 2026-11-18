import type { CourseKey, MealType, RecipeSummary } from '@/api/types';
import type { PlanSlot } from './planSlots';

/**
 * What a suggested plan should favor. Mirrors the server's preferences.
 */
export interface PlanPreferences {
    use_leftovers: boolean;
    quick_on_weekdays: boolean;
    prefer_not_recent: boolean;
    prefer_favorites: boolean;
    variety: boolean;
}

/**
 * Recipe the server suggests for a slot.
 */
export interface Suggestion {
    date: string;
    meal: MealType;
    recipe: RecipeSummary;
    kept: boolean;
}

/**
 * Suggestion as shown in the preview, where the user can lock it.
 */
export interface PreviewRow extends Suggestion {
    locked: boolean;
}

export interface SuggestBody {
    from: string;
    to: string;
    slots: PlanSlot[];
    servings: number;
    preferences: PlanPreferences;
    tag_ids: number[];
    courses: CourseKey[];
    keep: SlotPin[];
    avoid: SlotPin[];
}

interface SlotPin extends PlanSlot {
    recipe_id: number;
}

export const PREFERENCE_KEYS: (keyof PlanPreferences)[] = [
    'use_leftovers',
    'quick_on_weekdays',
    'prefer_not_recent',
    'prefer_favorites',
    'variety',
];

export function defaultPreferences(): PlanPreferences {
    return {
        use_leftovers: true,
        quick_on_weekdays: false,
        prefer_not_recent: true,
        prefer_favorites: false,
        variety: true,
    };
}

function pin(row: Suggestion): SlotPin {
    return { date: row.date, meal: row.meal, recipe_id: row.recipe.id };
}

/**
 * Pins for suggesting everything anew except what the user locked.
 */
export function pinsForRerollAll(rows: PreviewRow[]): Pick<SuggestBody, 'keep' | 'avoid'> {
    return { keep: rows.filter((row) => row.locked).map(pin), avoid: [] };
}

/**
 * Pins for replacing one row: all others stay and the row's current
 * recipe is not suggested again.
 */
export function pinsForReroll(rows: PreviewRow[], target: PreviewRow): Pick<SuggestBody, 'keep' | 'avoid'> {
    return { keep: rows.filter((row) => row !== target).map(pin), avoid: [pin(target)] };
}

/**
 * Take over a new suggestion while remembering which rows are locked.
 */
export function mergeSuggestion(previous: PreviewRow[], suggestion: Suggestion[]): PreviewRow[] {
    const locked = new Set(previous.filter((row) => row.locked).map((row) => `${row.date}|${row.meal}`));

    return suggestion.map((row) => ({ ...row, locked: locked.has(`${row.date}|${row.meal}`) }));
}
