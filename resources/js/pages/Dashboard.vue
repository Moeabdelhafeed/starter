<script setup>
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Default from '@/layouts/default.vue';
import {
    Users,
    Smartphone,
    Shield,
    Globe,
    FileText,
    Languages,
    Activity,
    Settings,
    ArrowRight,
    Clock,
} from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);

const props = defineProps({
    stats: Object,
    recentActivities: Array,
    isLocal: Boolean,
});

const statCards = computed(() => [
    {
        key: 'admins',
        label: t('users'),
        value: props.stats?.admins || 0,
        icon: Users,
        color: 'text-blue-500',
        bgColor: 'bg-blue-500/10',
        route: 'users',
        permission: 'users',
    },
    {
        key: 'appUsers',
        label: t('app_users'),
        value: props.stats?.appUsers || 0,
        icon: Smartphone,
        color: 'text-emerald-500',
        bgColor: 'bg-emerald-500/10',
        route: 'app_users',
        permission: 'app_users',
        showIf: page.props.app_users || page.props.app_guests,
    },
    {
        key: 'roles',
        label: t('roles'),
        value: props.stats?.roles || 0,
        icon: Shield,
        color: 'text-purple-500',
        bgColor: 'bg-purple-500/10',
        route: 'roles',
        permission: 'roles',
    },
    {
        key: 'languages',
        label: t('languages'),
        value: props.stats?.languages || 0,
        icon: Globe,
        color: 'text-amber-500',
        bgColor: 'bg-amber-500/10',
        route: 'languages',
        permission: 'languages',
        showIf: page.props.has_translations,
    },
    {
        key: 'pages',
        label: t('pages'),
        value: props.stats?.pages || 0,
        icon: FileText,
        color: 'text-rose-500',
        bgColor: 'bg-rose-500/10',
        route: 'pages',
        permission: 'pages',
        showIf: page.props.has_pages,
    },
    {
        key: 'translations',
        label: t('translations'),
        value: props.stats?.translations || 0,
        icon: Languages,
        color: 'text-cyan-500',
        bgColor: 'bg-cyan-500/10',
        route: 'translations',
        permission: 'translations',
        showIf: page.props.has_translations,
    },
]);

const visibleStats = computed(() => {
    return statCards.value.filter(card => {
        if (card.showIf !== undefined && !card.showIf) return false;
        if (card.permission && !page.props.auth.permissions?.includes(card.permission)) return false;
        return true;
    });
});

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(page.props.locale?.code || 'en', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getActionColor = (action) => {
    switch (action) {
        case 'created':
            return 'text-emerald-500 bg-emerald-500/10';
        case 'updated':
            return 'text-blue-500 bg-blue-500/10';
        case 'deleted':
            return 'text-red-500 bg-red-500/10';
        default:
            return 'text-muted-foreground bg-muted';
    }
};

const getSubjectName = (subjectType) => {
    if (!subjectType) return '';
    const parts = subjectType.split('\\');
    return parts[parts.length - 1];
};
</script>

<template>
    <Head :title="t('dashboard')" />

    <div class="mx-auto max-w-[1300px] px-4 py-6 sm:px-6 lg:px-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-foreground">
                {{ t('welcome_greeting') }} {{ user?.name }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{ t('dashboard_subtitle') }}
            </p>
        </div>

        <!-- Stats Grid -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Link
                v-for="stat in visibleStats"
                :key="stat.key"
                :href="route(stat.route)"
                class="group rounded-2xl border border-border bg-card p-5 transition-all hover:border-primary/50 hover:shadow-lg"
            >
                <div class="flex items-center justify-between">
                    <div :class="[stat.bgColor, 'rounded-xl p-3']">
                        <component :is="stat.icon" :class="[stat.color, 'size-6']" />
                    </div>
                    <ArrowRight class="size-5 text-muted-foreground opacity-0 transition-all group-hover:opacity-100 group-hover:translate-x-1 rtl:group-hover:-translate-x-1 rtl:rotate-180" />
                </div>
                <div class="mt-4">
                    <p class="text-3xl font-bold text-foreground">{{ stat.value }}</p>
                    <p class="text-sm text-muted-foreground">{{ stat.label }}</p>
                </div>
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Recent Activity -->
            <div class="lg:col-span-2">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl bg-primary/10 p-2.5">
                                <Activity class="size-5 text-primary" />
                            </div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('recent_activity') }}</h2>
                        </div>
                        <Link
                            v-if="page.props.has_activity_logs && page.props.auth.permissions?.includes('activity_logs')"
                            :href="route('activity_logs')"
                            class="text-sm text-primary hover:underline"
                        >
                            {{ t('view_all') }}
                        </Link>
                    </div>

                    <div v-if="recentActivities?.length" class="space-y-4">
                        <div
                            v-for="activity in recentActivities"
                            :key="activity.id"
                            class="flex items-center gap-4 rounded-xl border border-border bg-background p-4"
                        >
                            <div :class="[getActionColor(activity.action), 'rounded-lg px-2.5 py-1 text-xs font-medium capitalize']">
                                {{ t(activity.action) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-foreground truncate">
                                    <span class="me-1 font-medium">{{ activity.causer_name || t('system') }}</span>
                                    <span class="me-1 text-muted-foreground">{{ t(activity.action) }}</span>
                                    <span class="font-medium">{{ getSubjectName(activity.subject_type) }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground shrink-0">
                                <Clock class="size-3.5" />
                                {{ formatDate(activity.created_at) }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 text-center text-muted-foreground">
                        <Activity class="mx-auto size-12 opacity-20" />
                        <p class="mt-3">{{ t('no_recent_activity') }}</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div>
                <div class="rounded-3xl border border-border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="rounded-xl bg-primary/10 p-2.5">
                            <Settings class="size-5 text-primary" />
                        </div>
                        <h2 class="text-lg font-semibold text-foreground">{{ t('quick_actions') }}</h2>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-if="page.props.auth.permissions?.includes('users')"
                            :href="route('users')"
                            class="flex w-full items-center gap-3 rounded-xl border border-border bg-background p-4 transition-colors hover:border-primary/50 hover:bg-muted/50"
                        >
                            <Users class="size-5 text-blue-500" />
                            <span class="text-sm font-medium text-foreground">{{ t('manage_users') }}</span>
                        </Link>

                        <Link
                            v-if="page.props.auth.permissions?.includes('roles')"
                            :href="route('roles')"
                            class="flex w-full items-center gap-3 rounded-xl border border-border bg-background p-4 transition-colors hover:border-primary/50 hover:bg-muted/50"
                        >
                            <Shield class="size-5 text-purple-500" />
                            <span class="text-sm font-medium text-foreground">{{ t('manage_roles') }}</span>
                        </Link>

                        <Link
                            v-if="page.props.has_translations && page.props.auth.permissions?.includes('translations')"
                            :href="route('translations')"
                            class="flex w-full items-center gap-3 rounded-xl border border-border bg-background p-4 transition-colors hover:border-primary/50 hover:bg-muted/50"
                        >
                            <Languages class="size-5 text-cyan-500" />
                            <span class="text-sm font-medium text-foreground">{{ t('manage_translations') }}</span>
                        </Link>

                        <Link
                            :href="route('profile')"
                            class="flex w-full items-center gap-3 rounded-xl border border-border bg-background p-4 transition-colors hover:border-primary/50 hover:bg-muted/50"
                        >
                            <Users class="size-5 text-emerald-500" />
                            <span class="text-sm font-medium text-foreground">{{ t('edit_profile') }}</span>
                        </Link>

                        <!-- Developer Settings - only in local -->
                        <Link
                            v-if="isLocal"
                            :href="route('dev_settings')"
                            class="flex w-full items-center gap-3 rounded-xl border border-amber-500/50 bg-amber-500/5 p-4 transition-colors hover:border-amber-500 hover:bg-amber-500/10"
                        >
                            <Settings class="size-5 text-amber-500" />
                            <span class="text-sm font-medium text-amber-600 dark:text-amber-400">{{ t('developer_settings') }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Activity Stats Card -->
                <div class="mt-6 rounded-3xl border border-border bg-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('total_activities') }}</p>
                            <p class="text-2xl font-bold text-foreground">{{ stats?.activities || 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-primary/10 p-3">
                            <Activity class="size-6 text-primary" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
