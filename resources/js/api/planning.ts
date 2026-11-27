import { api, data } from './client';
import type { SuggestBody, Suggestion } from '@/lib/planWizard';
import type {
    MealPlanEntry,
    MealType,
    PantryItem,
    PlanReason,
    PortionVerdict,
    ShoppingList,
    ShoppingListItem,
} from './types';

export interface Period {
    from: string;
    to: string;
}

interface Servings {
    servings: number;
}

/**
 * Who eats a meal: the members who join and a number of guests.
 */
interface Eaters {
    eater_ids: number[];
    guests: number;
}

export const mealPlanApi = {
    list: (period: Period) => data(api.get<{ data: MealPlanEntry[] }>('/meal-plan', { ...period })),
    create: (entry: { date: string; meal: MealType; recipe_id: number } & (Servings | Eaters)) =>
        data(api.post<{ data: MealPlanEntry }>('/meal-plan', entry)),
    update: (
        id: number,
        changes: { date?: string; meal?: MealType; recipe_id?: number; cooked?: boolean } & Partial<
            Servings & Eaters
        >,
    ) => data(api.patch<{ data: MealPlanEntry }>(`/meal-plan/${id}`, changes)),
    setPortionFeedback: (id: number, verdict: PortionVerdict | null) =>
        data(api.put<{ data: MealPlanEntry }>(`/meal-plan/${id}/portion-feedback`, { verdict })),
    remove: (id: number) => api.delete(`/meal-plan/${id}`),
    shift: (from: string, days: number) =>
        data(api.post<{ data: MealPlanEntry[] }>('/meal-plan/shift', { from, days })),
    reasons: (period: Period) =>
        data(
            api.get<{ data: { entry_id: number; reasons: PlanReason[] }[] }>('/meal-plan/reasons', {
                ...period,
            }),
        ),
    suggest: (body: SuggestBody) =>
        api.post<{ data: Suggestion[]; meta: { candidate_count: number } }>('/meal-plan/suggest', body),
    saveBatch: (entries: { date: string; meal: MealType; recipe_id: number; servings: number }[]) =>
        data(api.post<{ data: MealPlanEntry[] }>('/meal-plan/batch', { entries })),
};

export const pantryApi = {
    list: () => data(api.get<{ data: PantryItem[] }>('/pantry')),
    create: (item: { product_id: number; amount: number; opened_on?: string | null }) =>
        data(api.post<{ data: PantryItem }>('/pantry', item)),
    update: (id: number, changes: { amount: number }) =>
        data(api.patch<{ data: PantryItem }>(`/pantry/${id}`, changes)),
    remove: (id: number) => api.delete(`/pantry/${id}`),
};

export const shoppingApi = {
    current: () => data(api.get<{ data: ShoppingList }>('/shopping-lists/current')),
    sync: (period: Period) => data(api.post<{ data: ShoppingList }>('/shopping-lists', period)),
    stockLeftovers: (id: number) =>
        data(api.post<{ data: ShoppingList }>(`/shopping-lists/${id}/stock-leftovers`)),
    addItem: (listId: number, entry: { label: string } | { product_id: number }) =>
        data(api.post<{ data: ShoppingListItem }>(`/shopping-lists/${listId}/items`, entry)),
    setChecked: (itemId: number, checked: boolean) =>
        data(api.patch<{ data: ShoppingListItem }>(`/shopping-list-items/${itemId}`, { checked })),
    setSkipped: (itemId: number, skipped: boolean) =>
        data(api.patch<{ data: ShoppingListItem }>(`/shopping-list-items/${itemId}`, { skipped })),
    substitute: (
        itemId: number,
        substitute: { substitute_product_id: number } | { substitute_label: string },
    ) =>
        data(
            api.patch<{ data: ShoppingListItem }>(`/shopping-list-items/${itemId}`, {
                checked: true,
                ...substitute,
            }),
        ),
    removeItem: (itemId: number) => api.delete(`/shopping-list-items/${itemId}`),
};
