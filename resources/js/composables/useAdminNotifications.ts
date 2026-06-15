import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useEcho, echo } from '@laravel/echo-vue';

const liveUnread = ref(0);
const incoming = ref<any[]>([]);
const subscribedChannels = new Set<string>();

/**
 * Shared admin notifications state. Subscribes to one private channel per
 * permission the admin holds — matches `admin.notifications.{type}` and the
 * channel-auth gate in routes/channels.php, so unauthorized admins never see
 * the payload (no DevTools side-channel leak).
 */
export function useAdminNotifications() {
    const page = usePage();

    const permissions = ((page.props as any).auth?.permissions ?? []) as string[];

    for (const permission of permissions) {
        const channelName = `admin.notifications.${permission}`;
        if (subscribedChannels.has(channelName)) continue;
        subscribedChannels.add(channelName);

        useEcho(channelName, '.notification.created', (event: any) => {
            const n = event?.notification;
            if (!n) return;
            incoming.value.unshift(n);
            if (!n.read_at) liveUnread.value += 1;
        });
    }

    const unreadCount = computed(
        () => Number((page.props as any).notifications?.unread_count || 0) + liveUnread.value,
    );

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
        } catch (_) {
            // Echo may not be initialized in some test contexts.
        }
        subscribedChannels.clear();
        liveUnread.value = 0;
        incoming.value = [];
    };

    return { unreadCount, incoming, decrementUnread, resetLive, leaveAll };
}
