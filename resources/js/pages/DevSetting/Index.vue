<script setup>
import Default from '@/layouts/default.vue';
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Hammer, Send, Database, Rocket, Mail, Bell, Lock, Settings, Palette, ToggleLeft } from 'lucide-vue-next';

import GeneralSection from '@/components/dev-setting/GeneralSection.vue';
import AppearanceSection from '@/components/dev-setting/AppearanceSection.vue';
import EnvironmentSection from '@/components/dev-setting/EnvironmentSection.vue';
import AuthenticationSection from '@/components/dev-setting/AuthenticationSection.vue';
import MailSection from '@/components/dev-setting/MailSection.vue';
import BroadcastingSection from '@/components/dev-setting/BroadcastingSection.vue';
import NotificationsSection from '@/components/dev-setting/NotificationsSection.vue';
import DataLimitsSection from '@/components/dev-setting/DataLimitsSection.vue';
import DeploymentSection from '@/components/dev-setting/DeploymentSection.vue';

defineOptions({ layout: Default });

const { t } = useI18n();

const props = defineProps({
    lightColors: Object,
    darkColors: Object,
    envValues: Object,
    envToggles: Array,
    firebaseConfigExists: Boolean,
    firebaseCredentialsPath: String,
    baseFirebaseExists: Boolean,
    authConfig: Object,
    socialAuthConfig: Object,
    validationConfig: Object,
    pusherConfig: Object,
    rateLimitConfig: Object,
    accountDeletionConfig: Object,
    sessionsConfig: Object,
    topicsConfig: Object,
    reviewerAccounts: Object,
    git: Object,
    baseDb: Object,
    baseMail: Object,
    localMail: Object,
    baseTesting: { type: Boolean, default: null },
    urls: Object,
    deployConfig: Object,
    deployLog: { type: String, default: null },
    adminCredentials: Object,
    apiToken: Object,
    appName: String,
});

// Sidebar navigation — grouped into logical sections.
const menuItems = [
    { id: 'general', icon: Settings, label: 'general_settings' },
    { id: 'appearance', icon: Palette, label: 'appearance' },
    { id: 'environment', icon: ToggleLeft, label: 'environment' },
    { id: 'authentication', icon: Lock, label: 'authentication' },
    { id: 'mail', icon: Mail, label: 'mail_settings' },
    { id: 'broadcasting', icon: Send, label: 'broadcasting' },
    { id: 'notifications', icon: Bell, label: 'fcm_notifications' },
    { id: 'data', icon: Database, label: 'data_and_limits' },
    { id: 'deployment', icon: Rocket, label: 'deployment' },
];

const validSections = menuItems.map((m) => m.id);

const sectionFromHash = () => {
    const hash = (typeof window !== 'undefined' ? window.location.hash : '').replace(/^#/, '');
    return validSections.includes(hash) ? hash : 'general';
};

const activeSection = ref(sectionFromHash());

if (typeof window !== 'undefined') {
    window.addEventListener('hashchange', () => {
        activeSection.value = sectionFromHash();
    });

    watch(activeSection, (val) => {
        if (window.location.hash.replace(/^#/, '') !== val) {
            history.replaceState(null, '', `#${val}`);
        }
    });
}
</script>

<template>

    <Head :title="t('developer_settings')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] gap-6 px-4 py-10 md:py-20 text-start">
            <!-- Sidebar -->
            <aside class="sticky top-20 h-fit w-64 shrink-0 rounded-2xl border bg-card p-3 hidden lg:block">
                <div class="flex items-center gap-3 px-3 py-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent">
                        <Hammer class="size-4 text-accent-foreground" />
                    </div>
                    <span class="font-semibold text-foreground text-sm">{{ t('developer_settings') }}</span>
                </div>
                <nav class="space-y-1">
                    <button v-for="item in menuItems" :key="item.id" @click="activeSection = item.id"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors" :class="activeSection === item.id
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                        <component :is="item.icon" class="size-4" />
                        {{ t(item.label) }}
                    </button>
                </nav>
            </aside>

            <!-- Mobile Menu -->
            <div class="lg:hidden fixed bottom-4 start-4 end-4 z-50">
                <div class="rounded-2xl border bg-card/95 backdrop-blur-sm p-2 shadow-lg">
                    <div class="flex gap-1 overflow-x-auto">
                        <button v-for="item in menuItems" :key="item.id" @click="activeSection = item.id"
                            class="flex shrink-0 flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs transition-colors"
                            :class="activeSection === item.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground'">
                            <component :is="item.icon" class="size-4" />
                            <span class="whitespace-nowrap">{{ t(item.label) }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 space-y-5 pb-20 lg:pb-0">
                <!-- Section Header -->
                <div class="flex items-center gap-3 rounded-2xl border bg-card p-4">
                    <component :is="menuItems.find(m => m.id === activeSection)?.icon || Settings"
                        class="size-5 text-primary" />
                    <h1 class="text-lg font-semibold text-foreground">
                        {{ t(menuItems.find(m => m.id === activeSection)?.label || 'general_settings') }}
                    </h1>
                </div>

                <GeneralSection v-if="activeSection === 'general'" :app-name="appName" />

                <AppearanceSection v-else-if="activeSection === 'appearance'"
                    :light-colors="lightColors" :dark-colors="darkColors" />

                <EnvironmentSection v-else-if="activeSection === 'environment'"
                    :env-toggles="envToggles" :env-values="envValues" :base-testing="baseTesting" :urls="urls" />

                <AuthenticationSection v-else-if="activeSection === 'authentication'"
                    :auth-config="authConfig" :social-auth-config="socialAuthConfig"
                    :admin-credentials="adminCredentials" :api-token="apiToken"
                    :reviewer-accounts="reviewerAccounts" :sessions-config="sessionsConfig" />

                <MailSection v-else-if="activeSection === 'mail'"
                    :local-mail="localMail" :base-mail="baseMail" />

                <BroadcastingSection v-else-if="activeSection === 'broadcasting'"
                    :pusher-config="pusherConfig" />

                <NotificationsSection v-else-if="activeSection === 'notifications'"
                    :firebase-config-exists="firebaseConfigExists"
                    :firebase-credentials-path="firebaseCredentialsPath"
                    :base-firebase-exists="baseFirebaseExists" :topics-config="topicsConfig" />

                <DataLimitsSection v-else-if="activeSection === 'data'"
                    :base-db="baseDb" :validation-config="validationConfig"
                    :rate-limit-config="rateLimitConfig" :account-deletion-config="accountDeletionConfig" />

                <DeploymentSection v-else-if="activeSection === 'deployment'"
                    :git="git" :deploy-config="deployConfig" :deploy-log="deployLog"
                    :base-mail="baseMail" :pusher-config="pusherConfig" :urls="urls" />
            </div>
        </div>
    </div>
</template>
