<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Bell, Database, Hammer, Lock, Mail, Palette, Rocket, Search, Send, Settings, ToggleLeft } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import AppearanceSection from '@/components/dev-setting/AppearanceSection.vue';
import AuthenticationSection from '@/components/dev-setting/AuthenticationSection.vue';
import BroadcastingSection from '@/components/dev-setting/BroadcastingSection.vue';
import DatabasesSection from '@/components/dev-setting/DatabasesSection.vue';
import DataLimitsSection from '@/components/dev-setting/DataLimitsSection.vue';
import DeploymentSection from '@/components/dev-setting/DeploymentSection.vue';
import EnvironmentSection from '@/components/dev-setting/EnvironmentSection.vue';
import GeneralSection from '@/components/dev-setting/GeneralSection.vue';
import MailSection from '@/components/dev-setting/MailSection.vue';
import NotificationsSection from '@/components/dev-setting/NotificationsSection.vue';
import { SECTION_LABELS, type SettingEntry, searchSettings, settingsIndex } from '@/components/dev-setting/settings-index';
import SettingsSearch from '@/components/dev-setting/SettingsSearch.vue';
import Default from '@/layouts/default.vue';

defineOptions({ layout: Default });

const { t } = useI18n();
const page = usePage();

// The testing badge is pinned bottom-end; lift the mobile nav clear of it.
const isTesting = computed(() => Boolean(page.props.is_testing));

/**
 * Every prop DevSettingController::index() sends is handed straight to one section
 * component, so each type is derived from the component that consumes it. That keeps
 * the two sides from drifting and leaves each shape documented next to the code that
 * actually reads it. The props are only forwarded in the template, never in script.
 */
type AppearanceProps = InstanceType<typeof AppearanceSection>['$props'];
type AuthenticationProps = InstanceType<typeof AuthenticationSection>['$props'];
type BroadcastingProps = InstanceType<typeof BroadcastingSection>['$props'];
type DataLimitsProps = InstanceType<typeof DataLimitsSection>['$props'];
type DeploymentProps = InstanceType<typeof DeploymentSection>['$props'];
type EnvironmentProps = InstanceType<typeof EnvironmentSection>['$props'];
type GeneralProps = InstanceType<typeof GeneralSection>['$props'];
type MailProps = InstanceType<typeof MailSection>['$props'];
type NotificationsProps = InstanceType<typeof NotificationsSection>['$props'];

withDefaults(
    defineProps<{
        lightColors?: AppearanceProps['lightColors'];
        darkColors?: AppearanceProps['darkColors'];
        envValues?: EnvironmentProps['envValues'];
        envToggles?: EnvironmentProps['envToggles'];
        firebaseConfigExists?: NotificationsProps['firebaseConfigExists'];
        firebaseCredentialsPath?: NotificationsProps['firebaseCredentialsPath'];
        baseFirebaseExists?: NotificationsProps['baseFirebaseExists'];
        authConfig?: AuthenticationProps['authConfig'];
        socialAuthConfig?: AuthenticationProps['socialAuthConfig'];
        validationConfig?: DataLimitsProps['validationConfig'];
        pusherConfig?: BroadcastingProps['pusherConfig'];
        rateLimitConfig?: DataLimitsProps['rateLimitConfig'];
        accountDeletionConfig?: DataLimitsProps['accountDeletionConfig'];
        sessionsConfig?: AuthenticationProps['sessionsConfig'];
        topicsConfig?: NotificationsProps['topicsConfig'];
        reviewerAccounts?: AuthenticationProps['reviewerAccounts'];
        git?: DeploymentProps['git'];
        baseMail?: MailProps['baseMail'];
        localMail?: MailProps['localMail'];
        baseTesting?: EnvironmentProps['baseTesting'];
        urls?: EnvironmentProps['urls'];
        protectedPagesConfig?: EnvironmentProps['protectedPagesConfig'];
        deployConfig?: DeploymentProps['deployConfig'];
        availableSeeders?: DeploymentProps['availableSeeders'];
        hostingerConfigured?: DeploymentProps['hostingerConfigured'];
        deployLog?: DeploymentProps['deployLog'];
        adminCredentials?: AuthenticationProps['adminCredentials'];
        apiToken?: AuthenticationProps['apiToken'];
        appName?: GeneralProps['appName'];
    }>(),
    {
        baseTesting: null,
        deployLog: null,
    },
);

/**
 * Sidebar navigation. `group` only labels the sidebar — nine flat entries read as one
 * undifferentiated list, and which third of the panel you are in ("how the project
 * looks" vs "how the app behaves" vs "getting it onto a server") is the first thing
 * worth knowing. Order within a group is the order shown.
 */
const menuItems = [
    { id: 'general', icon: Settings, group: 'menu_group_project' },
    { id: 'appearance', icon: Palette, group: 'menu_group_project' },
    { id: 'environment', icon: ToggleLeft, group: 'menu_group_application' },
    { id: 'authentication', icon: Lock, group: 'menu_group_application' },
    { id: 'mail', icon: Mail, group: 'menu_group_application' },
    { id: 'broadcasting', icon: Send, group: 'menu_group_application' },
    { id: 'notifications', icon: Bell, group: 'menu_group_application' },
    { id: 'data', icon: Database, group: 'menu_group_application' },
    { id: 'deployment', icon: Rocket, group: 'menu_group_delivery' },
    { id: 'databases', icon: Database, group: 'menu_group_delivery' },
]
    // Labels come from SECTION_LABELS so the sidebar and the command palette name a
    // section identically.
    .map((item) => ({ ...item, label: SECTION_LABELS[item.id] }));

const menuGroups = computed(() =>
    [...new Set(menuItems.map((m) => m.group))].map((group) => ({
        group,
        items: menuItems.filter((m) => m.group === group),
    })),
);

const sectionOf = (id: string) => menuItems.find((m) => m.id === id);

const validSections = menuItems.map((m) => m.id);

/**
 * The hash names either a section (`#environment`) or a single card
 * (`#env-toggles`) — the command palette links straight to a card, and the card's
 * section is looked up from the same index the search uses rather than encoded in
 * the URL, so there is one place that knows where a setting lives.
 */
const resolveHash = (): { section: string; anchor: string | null } => {
    const hash = (typeof window !== 'undefined' ? window.location.hash : '').replace(/^#/, '');

    if (validSections.includes(hash)) {
        return { section: hash, anchor: null };
    }

    const entry = settingsIndex.find((e) => e.anchor === hash);

    return entry ? { section: entry.section, anchor: entry.anchor } : { section: 'general', anchor: null };
};

const activeSection = ref(resolveHash().section);

/**
 * Scroll a card into view and ring it for two seconds.
 *
 * Applied to the element rather than bound as a prop: the card belongs to a child
 * section component that knows nothing about search or deep links.
 */
const focusAnchor = async (anchor: string) => {
    await nextTick();

    const el = document.getElementById(`setting-${anchor}`);
    if (!el) {
        return;
    }

    el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const ring = ['ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-background'];
    el.classList.add(...ring);
    window.setTimeout(() => el.classList.remove(...ring), 2000);
};

const applyHash = () => {
    const { section, anchor } = resolveHash();
    activeSection.value = section;

    if (anchor) {
        focusAnchor(anchor);
    }
};

if (typeof window !== 'undefined') {
    // Arriving from the palette while already on this page changes only the hash, so
    // the landing is handled here as well as on mount.
    window.addEventListener('hashchange', applyHash);
    onBeforeUnmount(() => window.removeEventListener('hashchange', applyHash));

    onMounted(applyHash);

    watch(activeSection, (val: string) => {
        // Only rewrite a hash that names a section. A card anchor is left alone so a
        // deep link stays copyable, and so this does not fight `applyHash`.
        const current = window.location.hash.replace(/^#/, '');

        if (current !== val && !settingsIndex.some((e) => e.anchor === current)) {
            history.replaceState(null, '', `#${val}`);
        }
    });
}

/**
 * Search across every card in every section, not just the open one.
 *
 * Sections are `v-if`'d, so the results list replaces the section rather than filtering
 * it in place: the cards being searched are, for eight sections out of nine, not in the
 * DOM at all. Picking a result is what mounts the section and scrolls to the card.
 */
const search = ref('');
const searching = computed(() => search.value.trim().length > 0);
const results = computed(() => searchSettings(search.value, (key: string) => t(key)));

const goToSetting = (entry: SettingEntry) => {
    search.value = '';
    activeSection.value = entry.section;
    focusAnchor(entry.anchor);
};
</script>

<template>
    <Head :title="t('developer_settings')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] gap-6 px-4 py-10 text-start md:py-20">
            <!-- Sidebar -->
            <aside class="sticky top-20 hidden h-fit w-64 shrink-0 rounded-2xl border bg-card p-3 lg:block">
                <div class="mb-2 flex items-center gap-3 px-3 py-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent">
                        <Hammer class="size-4 text-accent-foreground" />
                    </div>
                    <span class="text-sm font-semibold text-foreground">{{ t('developer_settings') }}</span>
                </div>

                <!-- Search lives in the nav, next to the list of places it can send you. -->
                <div class="mb-3 px-1">
                    <SettingsSearch v-model="search" @submit="results[0] && goToSetting(results[0])" />
                </div>

                <nav class="space-y-4" :aria-label="t('developer_settings')">
                    <div v-for="group in menuGroups" :key="group.group" class="space-y-1">
                        <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ t(group.group) }}
                        </p>
                        <button
                            v-for="item in group.items"
                            :key="item.id"
                            type="button"
                            :aria-current="activeSection === item.id && !searching ? 'page' : undefined"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            :class="
                                activeSection === item.id && !searching
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                            @click="
                                search = '';
                                activeSection = item.id;
                            "
                        >
                            <component :is="item.icon" class="size-4" />
                            {{ t(item.label) }}
                        </button>
                    </div>
                </nav>
            </aside>

            <!-- Mobile Menu -->
            <div class="fixed start-4 end-4 z-50 lg:hidden" :class="isTesting ? 'bottom-16' : 'bottom-4'">
                <div class="space-y-2 rounded-2xl border bg-card/95 p-2 shadow-lg backdrop-blur-sm">
                    <SettingsSearch v-model="search" @submit="results[0] && goToSetting(results[0])" />

                    <nav class="flex gap-1 overflow-x-auto" :aria-label="t('developer_settings')">
                        <button
                            v-for="item in menuItems"
                            :key="item.id"
                            type="button"
                            :aria-current="activeSection === item.id ? 'page' : undefined"
                            class="flex shrink-0 flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            :class="activeSection === item.id && !searching ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                            @click="
                                search = '';
                                activeSection = item.id;
                            "
                        >
                            <component :is="item.icon" class="size-4" />
                            <span class="whitespace-nowrap">{{ t(item.label) }}</span>
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1 space-y-5 pb-28 lg:pb-0">
                <!-- Just the heading: the search field lives in the nav, beside the list
                     of sections it jumps to. -->
                <div class="flex items-center gap-3 rounded-2xl border bg-card p-4">
                    <component :is="searching ? Search : sectionOf(activeSection)?.icon || Settings" class="size-5 shrink-0 text-primary" />
                    <h1 class="truncate text-lg font-semibold text-foreground">
                        {{ searching ? t('search_results') : t(sectionOf(activeSection)?.label || 'general_settings') }}
                    </h1>
                </div>

                <!-- Results replace the section while a query is present. -->
                <div v-if="searching" class="space-y-2">
                    <button
                        v-for="entry in results"
                        :key="`${entry.section}-${entry.anchor}`"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border bg-card p-4 text-start transition-colors hover:border-primary/40 hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        @click="goToSetting(entry)"
                    >
                        <component :is="sectionOf(entry.section)?.icon || Settings" class="size-5 shrink-0 text-primary" />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-foreground">{{ t(entry.label) }}</span>
                            <span class="block truncate text-xs text-muted-foreground">{{ t(sectionOf(entry.section)?.label || '') }}</span>
                        </span>
                    </button>

                    <p v-if="results.length === 0" class="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {{ t('no_settings_found') }}
                    </p>
                </div>

                <GeneralSection v-else-if="activeSection === 'general'" :app-name="appName" />

                <AppearanceSection v-else-if="activeSection === 'appearance'" :light-colors="lightColors" :dark-colors="darkColors" />

                <EnvironmentSection
                    v-else-if="activeSection === 'environment'"
                    :env-toggles="envToggles"
                    :env-values="envValues"
                    :base-testing="baseTesting"
                    :urls="urls"
                    :protected-pages-config="protectedPagesConfig"
                />

                <AuthenticationSection
                    v-else-if="activeSection === 'authentication'"
                    :auth-config="authConfig"
                    :social-auth-config="socialAuthConfig"
                    :admin-credentials="adminCredentials"
                    :api-token="apiToken"
                    :reviewer-accounts="reviewerAccounts"
                    :sessions-config="sessionsConfig"
                />

                <MailSection v-else-if="activeSection === 'mail'" :local-mail="localMail" :base-mail="baseMail" />

                <BroadcastingSection v-else-if="activeSection === 'broadcasting'" :pusher-config="pusherConfig" />

                <NotificationsSection
                    v-else-if="activeSection === 'notifications'"
                    :firebase-config-exists="firebaseConfigExists"
                    :firebase-credentials-path="firebaseCredentialsPath"
                    :base-firebase-exists="baseFirebaseExists"
                    :topics-config="topicsConfig"
                />

                <DataLimitsSection
                    v-else-if="activeSection === 'data'"
                    :validation-config="validationConfig"
                    :rate-limit-config="rateLimitConfig"
                    :account-deletion-config="accountDeletionConfig"
                />

                <DatabasesSection v-else-if="activeSection === 'databases'" />

                <DeploymentSection
                    v-else-if="activeSection === 'deployment'"
                    :git="git"
                    :deploy-config="deployConfig"
                    :deploy-log="deployLog"
                    :available-seeders="availableSeeders"
                    :base-mail="baseMail"
                    :pusher-config="pusherConfig"
                    :urls="urls"
                    :hostinger-configured="hostingerConfigured"
                />
            </div>
        </div>
    </div>
</template>
