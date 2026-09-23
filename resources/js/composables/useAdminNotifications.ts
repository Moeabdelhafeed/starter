import { usePage } from '@inertiajs/vue3';
import { echo, useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';

import type { AdminNotification } from '@/types';

const liveUnread = ref(0);
const incoming = ref<AdminNotification[]>([]);
const subscribedChannels = new Set<string>();

/**
 * Shared admin notifications state. Subscribes to one private channel per
 * permission the admin holds — matches `admin.notifications.{type}` and the
 * channel-auth gate in routes/channels.php, so unauthorized admins never see
 * the payload (no DevTools side-channel leak).
 */
export function useAdminNotifications() {
    const page = usePage();

    const permissions = (page.props.auth?.permissions ?? []) as string[];

    // Echo is a browser websocket client — unconfigured during SSR, and calling
    // into it there throws. The reactive state below still renders server-side;
    // only the live subscription is skipped.
    for (const permission of typeof window === 'undefined' ? [] : permissions) {
        const channelName = `admin.notifications.${permission}`;
        if (subscribedChannels.has(channelName)) continue;
        subscribedChannels.add(channelName);

        useEcho(channelName, '.notification.created', (event: { notification?: AdminNotification }) => {
            const n = event?.notification;
            if (!n) return;
            incoming.value.unshift(n);
            if (!n.read_at) liveUnread.value += 1;
        });
    }

    const unreadCount = computed(() => Number(page.props.notifications?.unread_count ?? 0) + liveUnread.value);

    const decrementUnread = () => {
        if (liveUnread.value > 0) liveUnread.value -= 1;
    };

    const resetLive = () => {
        liveUnread.value = 0;
    };

    /** Drop every subscribed admin channel — used on logout. */
    const leaveAll = () => {
        try {
            const e = echo();
            for (const channelName of subscribedChannels) {
                e.leave(`private-${channelName}`);
            }
        } catch {
            // Echo may not be initialized in some test contexts.
        }
        subscribedChannels.clear();
        liveUnread.value = 0;
        incoming.value = [];
    };

    return { unreadCount, incoming, decrementUnread, resetLive, leaveAll };
}
