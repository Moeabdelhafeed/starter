<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useMouse, useWindowSize } from '@vueuse/core';
import {
    Home,
    Settings,
    Users,
    ChevronDown,
    Globe,
    Languages,
    Check,
    LogOut,
    ChevronRight,
    Activity,
    Sun,
    Moon,
    UserIcon,
    FileText,
} from 'lucide-vue-next';
import NotificationBell from '@/components/notification/NotificationBell.vue';
import { useAdminNotifications } from '@/composables/useAdminNotifications';
import { computed, ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';

const page = usePage();

const { t, locale } = useI18n();

const { x, y } = useMouse();

const { width } = useWindowSize();

const currentLocale = computed(() => page.props.locale);

const isMouseOver = ref(false);

const isSidebarOpen = computed(() => {
    if (isMouseOver.value) return true;
    return currentLocale.value.code == 'en' ? x.value < 30 : x.value > width.value - 30;
});

// Live unread count — drives the badge on the open-arrow when navbar is closed.
const { unreadCount: navbarUnread, leaveAll: leaveNotificationChannels } = useAdminNotifications();

const handleLogout = () => {
    leaveNotificationChannels();
};

const openAccordions = ref({
    usersAndRoles: false,
    websiteSettings: false,
});

const toggleAccordion = (key) => {
    openAccordions.value[key] = !openAccordions.value[key];
};

const isDark = ref(false);

onMounted(() => {
    isDark.value = localStorage.getItem('theme') === 'dark';
});

const toggleTheme = (event) => {
    const applyTheme = () => {
        isDark.value = !isDark.value;
        document.documentElement.classList.toggle('dark', isDark.value);
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
    };

    if (!document.startViewTransition) {
        applyTheme();
        return;
    }

    const x = event?.clientX ?? window.innerWidth / 2;
    const y = event?.clientY ?? window.innerHeight / 2;
    const radius = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y),
    );

    document.documentElement.style.setProperty('--theme-x', `${x}px`);
    document.documentElement.style.setProperty('--theme-y', `${y}px`);
    document.documentElement.style.setProperty('--theme-r', `${radius}px`);

    document.startViewTransition(() => applyTheme());
};

const localeForm = useForm({
    locale: '',
});

const changeLocale = () => {
    const target = currentLocale.value.code === 'ar' ? 'en' : 'ar';

    locale.value = target;

    localeForm.locale = target;

    localeForm.post(route('locale.post'), {
        preserveScroll: true,
    });
};

const isRouteActive = (name) => {
    // Referencing page.url ensures this function is re-evaluated when the URL changes
    page.url;
    return route().current(name);
};
</script>

<template>
    <div>
        <div
            :class="[isSidebarOpen ? 'opacity-100' : 'pointer-events-none opacity-0']"
            class="fixed inset-0 z-[50] bg-black/40 backdrop-blur-sm transition-all duration-300"
        ></div>

        <div class="fixed start-2 top-1/2 z-[30] -translate-y-1/2">
            <div class="relative">
                <ChevronRight class="size-8 text-foreground ltr:rotate-180" />
                <span
                    v-if="navbarUnread > 0 && !isSidebarOpen"
                    class="absolute -end-1 -top-1 flex size-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-semibold leading-none text-white shadow-md ring-2 ring-background"
                >
                    {{ navbarUnread > 99 ? '99+' : navbarUnread }}
                </span>
            </div>
        </div>

        <div
            @mouseenter="isMouseOver = true"
            @mouseleave="isMouseOver = false"
            :class="[isSidebarOpen ? 'translate-x-0' : 'ltr:-translate-x-full rtl:translate-x-full']"
            class="fixed z-50 flex h-screen w-[280px] flex-col bg-card shadow-2xl transition-all duration-300"
        >
            <!-- Header -->
            <div class="border-b border-border p-4 py-6">
                <div class="flex items-center justify-between">
                    <h2 class="flex flex-1 items-center justify-center text-lg font-semibold text-foreground">
                        <img src="/images/logo.png" class="w-[60%]" />
                    </h2>
                    <NotificationBell :navbar-open="isSidebarOpen" @close="isMouseOver = false" />
                </div>
            </div>

            <!-- Navigation Content -->
            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                <!-- Dashboard -->
                <div class="space-y-2">
                    <Link :href="route('dashboard')" class="w-full">
                        <Button
                            variant="ghost"
                            :class="{ 'bg-primary/10': isRouteActive('dashboard') }"
                            class="h-11 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                        >
                            <Home class="size-5 text-muted-foreground" />
                            <span class="text-foreground">{{ t('dashboard') }}</span>
                        </Button>
                    </Link>
                </div>

                <!-- Users & Roles Accordion -->
                <div v-if="page.props.auth.permissions.find((p) => p === 'users' || p === 'roles')" class="space-y-1">
                    <button
                        @click="toggleAccordion('usersAndRoles')"
                        class="flex w-full items-center justify-between rounded-lg p-3 transition-colors hover:bg-primary/10"
                    >
                        <div class="flex items-center gap-3">
                            <Users class="size-5 text-muted-foreground" />
                            <span class="text-sm font-medium text-foreground">{{ t('users_and_roles') }}</span>
                        </div>
                        <ChevronDown
                            :class="['size-5 text-muted-foreground transition-transform duration-200', openAccordions.usersAndRoles ? 'rotate-180' : '']"
                        />
                    </button>

                    <div
                        v-show="openAccordions.usersAndRoles"
                        class="ms-4 space-y-1 overflow-hidden border-s border-border ps-2 transition-all duration-200"
                    >
                        <div v-if="page.props.auth.permissions.find((p) => p === 'users')" class="space-y-2">
                            <Link :href="route('users')" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('users') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <Users class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('users') }}</span>
                                </Button>
                            </Link>
                        </div>
                        <div v-if="page.props.auth.permissions.find((p) => p === 'app_users') && $page.props.app_users == true" class="space-y-2">
                            <Link :href="route('app_users')" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('app_users') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <Users class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('app_users') }}</span>
                                </Button>
                            </Link>
                        </div>
                        <div v-if="page.props.auth.permissions.find((p) => p === 'roles')" class="space-y-2">
                             <Link :href="route('roles')" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('roles') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <Check class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('roles') }}</span>
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Website Settings Accordion -->
                <div v-if="(page.props.has_translations && page.props.auth.permissions.find((p) => p === 'translations')) || page.props.auth.permissions.find((p) => p === 'activity_logs') || page.props.auth.permissions.find((p) => p === 'pages')" class="space-y-1">
                    <button
                        @click="toggleAccordion('websiteSettings')"
                        class="flex w-full items-center justify-between rounded-lg p-3 transition-colors hover:bg-primary/10"
                    >
                        <div class="flex items-center gap-3">
                            <Settings class="size-5 text-muted-foreground" />
                            <span class="text-sm font-medium text-foreground">{{ t('website_settings') }}</span>
                        </div>
                        <ChevronDown
                            :class="[
                                'size-5 text-muted-foreground transition-transform duration-200',
                                openAccordions.websiteSettings ? 'rotate-180' : '',
                            ]"
                        />
                    </button>

                    <div
                        v-show="openAccordions.websiteSettings"
                        class="ms-4 space-y-1 overflow-hidden border-s border-border ps-2 transition-all duration-200"
                    >
                        <div v-if="page.props.has_translations && page.props.auth.permissions.find((p) => p === 'translations')" class="space-y-2">
                            <Link :href="route('translations', { locale: page.props.locale.code })" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('translations') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <Languages class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('translations') }}</span>
                                </Button>
                            </Link>
                        </div>
                        <div v-if="page.props.has_translations && page.props.auth.permissions.find((p) => p === 'translations')" class="space-y-2">
                            <Link :href="route('languages')" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('languages') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <Globe class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('languages') }}</span>
                                </Button>
                            </Link>
                        </div>
                        <div v-if="page.props.auth.permissions.find((p) => p === 'activity_logs')" class="space-y-2">
                            <Link :href="route('activity_logs')" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('activity_logs') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <Activity class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('activity_logs') }}</span>
                                </Button>
                            </Link>
                        </div>
                        <div v-if="page.props.auth.permissions.find((p) => p === 'pages')" class="space-y-2">
                            <Link :href="route('pages')" class="w-full">
                                <Button
                                    variant="ghost"
                                    :class="{ 'bg-primary/10': isRouteActive('pages') }"
                                    class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                                >
                                    <FileText class="size-4 text-muted-foreground" />
                                    <span class="text-sm text-foreground">{{ t('pages') }}</span>
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="space-y-2 p-4">
                <Link :href="route('profile')" class="w-full">
                    <Button
                        variant="ghost"
                        :class="{ 'bg-primary/10': isRouteActive('profile') }"
                        class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                    >
                        <UserIcon class="size-5 text-muted-foreground" />
                        <span class="text-sm text-foreground">{{ t('profile') }}</span>
                    </Button>
                </Link>

                <Link v-if="page.props.is_local" :href="route('dev_settings')" class="w-full">
                    <Button
                        variant="ghost"
                        :class="{ 'bg-primary/10': isRouteActive('dev_settings') }"
                        class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary"
                    >
                        <Settings class="size-5 text-muted-foreground" />
                        <span class="text-sm text-foreground">{{ t('developer_settings') }}</span>
                    </Button>
                </Link>

                <Button @click="toggleTheme" variant="ghost" class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary">
                    <Sun v-if="isDark" class="size-5 text-muted-foreground" />
                    <Moon v-else class="size-5 text-muted-foreground" />
                    <span class="text-sm text-foreground">{{ isDark ? t('light_mode') : t('dark_mode') }}</span>
                </Button>

                <Button @click="changeLocale" variant="ghost" class="h-10 w-full cursor-pointer justify-start gap-3 hover:bg-primary/10 hover:text-primary">
                    <Globe class="size-5 text-muted-foreground" />
                    <div class="flex flex-col items-start">
                        <span class="text-sm font-medium text-foreground">{{
                            currentLocale.code == 'ar' ? 'English' : 'عربي'
                        }}</span>
                    </div>
                </Button>

                <Link :href="route('logout')" method="post" as="button" class="w-full" @click="handleLogout">
                    <Button variant="ghost" class="group w-full justify-start gap-3 hover:bg-red-50 hover:text-red-600">
                        <LogOut class="size-5 text-muted-foreground group-hover:text-red-600" />
                        <div class="flex flex-col items-start">
                            <span class="text-sm font-medium text-foreground group-hover:text-red-600">{{ t('logout') }}</span>
                        </div>
                    </Button>
                </Link>
            </div>
        </div>
    </div>
</template>
