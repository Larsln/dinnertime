import { api, data } from './client';
import type { Store } from './types';

export const storesApi = {
    list: () => data(api.get<{ data: Store[] }>('/stores')),
    create: (name: string) => data(api.post<{ data: Store }>('/stores', { name })),
    update: (id: number, changes: { name?: string; is_active?: boolean; is_preferred?: boolean }) =>
        data(api.patch<{ data: Store }>(`/stores/${id}`, changes)),
    remove: (id: number) => api.delete(`/stores/${id}`),
};
