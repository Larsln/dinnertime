import { computed, type ComputedRef } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { storesApi } from '@/api/stores';
import type { ProductOffer, Store } from '@/api/types';

export const STORES_KEY = ['stores'];

/**
 * The stores of the household and what they say about an offer: its
 * current name and whether the household buys there at the moment.
 */
export function useStores(): {
    stores: ComputedRef<Store[]>;
    storeName: (offer: ProductOffer) => string;
    isActive: (offer: ProductOffer) => boolean;
} {
    const query = useQuery({ queryKey: STORES_KEY, queryFn: storesApi.list });
    const stores = computed(() => query.data.value ?? []);
    const byId = computed(() => new Map(stores.value.map((store) => [store.id, store])));

    return {
        stores,
        storeName: (offer) => byId.value.get(offer.store_id)?.name ?? offer.store,
        isActive: (offer) => byId.value.get(offer.store_id)?.is_active ?? true,
    };
}
