import { api, data } from './client';
import type { Conversion, Paginated, Product, ProductInput, ProductOffer, ProductPackage } from './types';

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

    addPackage: (id: number, label: string, amountInBase: number) =>
        data(
            api.post<{ data: ProductPackage }>(`/products/${id}/packages`, {
                label,
                amount_in_base: amountInBase,
            }),
        ),
    updatePackage: (packageId: number, label: string, amountInBase: number) =>
        data(
            api.patch<{ data: ProductPackage }>(`/product-packages/${packageId}`, {
                label,
                amount_in_base: amountInBase,
            }),
        ),
    removePackage: (packageId: number) => api.delete(`/product-packages/${packageId}`),

    stores: () => data(api.get<{ data: string[] }>('/stores')),
    addOffer: (id: number, offer: Omit<ProductOffer, 'id'>) =>
        data(api.post<{ data: ProductOffer }>(`/products/${id}/offers`, offer)),
    removeOffer: (offerId: number) => api.delete(`/product-offers/${offerId}`),
};
