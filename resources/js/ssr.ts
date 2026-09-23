import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import { renderToString } from 'vue/server-renderer';

import { route, ZiggyVue } from '../../vendor/tightenco/ziggy';

import ar from './locales/ar.json';
import en from './locales/en.json';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Server-side render entry.
 *
 * It must install the same plugins as the browser entry (`app.ts`) — a missing
 * plugin here does not fail the build, it throws at render time for every page
 * whose components call into it, which is how this file previously took down
 * SSR for the entire admin panel.
 *
 * Deliberately NOT installed: Laravel Echo. It is a browser websocket client;
 * the composables that use it (`useDeviceRevocation`, `useAdminNotifications`)
 * no-op when `window` is undefined.
 */
createServer(
    (page) => {
        // Ziggy is a browser global in the client build (installed by the
        // @routes Blade directive). Server-side both globals have to be defined
        // from the route list Inertia shares, or any component that calls
        // route() throws and takes the whole page render down.
        const ziggy = page.props?.ziggy as ZiggyConfig | undefined;

        // @ts-expect-error — deliberately defining the browser globals on the server.
        globalThis.Ziggy = ziggy;
        // @ts-expect-error — same.
        globalThis.route = route;

        return createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: resolvePage,
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(ZiggyVue)
                    // Locale comes from the page payload so the server renders
                    // the same language the browser is about to hydrate.
                    .use(
                        createI18n({
                            locale: (page.props?.locale as { code?: string } | undefined)?.code ?? 'en',
                            fallbackLocale: 'en',
                            messages: { en, ar },
                        }),
                    ),
        });
    },
    { cluster: true },
);

type ZiggyConfig = {
    url: string;
    port: number | null;
    defaults: Record<string, unknown>;
    routes: Record<string, unknown>;
    location?: string;
};

function resolvePage(name: string) {
    const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

    return resolvePageComponent<DefineComponent>(`./pages/${name}.vue`, pages);
}
