import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { referenceApi } from '@/api/reference';
import { currentLocale } from '@/i18n';

const STALE_TIME = 60 * 60 * 1000;

/**
 * Units, store sections and tags in the interface language. They rarely
 * change, so they are cached per language.
 */
export function useReferenceData() {
    const locale = computed(() => currentLocale());

    const units = useQuery({
        queryKey: ['units', locale],
        queryFn: referenceApi.units,
        staleTime: STALE_TIME,
    });

    const storeSections = useQuery({
        queryKey: ['store-sections', locale],
        queryFn: referenceApi.storeSections,
        staleTime: STALE_TIME,
    });

    const tags = useQuery({
        queryKey: ['tags', locale],
        queryFn: referenceApi.tags,
        staleTime: STALE_TIME,
    });

    return {
        units: computed(() => units.data.value ?? []),
        storeSections: computed(() => storeSections.data.value ?? []),
        tags: computed(() => tags.data.value ?? []),
    };
}
