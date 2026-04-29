import { usePage, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';

/**
 * Subscribes to the authenticated user's private channel and listens for
 * `device.revoked` events. When the broadcast `token_id` matches the value
 * the client persisted at login, clear local credentials and bounce to login.
 *
 * Mainly used by API SPA / mobile clients. Admin Inertia sessions are
 * cookie-based and not tracked in `user_devices`, so this is a no-op for
 * admin users.
 */
export function useDeviceRevocation() {
    const page = usePage();
    const userId = (page.props as any).auth?.user?.id;

    if (!userId) return;

    useEcho(
        `user.${userId}`,
        '.device.revoked',
        (event: any) => {
            if (typeof window === 'undefined') return;

            const localTokenId = window.localStorage.getItem('current_token_id');

            // Without a stored token id, this client is admin/web — ignore.
            if (!localTokenId) return;
            if (Number(localTokenId) !== Number(event?.token_id)) return;

            window.localStorage.removeItem('auth_token');
            window.localStorage.removeItem('current_token_id');
            router.get(route('login'));
        },
        [],
        'private',
    );
}
