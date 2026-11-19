import { api, data } from './client';
import type { DinnerRound, DinnerRoundOptions, MealPlanEntry } from './types';

export const dinnerApi = {
    current: () => data(api.get<{ data: DinnerRound | null }>('/dinner-rounds/current')),
    start: (options: DinnerRoundOptions) => data(api.post<{ data: DinnerRound }>('/dinner-rounds', options)),
    vote: (id: number, recipeId: number, liked: boolean) =>
        data(api.post<{ data: DinnerRound }>(`/dinner-rounds/${id}/votes`, { recipe_id: recipeId, liked })),
    plan: (id: number) => data(api.post<{ data: MealPlanEntry }>(`/dinner-rounds/${id}/plan`)),
    close: (id: number) => api.delete(`/dinner-rounds/${id}`),
};
