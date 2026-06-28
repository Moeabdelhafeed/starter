<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Loader2, Hammer, Download, ImageIcon, Clipboard, Check, Settings } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';

const { t } = useI18n();

const props = defineProps({
    appName: String,
});

// App Name
const appNameForm = useForm({
    APP_NAME: props.appName || '',
});
const submitAppName = () => {
    appNameForm.put(route('dev_settings.app_name'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['appName', 'success', 'error'],
    });
};

// Branding
const logoForm = useForm({ logo: null });
const faviconForm = useForm({ favicon: null });
const logoCopied = ref(false);

const uploadLogo = () => {
    logoForm.post(route('dev_settings.logo'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { logoForm.reset(); },
    });
};

const uploadFavicon = () => {
    faviconForm.post(route('dev_settings.favicon'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { faviconForm.reset(); },
    });
};

const copyLogoPath = async () => {
    await navigator.clipboard.writeText(window.location.origin + '/images/logo.png');
    logoCopied.value = true;
    setTimeout(() => { logoCopied.value = false; }, 2000);
};

// Build
const building = ref(false);
const triggerBuild = () => {
    building.value = true;
    router.post(route('dev_settings.build'), {}, {
        preserveScroll: true,
        onFinish: () => { building.value = false; },
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- App Name -->
        <SettingsCard :title="t('app_name')">
            <template #icon><Settings class="size-5 text-primary" /></template>
            <form @submit.prevent="submitAppName" class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                <Input v-model="appNameForm.APP_NAME" type="text" placeholder="My App" class="flex-1" />
                <Button type="submit" :disabled="appNameForm.processing" class="w-full sm:w-auto">
                    <Loader2 v-if="appNameForm.processing" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('save') }}
                </Button>
            </form>
        </SettingsCard>

        <!-- Branding -->
        <SettingsCard :title="t('branding')" :description="t('branding_desc')">
            <template #icon><ImageIcon class="size-5 text-primary" /></template>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Logo -->
                <div class="space-y-4">
                    <h3 class="text-sm font-medium text-foreground">{{ t('logo') }}</h3>
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-16 w-40 items-center justify-center overflow-hidden rounded-lg border bg-muted/50 p-2">
                            <img :src="'/images/logo.png?' + Date.now()" alt="Logo"
                                class="max-h-full max-w-full object-contain" />
                        </div>
                        <button @click="copyLogoPath"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                            <Check v-if="logoCopied" class="size-4 text-emerald-500" />
                            <Clipboard v-else class="size-4" />
                            {{ logoCopied ? t('copied') : t('copy_logo_path') }}
                        </button>
                    </div>
                    <form @submit.prevent="uploadLogo" class="space-y-3">
                        <ImageUpload v-model="logoForm.logo" :removable="false" :error="logoForm.errors.logo" />
                        <Button type="submit" :disabled="logoForm.processing || !logoForm.logo" class="w-full sm:w-auto">
                            <Loader2 v-if="logoForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ logoForm.processing ? t('uploading') : t('upload_logo') }}
                        </Button>
                    </form>
                </div>

                <!-- Favicon -->
                <div class="space-y-4">
                    <h3 class="text-sm font-medium text-foreground">{{ t('favicon') }}</h3>
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border bg-muted/50 p-2">
                            <img :src="'/favicon.ico?' + Date.now()" alt="Favicon"
                                class="max-h-full max-w-full object-contain" />
                        </div>
                    </div>
                    <form @submit.prevent="uploadFavicon" class="space-y-3">
                        <ImageUpload v-model="faviconForm.favicon" :removable="false" accept="image/*,.ico" :error="faviconForm.errors.favicon" />
                        <Button type="submit" :disabled="faviconForm.processing || !faviconForm.favicon" class="w-full sm:w-auto">
                            <Loader2 v-if="faviconForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ faviconForm.processing ? t('uploading') : t('upload_favicon') }}
                        </Button>
                    </form>
                </div>
            </div>
        </SettingsCard>

        <!-- Actions -->
        <SettingsCard :title="t('actions')">
            <template #icon><Hammer class="size-5 text-primary" /></template>
            <div class="flex flex-wrap items-center gap-3">
                <Button :disabled="building" variant="outline" @click="triggerBuild">
                    <Loader2 v-if="building" class="me-2 h-4 w-4 animate-spin" />
                    <Hammer v-else class="me-2 size-4" />
                    {{ building ? t('building') : t('build_assets') }}
                </Button>
                <a :href="route('dev_settings.postman')" download>
                    <Button variant="outline"
                        class="border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white">
                        <Download class="me-2 size-4" />
                        {{ t('download_postman') }}
                    </Button>
                </a>
            </div>
        </SettingsCard>
    </div>
</template>
