import { api, data } from './client';
import type { Conversion, OfferUnit, Paginated, Product, ProductInput, ProductOffer } from './types';

/**
 * An offer as it is sent: the store by id or by name, or a `store_id` of
 * `null` for a size sold everywhere.
 */
export interface OfferInput {
    store_id?: number | null;
    store?: string;
    amount: number | null;
    unit: OfferUnit | null;
    price: number | null;
}

export const productsApi = {
    list: (filters: { search?: string; page?: number }) => api.get<Paginated<Product>>('/products', filters),
    review: (page = 1) => api.get<Paginated<Product>>('/review', { page }),
    get: (id: number) => data(api.get<{ data: Product }>(`/products/${id}`)),
    create: (input: ProductInput) => data(api.post<{ data: Product }>('/products', input)),
    update: (id: number, input: ProductInput) => data(api.patch<{ data: Product }>(`/products/${id}`, input)),
    remove: (id: number) => api.delete(`/products/${id}`),
    confirm: (id: number) => data(api.post<{ data: Product }>(`/products/${id}/confirm`)),
    merge: (id: number, into: number) => data(api.post<{ data: Product }>(`/products/${id}/merge`, { into })),

    setConversion: (id: number, unitId: number, amountInBase: number | null) =>
        data(
            api.put<{ data: Conversion }>(`/products/${id}/conversions/${unitId}`, {
                amount_in_base: amountInBase,
            }),
        ),
    removeConversion: (id: number, unitId: number) => api.delete(`/products/${id}/conversions/${unitId}`),

    addOffer: (id: number, offer: OfferInput) =>
        data(api.post<{ data: ProductOffer }>(`/products/${id}/offers`, offer)),
    updateOffer: (offerId: number, offer: OfferInput) =>
        data(api.patch<{ data: ProductOffer }>(`/product-offers/${offerId}`, offer)),
    removeOffer: (offerId: number) => api.delete(`/product-offers/${offerId}`),
};
