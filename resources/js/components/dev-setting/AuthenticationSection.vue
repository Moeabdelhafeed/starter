<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Loader2, ShieldCheck, KeyRound, RefreshCw, Shield } from 'lucide-vue-next';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import LocalBaseGrid from '@/components/dev-setting/LocalBaseGrid.vue';

const { t } = useI18n();

const props = defineProps({
    authConfig: Object,
    socialAuthConfig: Object,
    adminCredentials: Object,
    apiToken: Object,
    reviewerAccounts: Object,
    sessionsConfig: Object,
});

// Auth Config
const authForm = useForm({
    identifiers: props.authConfig?.identifiers || ['email'],
    has_email_field: props.authConfig?.has_email_field ?? true,
    has_phone_field: props.authConfig?.has_phone_field ?? false,
    has_username_field: props.authConfig?.has_username_field ?? false,
    auth_mode: props.authConfig?.auth_mode || 'password',
});

const toggleIdentifier = (value) => {
    const idx = authForm.identifiers.indexOf(value);
    if (idx > -1) {
        if (authForm.identifiers.length > 1) {
            authForm.identifiers.splice(idx, 1);
        }
    } else {
        authForm.identifiers.push(value);
    }
};

const submitAuth = () => {
    authForm.put(route('dev_settings.auth'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['authConfig', 'success', 'error'],
    });
};

// Social Auth Config
const availableProviders = [
    { id: 'google.com', name: 'Google' },
    { id: 'apple.com', name: 'Apple' },
    { id: 'facebook.com', name: 'Facebook' },
    { id: 'twitter.com', name: 'Twitter' },
    { id: 'github.com', name: 'GitHub' },
];

const socialAuthForm = useForm({
    providers: props.socialAuthConfig?.providers || [],
    max_accounts: props.socialAuthConfig?.max_accounts ?? 0,
});

const toggleProvider = (providerId) => {
    const idx = socialAuthForm.providers.indexOf(providerId);
    if (idx > -1) {
        socialAuthForm.providers.splice(idx, 1);
    } else {
        socialAuthForm.providers.push(providerId);
    }
};

const submitSocialAuth = () => {
    socialAuthForm.put(route('dev_settings.social_auth'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['socialAuthConfig', 'success', 'error'],
    });
};

// Admin Credentials
const localAdminForm = useForm({
    target: 'local',
    ADMIN_EMAIL: props.adminCredentials?.local?.ADMIN_EMAIL || '',
    ADMIN_PASSWORD: props.adminCredentials?.local?.ADMIN_PASSWORD || '',
});
const prodAdminForm = useForm({
    target: 'production',
    ADMIN_EMAIL: props.adminCredentials?.base?.ADMIN_EMAIL || '',
    ADMIN_PASSWORD: props.adminCredentials?.base?.ADMIN_PASSWORD || '',
});

const submitLocalAdmin = () => {
    localAdminForm.put(route('dev_settings.admin_credentials'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['adminCredentials', 'success', 'error'],
    });
};
const submitProdAdmin = () => {
    prodAdminForm.put(route('dev_settings.admin_credentials'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['adminCredentials', 'success', 'error'],
    });
};

// API Token
const tokenGenerating = ref('');
const generateApiToken = (target) => {
    tokenGenerating.value = target;
    router.post(route('dev_settings.api_token'), { target }, {
        preserveScroll: true,
        onFinish: () => { tokenGenerating.value = ''; },
    });
};

// Reviewer accounts (App Store + Play Store reviewers)
const reviewerForm = useForm({
    apple: {
        email: props.reviewerAccounts?.apple?.email || '',
        password: props.reviewerAccounts?.apple?.password || '',
    },
    google: {
        email: props.reviewerAccounts?.google?.email || '',
        password: props.reviewerAccounts?.google?.password || '',
    },
    appgallery: {
        email: props.reviewerAccounts?.appgallery?.email || '',
        password: props.reviewerAccounts?.appgallery?.password || '',
    },
});

const submitReviewerAccounts = () => {
    reviewerForm.put(route('dev_settings.reviewer_accounts'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['reviewerAccounts', 'success', 'error'],
    });
};

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard?.writeText(text);
};

// Multi-session toggle
const sessionsForm = useForm({
    multi_session_enabled: Boolean(props.sessionsConfig?.multi_session_enabled),
});

const submitSessions = () => {
    sessionsForm.put(route('dev_settings.sessions'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['sessionsConfig', 'success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Auth Configuration -->
        <SettingsCard :title="t('auth_config')" :description="t('auth_config_desc')">
            <template #icon><ShieldCheck class="size-5 text-violet-500" /></template>

            <form @submit.prevent="submitAuth" class="space-y-6">
                <!-- Identifier Selection -->
                <div>
                    <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('login_identifiers') }}</h3>
                    <p class="mb-3 text-xs text-muted-foreground">{{ t('login_identifiers_desc') }}</p>
                    <div class="flex w-max gap-2 rounded-xl bg-muted p-2">
                        <Button type="button" @click="toggleIdentifier('email')"
                            :class="authForm.identifiers.includes('email') ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                            {{ t('email') }}
                        </Button>
                        <Button type="button" @click="toggleIdentifier('phone')"
                            :class="authForm.identifiers.includes('phone') ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                            {{ t('phone') }}
                        </Button>
                    </div>
                </div>

                <!-- Additional Fields -->
                <div>
                    <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('additional_fields') }}</h3>
                    <p class="mb-3 text-xs text-muted-foreground">{{ t('additional_fields_desc') }}</p>
                    <div class="flex flex-wrap gap-6">
                        <label v-if="!authForm.identifiers.includes('email')"
                            class="flex items-center gap-2 text-sm text-foreground">
                            <Checkbox v-model="authForm.has_email_field" />
                            {{ t('email') }}
                        </label>
                        <label v-if="!authForm.identifiers.includes('phone')"
                            class="flex items-center gap-2 text-sm text-foreground">
                            <Checkbox v-model="authForm.has_phone_field" />
                            {{ t('phone') }}
                        </label>
                        <label class="flex flex-col gap-1 text-sm text-foreground">
                            <span class="flex items-center gap-2">
                                <Checkbox v-model="authForm.has_username_field" />
                                {{ t('username') }}
                            </span>
                            <span class="ms-7 text-xs text-muted-foreground">{{ t('username_login_alias_note') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Login Method -->
                <div>
                    <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('login_method') }}</h3>
                    <p class="mb-3 text-xs text-muted-foreground">{{ t('login_method_desc') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <label
                            class="flex flex-1 cursor-pointer items-start gap-3 rounded-xl border bg-muted/30 p-4"
                            :class="authForm.auth_mode === 'password' ? 'border-primary bg-primary/5' : ''"
                        >
                            <input
                                type="radio"
                                value="password"
                                v-model="authForm.auth_mode"
                                class="mt-1 size-4 cursor-pointer accent-primary"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-foreground">{{ t('login_with_password') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ t('login_with_password_desc') }}</p>
                            </div>
                        </label>
                        <label
                            class="flex flex-1 cursor-pointer items-start gap-3 rounded-xl border bg-muted/30 p-4"
                            :class="authForm.auth_mode === 'otp' ? 'border-primary bg-primary/5' : ''"
                        >
                            <input
                                type="radio"
                                value="otp"
                                v-model="authForm.auth_mode"
                                class="mt-1 size-4 cursor-pointer accent-primary"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-foreground">{{ t('login_with_otp') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ t('login_with_otp_desc') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <Button type="submit" :disabled="authForm.processing">
                        <Loader2 v-if="authForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ authForm.processing ? t('saving') : t('save_auth_config') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>

        <!-- Social Auth Configuration -->
        <SettingsCard :title="t('social_auth_config')" :description="t('social_auth_config_desc')">
            <template #icon><ShieldCheck class="size-5 text-pink-500" /></template>

            <form @submit.prevent="submitSocialAuth" class="space-y-6">
                <!-- Allowed Providers -->
                <div>
                    <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('allowed_providers') }}</h3>
                    <p class="mb-3 text-xs text-muted-foreground">{{ t('allowed_providers_desc') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <Button v-for="provider in availableProviders" :key="provider.id" type="button"
                            @click="toggleProvider(provider.id)"
                            :class="socialAuthForm.providers.includes(provider.id) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                            {{ provider.name }}
                        </Button>
                    </div>
                </div>

                <!-- Max Social Accounts -->
                <div>
                    <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('max_social_accounts') }}</h3>
                    <p class="mb-3 text-xs text-muted-foreground">{{ t('max_social_accounts_desc') }}</p>
                    <div class="flex w-max gap-2 rounded-xl bg-muted p-2">
                        <Button type="button" @click="socialAuthForm.max_accounts = 1"
                            :class="socialAuthForm.max_accounts === 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                            {{ t('one_account') }}
                        </Button>
                        <Button type="button" @click="socialAuthForm.max_accounts = 0"
                            :class="socialAuthForm.max_accounts === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                            {{ t('unlimited') }}
                        </Button>
                    </div>
                </div>

                <div class="pt-2">
                    <Button type="submit" :disabled="socialAuthForm.processing">
                        <Loader2 v-if="socialAuthForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ socialAuthForm.processing ? t('saving') : t('save_social_auth_config') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>

        <!-- Admin Credentials -->
        <SettingsCard :title="t('admin_credentials')" :description="t('admin_credentials_desc')">
            <template #icon><KeyRound class="size-5 text-rose-500" /></template>

            <LocalBaseGrid>
                <template #local>
                    <form @submit.prevent="submitLocalAdmin" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_email') }}</label>
                            <Input v-model="localAdminForm.ADMIN_EMAIL" type="email" placeholder="admin@example.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_password') }}</label>
                            <Input v-model="localAdminForm.ADMIN_PASSWORD" type="password" placeholder="••••••••" />
                        </div>
                        <Button type="submit" :disabled="localAdminForm.processing">
                            <Loader2 v-if="localAdminForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ localAdminForm.processing ? t('saving') : t('save_admin') }}
                        </Button>
                    </form>
                </template>
                <template #base>
                    <form @submit.prevent="submitProdAdmin" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_email') }}</label>
                            <Input v-model="prodAdminForm.ADMIN_EMAIL" type="email" placeholder="admin@example.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_password') }}</label>
                            <Input v-model="prodAdminForm.ADMIN_PASSWORD" type="password" placeholder="••••••••" />
                        </div>
                        <Button type="submit" :disabled="prodAdminForm.processing">
                            <Loader2 v-if="prodAdminForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ prodAdminForm.processing ? t('saving') : t('save_admin') }}
                        </Button>
                    </form>
                </template>
            </LocalBaseGrid>
        </SettingsCard>

        <!-- API Token -->
        <SettingsCard :title="t('api_token')" :description="t('api_token_desc')">
            <template #icon><RefreshCw class="size-5 text-cyan-500" /></template>

            <LocalBaseGrid>
                <template #local>
                    <code
                        class="block break-all rounded-lg bg-muted p-3 text-xs text-muted-foreground">{{ apiToken?.local || '—' }}</code>
                    <Button :disabled="!!tokenGenerating" @click="generateApiToken('local')">
                        <Loader2 v-if="tokenGenerating === 'local'" class="me-2 h-4 w-4 animate-spin" />
                        <RefreshCw v-else class="me-2 size-4" />
                        {{ tokenGenerating === 'local' ? t('generating') : t('generate_local') }}
                    </Button>
                </template>
                <template #base>
                    <code
                        class="block break-all rounded-lg bg-muted p-3 text-xs text-muted-foreground">{{ apiToken?.base || '—' }}</code>
                    <Button :disabled="!!tokenGenerating" @click="generateApiToken('production')">
                        <Loader2 v-if="tokenGenerating === 'production'" class="me-2 h-4 w-4 animate-spin" />
                        <RefreshCw v-else class="me-2 size-4" />
                        {{ tokenGenerating === 'production' ? t('generating') : t('generate_base') }}
                    </Button>
                </template>
            </LocalBaseGrid>

            <!-- Generate Both -->
            <div class="mt-4 border-t border-border pt-4">
                <Button variant="outline" :disabled="!!tokenGenerating" @click="generateApiToken('both')">
                    <Loader2 v-if="tokenGenerating === 'both'" class="me-2 h-4 w-4 animate-spin" />
                    <RefreshCw v-else class="me-2 size-4" />
                    {{ tokenGenerating === 'both' ? t('generating') : t('generate_both') }}
                </Button>
            </div>
        </SettingsCard>

        <!-- Reviewer Accounts (App Store + Play Store reviewers) -->
        <SettingsCard :title="t('reviewer_accounts')" :description="t('reviewer_accounts_desc')">
            <template #icon><Shield class="size-5 text-blue-500" /></template>

            <form @submit.prevent="submitReviewerAccounts" class="space-y-6">
                <div v-for="slot in ['apple', 'google', 'appgallery']" :key="slot" class="rounded-xl border bg-muted/30 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-foreground">{{ t(slot + '_reviewer') }}</h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copyToClipboard(`${reviewerForm[slot].email}\n${reviewerForm[slot].password}`)"
                            :disabled="!reviewerForm[slot].email || !reviewerForm[slot].password"
                        >
                            {{ t('copy_credentials') }}
                        </Button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-foreground">{{ t('email') }}</label>
                            <Input v-model="reviewerForm[slot].email" type="email" placeholder="reviewer@yourapp.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-foreground">{{ t('password') }}</label>
                            <Input v-model="reviewerForm[slot].password" type="text" placeholder="••••••••" />
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <Button type="submit" :disabled="reviewerForm.processing">
                        <Loader2 v-if="reviewerForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ reviewerForm.processing ? t('saving') : t('save_reviewer_accounts') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>

        <!-- Sessions / Multi-Device -->
        <SettingsCard :title="t('sessions_settings')" :description="t('sessions_settings_desc')">
            <template #icon><KeyRound class="size-5 text-emerald-500" /></template>

            <form @submit.prevent="submitSessions" class="space-y-6">
                <label class="flex cursor-pointer items-start gap-4 rounded-xl border bg-muted/30 p-4">
                    <input
                        type="checkbox"
                        v-model="sessionsForm.multi_session_enabled"
                        class="mt-1 size-4 cursor-pointer accent-primary"
                    />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-foreground">{{ t('multi_session_enabled') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ sessionsForm.multi_session_enabled ? t('multi_session_desc') : t('single_session_desc') }}
                        </p>
                    </div>
                </label>

                <div class="pt-2">
                    <Button type="submit" :disabled="sessionsForm.processing">
                        <Loader2 v-if="sessionsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ sessionsForm.processing ? t('saving') : t('save_sessions_config') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>
    </div>
</template>
