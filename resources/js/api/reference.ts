import { api, data } from './client';
import type { StoreSection, Tag, Unit } from './types';

export const referenceApi = {
    units: () => data(api.get<{ data: Unit[] }>('/units')),
    storeSections: () => data(api.get<{ data: StoreSection[] }>('/store-sections')),
    tags: () => data(api.get<{ data: Tag[] }>('/tags')),
};
