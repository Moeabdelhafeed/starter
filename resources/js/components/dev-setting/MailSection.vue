<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Mail } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import LocalBaseGrid from '@/components/dev-setting/LocalBaseGrid.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';

const { t } = useI18n();

/**
 * The MAIL_* block of one .env file, as DevSettingController::getLocalMail() and
 * ::getProductionMail() build it (every key always present, values may be empty).
 */
type MailConfig = {
    MAIL_MAILER: string;
    MAIL_HOST: string;
    MAIL_PORT: string;
    MAIL_USERNAME: string;
    MAIL_PASSWORD: string;
    MAIL_ENCRYPTION: string;
    MAIL_FROM_ADDRESS: string;
};

const props = defineProps<{
    localMail?: MailConfig;
    baseMail?: MailConfig;
}>();

// Local Mail
const localMailForm = useForm({
    MAIL_MAILER: props.localMail?.MAIL_MAILER || 'smtp',
    MAIL_HOST: props.localMail?.MAIL_HOST || '',
    MAIL_PORT: props.localMail?.MAIL_PORT || '465',
    MAIL_USERNAME: props.localMail?.MAIL_USERNAME || '',
    MAIL_PASSWORD: props.localMail?.MAIL_PASSWORD || '',
    MAIL_ENCRYPTION: props.localMail?.MAIL_ENCRYPTION || 'ssl',
    MAIL_FROM_ADDRESS: props.localMail?.MAIL_FROM_ADDRESS || '',
});

const submitLocalMail = () => {
    localMailForm.put(route('dev_settings.local_mail'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['localMail', 'success', 'error'],
    });
};

// Base Mail
const prodMailForm = useForm({
    MAIL_MAILER: props.baseMail?.MAIL_MAILER || 'smtp',
    MAIL_HOST: props.baseMail?.MAIL_HOST || '',
    MAIL_PORT: props.baseMail?.MAIL_PORT || '465',
    MAIL_USERNAME: props.baseMail?.MAIL_USERNAME || '',
    MAIL_PASSWORD: props.baseMail?.MAIL_PASSWORD || '',
    MAIL_ENCRYPTION: props.baseMail?.MAIL_ENCRYPTION || 'ssl',
    MAIL_FROM_ADDRESS: props.baseMail?.MAIL_FROM_ADDRESS || '',
});

const submitProdMail = () => {
    prodMailForm.put(route('dev_settings.production_mail'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['baseMail', 'success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <SettingsCard anchor="mail" :title="t('mail_settings')" :description="t('mail_settings_desc')">
            <template #icon><Mail class="size-5 text-emerald-500" /></template>

            <LocalBaseGrid>
                <!-- Local (.env) -->
                <template #local>
                    <form @submit.prevent="submitLocalMail" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_mailer') }}</label>
                            <Input v-model="localMailForm.MAIL_MAILER" type="text" placeholder="smtp" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_host') }}</label>
                            <Input v-model="localMailForm.MAIL_HOST" type="text" placeholder="smtp.example.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_port') }}</label>
                            <Input v-model="localMailForm.MAIL_PORT" type="text" placeholder="465" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_encryption') }}</label>
                            <Input v-model="localMailForm.MAIL_ENCRYPTION" type="text" placeholder="ssl" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_username') }}</label>
                            <Input v-model="localMailForm.MAIL_USERNAME" type="text" placeholder="user@example.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_password') }}</label>
                            <Input v-model="localMailForm.MAIL_PASSWORD" type="password" placeholder="••••••••" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_from_address') }}</label>
                            <Input v-model="localMailForm.MAIL_FROM_ADDRESS" type="email" placeholder="no-reply@example.com" />
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="localMailForm.processing">
                                <Loader2 v-if="localMailForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ localMailForm.processing ? t('saving') : t('save_local_mail') }}
                            </Button>
                        </div>
                    </form>
                </template>

                <!-- Base (.env.production) -->
                <template #base>
                    <form @submit.prevent="submitProdMail" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_mailer') }}</label>
                            <Input v-model="prodMailForm.MAIL_MAILER" type="text" placeholder="smtp" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_host') }}</label>
                            <Input v-model="prodMailForm.MAIL_HOST" type="text" placeholder="smtp.example.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_port') }}</label>
                            <Input v-model="prodMailForm.MAIL_PORT" type="text" placeholder="465" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_encryption') }}</label>
                            <Input v-model="prodMailForm.MAIL_ENCRYPTION" type="text" placeholder="ssl" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_username') }}</label>
                            <Input v-model="prodMailForm.MAIL_USERNAME" type="text" placeholder="user@example.com" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_password') }}</label>
                            <Input v-model="prodMailForm.MAIL_PASSWORD" type="password" placeholder="••••••••" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('mail_from_address') }}</label>
                            <Input v-model="prodMailForm.MAIL_FROM_ADDRESS" type="email" placeholder="no-reply@example.com" />
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="prodMailForm.processing">
                                <Loader2 v-if="prodMailForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ prodMailForm.processing ? t('saving') : t('save_base_mail') }}
                            </Button>
                        </div>
                    </form>
                </template>
            </LocalBaseGrid>
        </SettingsCard>
    </div>
</template>
