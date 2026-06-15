import { ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

/**
 * Reads `?highlight={id}` from the current URL and exposes a reactive helper
 * for tables to glow the matching row. After `clearAfterMs` the id is reset
 * and the query param removed so a refresh doesn't re-trigger the glow.
 */
export function useHighlight(clearAfterMs = 6000) {
    const page = usePage();

    const readFromUrl = (): string | null => {
        if (typeof window === 'undefined') return null;
        const params = new URLSearchParams(window.location.search);
        return params.get('highlight');
    };

    const highlightedId = ref<string | null>(readFromUrl());
    let timer: ReturnType<typeof setTimeout> | null = null;

    const clear = () => {
        highlightedId.value = null;
        if (typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        if (url.searchParams.has('highlight')) {
            url.searchParams.delete('highlight');
            history.replaceState(null, '', url.toString());
        }
    };

    const scheduleClear = () => {
        if (timer) clearTimeout(timer);
        if (highlightedId.value === null) return;
        timer = setTimeout(clear, clearAfterMs);
    };

    scheduleClear();

    // Re-check only when the URL's highlight param actually changes — partial
    // Inertia reloads (e.g. flash data refresh) can re-fire page.url watchers
    // and would otherwise reset the timer or wipe the glow mid-display.
    watch(
        () => page.url,
        () => {
            const next = readFromUrl();
            if (next === highlightedId.value) return;
            highlightedId.value = next;
            scheduleClear();
        },
    );

    const isHighlighted = (id: number | string): boolean => {
        return highlightedId.value !== null && String(highlightedId.value) === String(id);
    };

    return { highlightedId, isHighlighted, clear };
}
