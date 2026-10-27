import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update as updateLanguage } from '@/actions/App/Http/Controllers/LanguageController';

export type Locale = 'en' | 'de';

export type TranslateParams = Record<string, string | number>;

const page = usePage();

/**
 * Active interface language, shared by the backend on every page.
 */
export const locale = computed<Locale>(() =>
    page.props.locale === 'de' ? 'de' : 'en',
);

function interpolate(text: string, params: TranslateParams): string {
    return Object.entries(params).reduce(
        // Only the given names are replaced, so a literal colon in the text stays untouched.
        (result, [name, value]) => result.replaceAll(`:${name}`, String(value)),
        text,
    );
}

/**
 * Translate an interface string by its prototype key (F-24).
 *
 * Placeholders use the Laravel form, `:name` ("Back to :name"), the same as
 * lang/{en,de}/ui.php. Longer names are replaced first so `:names` is not
 * clobbered by `:name`. Unknown keys return the key itself.
 */
export function t(key: string, params: TranslateParams = {}): string {
    const translations = (page.props.translations ?? {}) as Record<
        string,
        string
    >;
    const sorted = Object.fromEntries(
        Object.entries(params).sort(([a], [b]) => b.length - a.length),
    );

    return interpolate(translations[key] ?? key, sorted);
}

/**
 * Translate a plural string of the form "one form|other form" for a count.
 *
 * `:n` is filled with the count automatically. Uses the active language's
 * plural rules, so it also works for languages with other categories later.
 */
export function tc(
    key: string,
    count: number,
    params: TranslateParams = {},
): string {
    const forms = t(key, { ...params, n: count }).split('|');
    const category = new Intl.PluralRules(locale.value).select(count);

    return (category === 'one' ? forms[0] : (forms[1] ?? forms[0])).trim();
}

/**
 * Switch the interface language: guests keep it in the session, accounts on their profile.
 *
 * The backend redirects back, which refreshes the shared locale and strings.
 */
export function setLocale(language: Locale): void {
    if (language === locale.value) {
        return;
    }

    router.patch(
        updateLanguage.url(),
        { language },
        { preserveScroll: true, preserveState: true },
    );
}
