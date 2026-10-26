import { locale, setLocale, t, tc } from '@/lib/i18n';

/**
 * Use the interface language helpers inside a component.
 */
export function useI18n() {
    return { t, tc, locale, setLocale };
}
