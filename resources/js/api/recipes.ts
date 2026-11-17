import { api, data } from './client';
import type { Paginated, Recipe, RecipeImport, RecipeInput, RecipeSummary } from './types';

export const recipesApi = {
    list: (filters: { search?: string; tag?: number | null; page?: number }) =>
        api.get<Paginated<RecipeSummary>>('/recipes', filters),
    get: (id: number) => data(api.get<{ data: Recipe }>(`/recipes/${id}`)),
    create: (input: RecipeInput) => data(api.post<{ data: Recipe }>('/recipes', input)),
    update: (id: number, input: Partial<RecipeInput>) =>
        data(api.patch<{ data: Recipe }>(`/recipes/${id}`, input)),
    remove: (id: number) => api.delete(`/recipes/${id}`),
    uploadCover: (id: number, image: File) => {
        const form = new FormData();
        form.set('image', image);

        return data(api.upload<{ data: Recipe }>(`/recipes/${id}/cover`, form));
    },
};

export const importsApi = {
    list: () => data(api.get<{ data: RecipeImport[] }>('/imports')),
    create: (url: string) => data(api.post<{ data: RecipeImport }>('/imports', { url })),
    retry: (id: number) => data(api.post<{ data: RecipeImport }>(`/imports/${id}/retry`)),
};
