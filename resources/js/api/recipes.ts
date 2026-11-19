import { api, data } from './client';
import type { CourseKey, Paginated, Recipe, RecipeImport, RecipeInput, RecipeSummary } from './types';

export const recipesApi = {
    list: (filters: {
        search?: string;
        tags?: number[];
        courses?: CourseKey[];
        favorite?: 1 | null;
        page?: number;
    }) => api.get<Paginated<RecipeSummary>>('/recipes', filters),
    get: (id: number) => data(api.get<{ data: Recipe }>(`/recipes/${id}`)),
    create: (input: RecipeInput) => data(api.post<{ data: Recipe }>('/recipes', input)),
    update: (id: number, input: Partial<RecipeInput>) =>
        data(api.patch<{ data: Recipe }>(`/recipes/${id}`, input)),
    remove: (id: number) => api.delete(`/recipes/${id}`),
    rate: (id: number, rating: number | null) =>
        data(api.put<{ data: RecipeSummary }>(`/recipes/${id}/rating`, { rating })),
    setFavorite: (id: number, favorite: boolean) =>
        data(api.put<{ data: RecipeSummary }>(`/recipes/${id}/favorite`, { favorite })),
    uploadCover: (id: number, image: File) => {
        const form = new FormData();
        form.set('image', image);

        return data(api.upload<{ data: Recipe }>(`/recipes/${id}/cover`, form));
    },
};

export const importsApi = {
    list: () => data(api.get<{ data: RecipeImport[] }>('/imports')),
    create: (url: string, dishCount: number | null) =>
        data(api.post<{ data: RecipeImport }>('/imports', { url, dish_count: dishCount })),
    retry: (id: number) => data(api.post<{ data: RecipeImport }>(`/imports/${id}/retry`)),
};
