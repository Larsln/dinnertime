import { api, data } from './client';
import type { SuggestBody, Suggestion } from '@/lib/planWizard';
import type { MealPlanEntry, MealType, PantryItem, ShoppingList, ShoppingListItem } from './types';

export interface Period {
    from: string;
    to: string;
}

export const mealPlanApi = {
    list: (period: Period) => data(api.get<{ data: MealPlanEntry[] }>('/meal-plan', { ...period })),
    create: (entry: { date: string; meal: MealType; recipe_id: number; servings: number }) =>
        data(api.post<{ data: MealPlanEntry }>('/meal-plan', entry)),
    update: (id: number, changes: { servings?: number; cooked?: boolean }) =>
        data(api.patch<{ data: MealPlanEntry }>(`/meal-plan/${id}`, changes)),
    remove: (id: number) => api.delete(`/meal-plan/${id}`),
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
    addItem: (listId: number, label: string) =>
        data(api.post<{ data: ShoppingListItem }>(`/shopping-lists/${listId}/items`, { label })),
    setChecked: (itemId: number, checked: boolean) =>
        data(api.patch<{ data: ShoppingListItem }>(`/shopping-list-items/${itemId}`, { checked })),
    removeItem: (itemId: number) => api.delete(`/shopping-list-items/${itemId}`),
};
