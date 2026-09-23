import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const STORAGE_KEY = 'cms_timezone';

const browserTimezone = (): string => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
};

const readStored = (): string => {
    try {
        return localStorage.getItem(STORAGE_KEY) || 'auto';
    } catch {
        return 'auto';
    }
};

/**
 * Module-level singleton, so the picker in the corner and every table that
 * renders a date share one reactive source. 'auto' means the viewer's browser
 * timezone.
 */
const timezone = ref<string>(readStored());

/** Every zone the browser knows, for the picker's list. */
export const timezoneOptions = (): string[] => {
    try {
        return Intl.supportedValuesOf('timeZone');
    } catch {
        return ['UTC'];
    }
};

/**
 * Formats stored UTC datetimes in the viewer's locale and chosen timezone.
 * Never render a raw DB datetime — always go through formatDate/formatDateOnly.
 */
export function useDateFormat() {
    const page = usePage();

    const resolvedTimezone = (): string => (timezone.value === 'auto' ? browserTimezone() : timezone.value);

    const setTimezone = (tz: string): void => {
        timezone.value = tz || 'auto';

        try {
            localStorage.setItem(STORAGE_KEY, timezone.value);
        } catch {
            // A private window can refuse storage; the choice just won't persist.
        }

        // app.ts reads the stored value live for the X-Timezone header, so
        // there is nothing else to sync here.
    };

    const locale = (): string => page.props.locale?.code || 'en';

    const formatDate = (value: string | number | Date | null | undefined, opts: Intl.DateTimeFormatOptions = {}): string => {
        if (!value) return '';
        const date = new Date(value);
        if (isNaN(date.getTime())) return '';

        return new Intl.DateTimeFormat(locale(), {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: resolvedTimezone(),
            ...opts,
        }).format(date);
    };

    const formatDateOnly = (value: string | number | Date | null | undefined): string => formatDate(value, { hour: undefined, minute: undefined });

    return { timezone, resolvedTimezone, setTimezone, formatDate, formatDateOnly };
}
