import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, Fragment } from 'vue';
import { createI18n } from 'vue-i18n';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import TestingBadge from './components/Shared/TestingBadge.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Import your language files
import ar from './locales/ar.json';
import en from './locales/en.json';
import { configureEcho } from '@laravel/echo-vue';

// Send the admin's active timezone on every Inertia request so the backend
// HasUserTimezone trait can convert user-entered datetimes to UTC before save.
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

const i18n = createI18n({
    locale: 'en', // set locale
    fallbackLocale: 'en', // set fallback locale
    messages, // set locale messages
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(Fragment, [h(App, props), h(TestingBadge)]) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: 'var(--primary)',
    },
});
