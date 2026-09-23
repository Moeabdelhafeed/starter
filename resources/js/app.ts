import '../css/app.css';

import { createInertiaApp, router, usePage } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, Fragment, h } from 'vue';
import { createI18n } from 'vue-i18n';

import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import TestingBadge from './components/Shared/TestingBadge.vue';
import { installHighlight } from './composables/useHighlight';
import ar from './locales/ar.json';
import en from './locales/en.json';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Infinite-scroll lists survive a write. A create/update/delete redirects back to the list,
// and that full response would carry page 1 only — while the InfiniteScroll component still
// remembers it had loaded page N, so the next fetch appended page N+1 after page 1 and the
// rows in between were gone. Every non-GET visit therefore tells the server which page each
// scroll prop had reached (keyed by its page name); `scrollPaginate()` on the server answers
// with pages 1..N and the metadata pointing at N, so client state and data agree again.
/**
 * The admin's chosen timezone rides on every Inertia request so the backend
 * HasUserTimezone trait can convert a user-entered wall-clock datetime to UTC
 * before storing it. Read from localStorage at send time, so changing it in the
 * picker takes effect on the next request with nothing to keep in sync.
 */
const resolveCmsTimezone = (): string => {
    try {
        const stored = localStorage.getItem('cms_timezone');
        if (stored && stored !== 'auto') return stored;

        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
};

router.on('before', (event) => {
    event.detail.visit.headers['X-Timezone'] = resolveCmsTimezone();
});

router.on('before', (event) => {
    const visit = event.detail.visit;
    if (visit.method === 'get') return;

    const scrollProps = (usePage() as { scrollProps?: Record<string, { pageName: string; currentPage: number | string | null }> }).scrollProps || {};
    const loaded = Object.fromEntries(
        Object.values(scrollProps)
            .filter((p) => p && Number(p.currentPage) > 1)
            .map((p) => [p.pageName, Number(p.currentPage)]),
    );

    if (Object.keys(loaded).length) {
        visit.headers['X-Inertia-Scroll-Restore'] = JSON.stringify(loaded);
    }
});

configureEcho({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    // Admin uses web session auth, not Sanctum bearer. The mobile API still
    // hits /api/broadcasting/auth via withBroadcasting() in bootstrap/app.php.
    authEndpoint: '/broadcasting/auth',
});

const messages = {
    en,
    ar,
};

// Read the locale straight off the Inertia payload embedded in the page, so the
// first paint is already in the right language. Falling back to 'en' and fixing
// it after mount flashed English and desynced SSR hydration.
const initialLocale = (() => {
    try {
        const el = document.getElementById('app');
        const page = el?.dataset.page ? JSON.parse(el.dataset.page) : null;

        return page?.props?.locale?.code ?? 'en';
    } catch {
        return 'en';
    }
})();

const i18n = createI18n({
    locale: initialLocale,
    fallbackLocale: 'en',
    messages,
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(Fragment, [h(App, props), h(TestingBadge)]) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n);
        installHighlight(app);
        app.mount(el);
    },
    progress: {
        color: 'var(--primary)',
    },
});
