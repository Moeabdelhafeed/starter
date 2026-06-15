interface Translation {
    field: string;
    locale: string;
    value: string;
}

interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
}

export function useTranslations() {
    /**
     * Convert translations array to object format
     * From: [{field: 'name', locale: 'en', value: 'Hello'}, ...]
     * To: {name: {en: 'Hello', ar: '...'}, description: {...}}
     */
    const translationsToObject = (
        translations: Translation[] | undefined,
        fields: string[]
    ): Record<string, Record<string, string>> => {
        const result: Record<string, Record<string, string>> = {};

        fields.forEach(field => {
            result[field] = {};
        });

        translations?.forEach(t => {
            if (result[t.field] !== undefined) {
                result[t.field][t.locale] = t.value;
            }
        });

        return result;
    };

    /**
     * Initialize empty translations object for form
     */
    const initTranslations = (
        fields: string[],
        languages: Language[]
    ): Record<string, Record<string, string>> => {
        const result: Record<string, Record<string, string>> = {};

        fields.forEach(field => {
            result[field] = {};
            languages.forEach(lang => {
                result[field][lang.code] = '';
            });
        });

        return result;
    };

    return {
        translationsToObject,
        initTranslations,
    };
}
