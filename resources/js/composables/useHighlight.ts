import { router, usePage } from '@inertiajs/vue3';
import type { App, Directive } from 'vue';
import { ref, watchEffect } from 'vue';

/**
 * One row glows for a few seconds after it was created or edited (the controller flashes
 * `highlight` with the row's id) or when the URL deep-links to it (`?highlight={id}`, used
 * by admin notifications). Module-level state, so the flash set by a redirect and the
 * rows rendered by any table share it without prop drilling.
 *
 * Tables opt a row in with `v-highlight="row.id"` — the directive toggles the glow classes
 * and scrolls the row into view, so a table never has to bind the classes itself.
 */
const HIGHLIGHT_CLASSES = ['animate-pulse', 'bg-primary/10', 'ring-2', 'ring-primary/70'];
const CLEAR_AFTER_MS = 6000;

const highlightedId = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;
let installed = false;

const readFromUrl = (): string | null => {
    if (typeof window === 'undefined') return null;
    return new URLSearchParams(window.location.search).get('highlight');
};

const clear = () => {
    highlightedId.value = null;
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (url.searchParams.has('highlight')) {
        url.searchParams.delete('highlight');
        history.replaceState(null, '', url.toString());
    }
};

export const setHighlight = (id: string | number | null | undefined) => {
    if (id === null || id === undefined || id === '') return;
    highlightedId.value = String(id);
    if (timer) clearTimeout(timer);
    timer = setTimeout(clear, CLEAR_AFTER_MS);
};

export const isHighlighted = (id: number | string): boolean => highlightedId.value !== null && highlightedId.value === String(id);

/** `v-highlight="row.id"` — glow + scroll into view while this id is the highlighted one. */
export const vHighlight: Directive<HTMLElement & { __stopHighlight?: () => void }, string | number> = {
    mounted(el, binding) {
        let scrolled = false;
        el.__stopHighlight = watchEffect(() => {
            const on = isHighlighted(binding.value);
            el.classList[on ? 'add' : 'remove'](...HIGHLIGHT_CLASSES);
            if (on && !scrolled) {
                scrolled = true;
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            if (!on) scrolled = false;
        });
    },
    unmounted(el) {
        el.__stopHighlight?.();
    },
};

/**
 * Registers the directive and wires the two sources: the `?highlight=` URL param on any
 * navigation, and the `highlight` prop a controller flashes after a write.
 */
export const installHighlight = (app: App) => {
    app.directive('highlight', vHighlight);
    if (installed) return;
    installed = true;

    setHighlight(readFromUrl());
    router.on('navigate', () => setHighlight(readFromUrl()));
    router.on('success', (event) => setHighlight((event.detail.page.props as { highlight?: string | number | null }).highlight));
};

/** Kept for tables that still read the state directly. */
export function useHighlight() {
    if (!installed) setHighlight(readFromUrl());
    return { highlightedId, isHighlighted, clear, page: usePage() };
}
