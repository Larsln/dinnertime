import { api, data } from './client';
import type { Course, SideKind, StoreSection, Tag, Unit } from './types';

export const referenceApi = {
    courses: () => data(api.get<{ data: Course[] }>('/courses')),
    sideKinds: () => data(api.get<{ data: SideKind[] }>('/side-kinds')),
    units: () => data(api.get<{ data: Unit[] }>('/units')),
    storeSections: () => data(api.get<{ data: StoreSection[] }>('/store-sections')),
    tags: () => data(api.get<{ data: Tag[] }>('/tags')),
};
