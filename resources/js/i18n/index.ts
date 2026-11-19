import { createI18n } from 'vue-i18n';
import de from './de';
import en from './en';

const LOCALE_KEY = 'dinnertime.locale';

export const SUPPORTED_LOCALES = ['de', 'en'] as const;
export type Locale = (typeof SUPPORTED_LOCALES)[number];

function isSupported(value: string | null | undefined): value is Locale {
    return SUPPORTED_LOCALES.includes(value as Locale);
}

function initialLocale(): Locale {
    const stored = localStorage.getItem(LOCALE_KEY);
    const browser = navigator.language.slice(0, 2);

    return isSupported(stored) ? stored : isSupported(browser) ? browser : 'en';
}

export const i18n = createI18n({
    legacy: false,
    locale: initialLocale(),
    fallbackLocale: 'en',
    messages: { de, en },
});

/**
 * Switch the interface language and remember the choice on this device.
 */
export function setLocale(locale: Locale): void {
    i18n.global.locale.value = locale;
    localStorage.setItem(LOCALE_KEY, locale);
    document.documentElement.lang = locale;
}

export function currentLocale(): Locale {
    return i18n.global.locale.value as Locale;
}
