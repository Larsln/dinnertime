import { api, data } from './client';

/**
 * How the server fetches links for the import.
 */
export interface ImportSettings {
    identify_as_browser: boolean;
    has_cookies: boolean;
}

export interface HouseholdMember {
    id: number;
    name: string;
}

/**
 * What the household decided once for everyday use.
 */
export interface HouseholdSettings {
    default_servings: number;
    currency: string;
    show_stocked_needs: boolean;
    members: HouseholdMember[];
}

export const householdSettingsApi = {
    get: () => data(api.get<{ data: HouseholdSettings }>('/settings/household')),
    setDefaultServings: (servings: number) =>
        data(api.patch<{ data: HouseholdSettings }>('/settings/household', { default_servings: servings })),
    setShowsStockedNeeds: (shown: boolean) =>
        data(api.patch<{ data: HouseholdSettings }>('/settings/household', { show_stocked_needs: shown })),
};

export const importSettingsApi = {
    get: () => data(api.get<{ data: ImportSettings }>('/settings/import')),
    setIdentifyAsBrowser: (enabled: boolean) =>
        data(api.patch<{ data: ImportSettings }>('/settings/import', { identify_as_browser: enabled })),
    uploadCookies: (file: File) => {
        const form = new FormData();
        form.set('cookies', file);

        return data(api.upload<{ data: ImportSettings }>('/settings/import/cookies', form));
    },
    deleteCookies: () => data(api.delete<{ data: ImportSettings }>('/settings/import/cookies')),
};
