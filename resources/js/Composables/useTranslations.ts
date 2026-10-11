import { usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

interface Localization {
    locale: 'nl' | 'en_US';
    messages: Record<string, string>;
}

export function useTranslations() {
    const page = usePage<{ i18n?: Localization }>();
    const locale = () => page.props?.i18n?.locale === 'nl' ? 'nl' : 'en-US';

    function t(source: string, replacements: Record<string, string | number> = {}): string {
        const message = page.props?.i18n?.messages[source] ?? source;
        return message.replace(/:([a-zA-Z_]+)/g, (placeholder, name: string) =>
            Object.hasOwn(replacements, name) ? String(replacements[name]) : placeholder);
    }

    function date(value: string | null): string {
        return value ? new Date(value).toLocaleString(locale()) : t('Not yet');
    }

    return { t, date, locale };
}

export function useDocumentLanguage() {
    const { locale } = useTranslations();
    watchEffect(() => { document.documentElement.lang = locale(); });
}
