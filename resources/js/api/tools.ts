import { api, data } from './client';
import type { Tool } from './types';

export const toolsApi = {
    list: (search?: string) => data(api.get<{ data: Tool[] }>('/tools', { search })),
    create: (name: string) => data(api.post<{ data: Tool }>('/tools', { name })),
    update: (id: number, changes: { name?: string; is_owned?: boolean }) =>
        data(api.patch<{ data: Tool }>(`/tools/${id}`, changes)),
    remove: (id: number) => api.delete(`/tools/${id}`),
    merge: (id: number, into: number) => data(api.post<{ data: Tool }>(`/tools/${id}/merge`, { into })),
};
