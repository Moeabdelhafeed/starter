import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

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

// Module-level singleton so the navbar selector and every table share one
// reactive source. 'auto' resolves to the viewer's browser timezone.
const timezone = ref<string>(readStored());

export const timezoneOptions = (): string[] => {
    try {
        // @ts-expect-error supportedValuesOf is widely available in modern browsers
        return Intl.supportedValuesOf('timeZone');
    } catch {
        return ['UTC'];
    }
};

export function useDateFormat() {
    const page = usePage();

    const resolvedTimezone = (): string => (timezone.value === 'auto' ? browserTimezone() : timezone.value);

    const setTimezone = (tz: string): void => {
        timezone.value = tz || 'auto';
        try {
            localStorage.setItem(STORAGE_KEY, timezone.value);
        } catch {
            /* ignore */
        }
        // The X-Timezone request header is read live from localStorage by the
        // Inertia `before` hook in app.ts, so no extra sync is needed here.
    };

    const locale = (): string => (page.props as any)?.locale?.code || 'en';

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

    const formatDateOnly = (value: string | number | Date | null | undefined): string =>
        formatDate(value, { hour: undefined, minute: undefined });

    return { timezone, resolvedTimezone, setTimezone, formatDate, formatDateOnly };
}
