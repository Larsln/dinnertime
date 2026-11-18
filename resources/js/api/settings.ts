import { api, data } from './client';

/**
 * How the server fetches links for the import.
 */
export interface ImportSettings {
    identify_as_browser: boolean;
    has_cookies: boolean;
}

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
