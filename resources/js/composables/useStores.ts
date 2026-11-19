import { computed, type ComputedRef } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { storesApi } from '@/api/stores';
import type { ProductOffer, Store } from '@/api/types';

export const STORES_KEY = ['stores'];

/**
 * The stores of the household, the preferred ones first, and what they
 * say about an offer: the current name of its store, which is `null` for
 * a size sold everywhere, and whether the household buys there at the
 * moment.
 */
export function useStores(): {
    stores: ComputedRef<Store[]>;
    activeStores: ComputedRef<Store[]>;
    storeName: (offer: ProductOffer) => string | null;
    isActive: (offer: ProductOffer) => boolean;
} {
    const query = useQuery({ queryKey: STORES_KEY, queryFn: storesApi.list });
    const stores = computed(() =>
        [...(query.data.value ?? [])].sort(
            (first, second) =>
                Number(second.is_preferred) - Number(first.is_preferred) ||
                first.name.localeCompare(second.name),
        ),
    );
    const activeStores = computed(() => stores.value.filter((store) => store.is_active));
    const byId = computed(() => new Map(stores.value.map((store) => [store.id, store])));

    function storeOf(offer: ProductOffer): Store | undefined {
        return offer.store_id === null ? undefined : byId.value.get(offer.store_id);
    }

    return {
        stores,
        activeStores,
        storeName: (offer) => storeOf(offer)?.name ?? offer.store,
        isActive: (offer) => storeOf(offer)?.is_active ?? true,
    };
}
