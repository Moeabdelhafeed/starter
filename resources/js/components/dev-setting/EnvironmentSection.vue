<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ToggleLeft, Link2, Loader2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import LocalBaseGrid from '@/components/dev-setting/LocalBaseGrid.vue';

const { t } = useI18n();

const props = defineProps({
    envToggles: Array,
    envValues: Object,
    baseTesting: { type: Boolean, default: null },
    urls: Object,
});

// Env toggles
const envToggling = ref({});
const toggleEnv = (key, currentValue) => {
    envToggling.value[key] = true;
    const newValue = !currentValue;
    router.post(route('dev_settings.env'), {
        key: key,
        value: newValue,
        _method: 'PUT',
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['envValues', 'success', 'error'],
        onFinish: () => { envToggling.value[key] = false; },
    });
};

const prodEnvToggling = ref({});
const toggleProdEnv = (key, currentValue) => {
    prodEnvToggling.value[key] = true;
    router.post(route('dev_settings.production_env'), {
        key: key,
        value: !currentValue,
        _method: 'PUT',
    }, {
        preserveScroll: true,
        onFinish: () => { prodEnvToggling.value[key] = false; },
    });
};

const envLabel = (key) => {
    const labels = {
        'APP_USERS': 'App Users Module',
        'APP_GUESTS': 'App Guests Module',
        'HAS_TRANSLATIONS': 'App Translations',
        'HAS_NOTIFICATION_TEMPLATES': 'Notification Templates',
        'HAS_PAGES': 'Pages',
        'HAS_APP_SETTINGS': 'App Settings',
        'HAS_DYNAMIC_STORAGE': 'Dynamic Storage',
        'HAS_ACTIVITY_LOGS': 'Activity Logs',
        'IS_TESTING': 'Testing Mode',
        'APP_DEBUG': 'Debug Mode',
        'IS_OTP_WHATSAPP': 'OTP via WhatsApp',
    };
    return labels[key] || key;
};

const envDescription = (key) => {
    const desc = {
        'APP_USERS': 'Enable/disable the app users (API guard) module and routes',
        'APP_GUESTS': 'Enable/disable lazy guest user creation in IdentifyDevice middleware. When off, X-Device-Id + X-Platform headers are still required but no guest row is created.',
        'HAS_TRANSLATIONS': 'Enable/disable app translations feature (admin routes, navbar links, API endpoints for translations/languages)',
        'HAS_NOTIFICATION_TEMPLATES': 'Enable/disable the notification templates feature (admin routes and navbar link). Some apps do not need it.',
        'HAS_PAGES': 'Enable/disable the pages feature (admin CRUD, public /p/{slug} page, and API page endpoints).',
        'HAS_APP_SETTINGS': 'Enable/disable the App Settings feature (social/contact/store-link blocks admin CRUD + the /api/app-settings endpoint).',
        'HAS_DYNAMIC_STORAGE': 'Enable/disable the Dynamic Storage feature (keyed media store admin CRUD + the /api/media upload & fetch endpoints).',
        'HAS_ACTIVITY_LOGS': 'Enable/disable the activity logs admin feature (routes + navbar link). Models still record logs; only the admin viewer is hidden.',
        'IS_TESTING': 'Enable/disable testing mode for the application',
        'APP_DEBUG': 'Enable/disable detailed error pages and debug info',
        'IS_OTP_WHATSAPP': 'If true, OTPs sent via WhatsApp. If false, sent via SMS. Only applies when identifier is phone.',
    };
    return desc[key] || '';
};

// URLs
const localUrlsForm = useForm({
    target: 'local',
    APP_URL: props.urls?.local?.APP_URL || 'http://localhost',
    FRONTEND_URL: props.urls?.local?.FRONTEND_URL || 'http://localhost:5173',
});
const baseUrlsForm = useForm({
    target: 'production',
    APP_URL: props.urls?.base?.APP_URL || '',
    FRONTEND_URL: props.urls?.base?.FRONTEND_URL || '',
});

const submitLocalUrls = () => {
    localUrlsForm.put(route('dev_settings.urls'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['urls', 'success', 'error'],
    });
};
const submitBaseUrls = () => {
    baseUrlsForm.put(route('dev_settings.urls'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['urls', 'success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Environment Toggles -->
        <SettingsCard :title="t('env_toggles')" :description="t('env_toggles_desc')">
            <template #icon><ToggleLeft class="size-5 text-violet-500" /></template>

            <div class="divide-y divide-border">
                <div v-for="key in envToggles" :key="key" class="flex items-center justify-between py-4">
                    <div>
                        <p class="text-sm font-medium text-foreground">{{ envLabel(key) }}</p>
                        <p class="text-xs text-muted-foreground">{{ envDescription(key) }}</p>
                        <p class="mt-1 font-mono text-xs text-muted-foreground">{{ key }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <!-- Local toggle -->
                        <div v-if="key === 'IS_TESTING'" class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ t('local') }}</span>
                        </div>
                        <button
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :class="envValues[key] ? 'bg-primary' : 'bg-muted'"
                            :disabled="envToggling[key]"
                            @click="toggleEnv(key, envValues[key])">
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="envValues[key] ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'" />
                        </button>
                        <!-- Base toggle (IS_TESTING only) -->
                        <template v-if="key === 'IS_TESTING' && baseTesting !== null">
                            <div class="flex items-center gap-2 border-s border-border ps-4">
                                <span class="text-xs text-muted-foreground">{{ t('base') }}</span>
                            </div>
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                :class="baseTesting ? 'bg-primary' : 'bg-muted'"
                                :disabled="prodEnvToggling['IS_TESTING']"
                                @click="toggleProdEnv('IS_TESTING', baseTesting)">
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="baseTesting ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </SettingsCard>

        <!-- App URLs -->
        <SettingsCard :title="t('urls')" :description="t('urls_desc')">
            <template #icon><Link2 class="size-5 text-blue-500" /></template>

            <LocalBaseGrid>
                <template #local>
                    <form @submit.prevent="submitLocalUrls" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('app_url') }}</label>
                            <Input v-model="localUrlsForm.APP_URL" type="url" placeholder="http://localhost" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('frontend_url') }}</label>
                            <Input v-model="localUrlsForm.FRONTEND_URL" type="url"
                                placeholder="http://localhost:5173" />
                        </div>
                        <Button type="submit" :disabled="localUrlsForm.processing">
                            <Loader2 v-if="localUrlsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ localUrlsForm.processing ? t('saving') : t('save_urls') }}
                        </Button>
                    </form>
                </template>
                <template #base>
                    <form @submit.prevent="submitBaseUrls" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('app_url') }}</label>
                            <Input v-model="baseUrlsForm.APP_URL" type="url" placeholder="https://yourdomain.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('frontend_url') }}</label>
                            <Input v-model="baseUrlsForm.FRONTEND_URL" type="url"
                                placeholder="https://app.yourdomain.com" />
                        </div>
                        <Button type="submit" :disabled="baseUrlsForm.processing">
                            <Loader2 v-if="baseUrlsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ baseUrlsForm.processing ? t('saving') : t('save_urls') }}
                        </Button>
                    </form>
                </template>
            </LocalBaseGrid>
        </SettingsCard>
    </div>
</template>
