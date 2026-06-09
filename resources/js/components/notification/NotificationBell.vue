<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bell, Check, CheckCheck, X, User, Activity, Clock } from 'lucide-vue-next';
import { useAdminNotifications } from '@/composables/useAdminNotifications';

const props = defineProps({
    navbarOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const page = usePage();

const isOpen = ref(false);

// Close notification sidebar when navbar closes
watch(() => props.navbarOpen, (newVal) => {
    if (!newVal && isOpen.value) {
        isOpen.value = false;
    }
});
const notifications = ref([]);
const isLoading = ref(false);

// Shared admin-notifications state (Echo subscription lives in the composable).
const { unreadCount, incoming, decrementUnread } = useAdminNotifications();

// Mirror live arrivals into the local list so the open sidebar updates.
watch(incoming, (list) => {
    if (list.length === 0) return;
    notifications.value.unshift(...list.splice(0));
}, { deep: true });

const toggleSidebar = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value && notifications.value.length === 0) {
        fetchNotifications();
    }
};

const closeSidebar = () => {
    isOpen.value = false;
};

const fetchNotifications = async () => {
    isLoading.value = true;
    try {
        const response = await fetch(route('notifications.recent'));
        const data = await response.json();
        notifications.value = data.notifications;
    } catch (error) {
        console.error('Failed to fetch notifications:', error);
    } finally {
        isLoading.value = false;
    }
};

const markAsRead = async (notification) => {
    // Plain fetch — avoids Inertia partial reload (which flashes shared props
    // and momentarily un-styles the page during nav).
    try {
        await fetch(route('notifications.mark_read', notification.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });
    } catch (e) {
        // Ignore — UI already optimistically updated below.
    }

    const index = notifications.value.findIndex(n => n.id === notification.id);
    if (index !== -1) {
        notifications.value[index].read_at = new Date().toISOString();
    }
    decrementUnread();
};

// Notification click → navigate to target route with ?highlight={id}
// so the destination page can glow the related row.
const openNotification = (notification) => {
    if (!notification.read_at) {
        markAsRead(notification);
    }

    // Close sidebar + signal navbar to collapse on mobile.
    closeSidebar();
    emit('close');

    if (notification.target?.route) {
        router.get(route(notification.target.route, { highlight: notification.target.highlight }), {}, {
            preserveState: false,
            preserveScroll: false,
        });
    }
};

const markAllAsRead = () => {
    router.post(route('notifications.mark_all_read'), {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = notifications.value.map(n => ({
                ...n,
                read_at: new Date().toISOString(),
            }));
        },
    });
};

const getTypeIcon = (type) => {
    switch (type) {
        case 'app_users':
            return User;
        case 'activity_logs':
            return Activity;
        default:
            return Bell;
    }
};

const getTypeColor = (type) => {
    switch (type) {
        case 'app_users':
            return 'text-emerald-500 bg-emerald-500/10';
        case 'activity_logs':
            return 'text-blue-500 bg-blue-500/10';
        default:
            return 'text-primary bg-primary/10';
    }
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / (1000 * 60));
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffMins < 1) return t('just_now');
    if (diffMins < 60) return t('minutes_ago', { count: diffMins });
    if (diffHours < 24) return t('hours_ago', { count: diffHours });
    if (diffDays < 7) return t('days_ago', { count: diffDays });

    return date.toLocaleDateString(page.props.locale?.code || 'en', {
        month: 'short',
        day: 'numeric',
    });
};

// Close sidebar on escape key
const handleKeydown = (event) => {
    if (event.key === 'Escape' && isOpen.value) {
        closeSidebar();
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div>
        <!-- Bell Button -->
        <button @click.stop="toggleSidebar"
            class="relative flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-primary/10">
            <Bell class="size-5 text-muted-foreground" />
            <span v-if="unreadCount > 0"
                class="absolute -end-0.5 -top-0.5 flex size-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-semibold leading-none text-white shadow-md">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Backdrop -->
        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="isOpen" @click="closeSidebar" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm"></div>
        </Transition>

        <!-- Sidebar (same side as navbar) -->
        <Transition enter-active-class="transition duration-300 ease-out"
            enter-from-class="ltr:-translate-x-full rtl:translate-x-full" enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in" leave-from-class="translate-x-0"
            leave-to-class="ltr:-translate-x-full rtl:translate-x-full">
            <div v-if="isOpen" class="fixed start-0 top-0 z-50 flex h-screen w-[85vw] max-w-[320px] flex-col bg-card shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-primary/10 p-2">
                            <Bell class="size-5 text-primary" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-foreground">{{ t('notifications') }}</h2>
                            <p class="text-xs text-muted-foreground">
                                {{ unreadCount }} {{ t('unread') }}
                            </p>
                        </div>
                    </div>
                    <button @click="closeSidebar"
                        class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Actions Bar -->
                <div v-if="unreadCount > 0" class="border-b border-border px-4 py-2">
                    <button @click="markAllAsRead" class="flex items-center gap-2 text-sm text-primary hover:underline">
                        <CheckCheck class="size-4" />
                        {{ t('mark_all_read') }}
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center py-12">
                        <div class="size-8 animate-spin rounded-full border-2 border-primary border-t-transparent">
                        </div>
                    </div>

                    <!-- No Notifications -->
                    <div v-else-if="notifications.length === 0" class="flex flex-col items-center justify-center py-16">
                        <div class="rounded-full bg-muted p-4">
                            <Bell class="size-8 text-muted-foreground" />
                        </div>
                        <p class="mt-4 text-sm text-muted-foreground">{{ t('no_notifications') }}</p>
                    </div>

                    <!-- Notification List -->
                    <div v-else class="divide-y divide-border">
                        <div v-for="notification in notifications" :key="notification.id"
                            class="flex gap-3 p-4 transition-colors"
                            :class="[
                                notification.read_at ? 'bg-background' : 'bg-primary/5',
                                notification.target?.route ? 'cursor-pointer hover:bg-primary/10' : '',
                            ]"
                            @click="notification.target?.route && openNotification(notification)">
                            <!-- Type Icon -->
                            <div
                                :class="[getTypeColor(notification.type), 'flex size-10 shrink-0 items-center justify-center rounded-lg']">
                                <component :is="getTypeIcon(notification.type)" class="size-5" />
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-foreground">{{ notification.title }}</p>
                                <p v-if="notification.message"
                                    class="mt-0.5 text-xs text-muted-foreground line-clamp-2">
                                    {{ notification.message }}
                                </p>
                                <div class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Clock class="size-3" />
                                    {{ formatDate(notification.created_at) }}
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex shrink-0 items-start">
                                <button v-if="!notification.read_at" @click.stop="markAsRead(notification)"
                                    class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                    :title="t('mark_as_read')">
                                    <Check class="size-4" />
                                </button>
                                <div v-else class="p-1.5">
                                    <CheckCheck class="size-4 text-emerald-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </Transition>
    </div>
</template>
