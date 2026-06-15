import { ref, watch } from 'vue';

export type ViewMode = 'table' | 'grid';

/**
 * Reactive table/grid view switch persisted per-feature in localStorage.
 * `key` scopes the preference (e.g. 'users', 'roles') so each page remembers
 * its own choice. SSR-safe: falls back to `fallback` when window is missing.
 */
export function useViewMode(key: string, fallback: ViewMode = 'table') {
    const storageKey = `view_mode:${key}`;

    const read = (): ViewMode => {
        if (typeof window === 'undefined') return fallback;
        const stored = window.localStorage.getItem(storageKey);
        return stored === 'grid' || stored === 'table' ? stored : fallback;
    };

    const view = ref<ViewMode>(read());

    watch(view, (value) => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(storageKey, value);
    });

    const setView = (value: ViewMode) => {
        view.value = value;
    };

    return { view, setView };
}
