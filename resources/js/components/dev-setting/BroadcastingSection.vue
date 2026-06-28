<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Send, Loader2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import LocalBaseGrid from '@/components/dev-setting/LocalBaseGrid.vue';

const { t } = useI18n();

const props = defineProps({
    pusherConfig: Object,
});

// Pusher Config - Local
const localPusherForm = useForm({
    app_id: props.pusherConfig?.local?.app_id || '',
    app_key: props.pusherConfig?.local?.app_key || '',
    app_secret: props.pusherConfig?.local?.app_secret || '',
    app_cluster: props.pusherConfig?.local?.app_cluster || 'eu',
});

const submitLocalPusher = () => {
    localPusherForm.put(route('dev_settings.pusher'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['pusherConfig', 'success', 'error'],
    });
};

// Pusher Config - Base
const productionPusherForm = useForm({
    app_id: props.pusherConfig?.base?.app_id || '',
    app_key: props.pusherConfig?.base?.app_key || '',
    app_secret: props.pusherConfig?.base?.app_secret || '',
    app_cluster: props.pusherConfig?.base?.app_cluster || 'eu',
});

const submitProductionPusher = () => {
    productionPusherForm.put(route('dev_settings.production_pusher'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['pusherConfig', 'success', 'error'],
    });
};

// Test Broadcast
const testBroadcastForm = useForm({
    user_id: '',
});

const submitTestBroadcast = () => {
    testBroadcastForm.post(route('dev_settings.test_broadcast'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Pusher Broadcasting -->
        <SettingsCard :title="t('pusher_settings')" :description="t('pusher_settings_desc')">
            <template #icon><Send class="size-5 text-purple-500" /></template>

            <LocalBaseGrid>
                <!-- Local Pusher Config -->
                <template #local>
                    <form @submit.prevent="submitLocalPusher" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_id')
                                }}</label>
                            <Input v-model="localPusherForm.app_id" :placeholder="t('pusher_app_id')" class="h-9" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_key')
                                }}</label>
                            <Input v-model="localPusherForm.app_key" :placeholder="t('pusher_app_key')" class="h-9" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{
                                t('pusher_app_secret') }}</label>
                            <Input v-model="localPusherForm.app_secret" type="password"
                                :placeholder="t('pusher_app_secret')" class="h-9" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{
                                t('pusher_app_cluster') }}</label>
                            <Input v-model="localPusherForm.app_cluster" :placeholder="t('pusher_app_cluster')"
                                class="h-9" />
                        </div>
                        <Button type="submit" size="sm" :disabled="localPusherForm.processing">
                            <Loader2 v-if="localPusherForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ localPusherForm.processing ? t('saving') : t('save') }}
                        </Button>
                    </form>
                </template>

                <!-- Base Pusher Config -->
                <template #base>
                    <form @submit.prevent="submitProductionPusher" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_id')
                                }}</label>
                            <Input v-model="productionPusherForm.app_id" :placeholder="t('pusher_app_id')"
                                class="h-9" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_key')
                                }}</label>
                            <Input v-model="productionPusherForm.app_key" :placeholder="t('pusher_app_key')"
                                class="h-9" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{
                                t('pusher_app_secret') }}</label>
                            <Input v-model="productionPusherForm.app_secret" type="password"
                                :placeholder="t('pusher_app_secret')" class="h-9" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">{{
                                t('pusher_app_cluster') }}</label>
                            <Input v-model="productionPusherForm.app_cluster" :placeholder="t('pusher_app_cluster')"
                                class="h-9" />
                        </div>
                        <Button type="submit" size="sm" :disabled="productionPusherForm.processing">
                            <Loader2 v-if="productionPusherForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ productionPusherForm.processing ? t('saving') : t('save') }}
                        </Button>
                    </form>
                </template>
            </LocalBaseGrid>
        </SettingsCard>

        <!-- Test Broadcast -->
        <SettingsCard :title="t('test_broadcast')" :description="t('test_broadcast_desc')">
            <template #icon><Send class="size-5 text-purple-500" /></template>

            <form @submit.prevent="submitTestBroadcast" class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-foreground mb-1">{{ t('select_user') }}</label>
                    <Input v-model="testBroadcastForm.user_id" type="number" placeholder="User ID" />
                </div>
                <Button type="submit" :disabled="testBroadcastForm.processing || !testBroadcastForm.user_id">
                    <Loader2 v-if="testBroadcastForm.processing" class="me-2 h-4 w-4 animate-spin" />
                    <Send v-else class="me-2 h-4 w-4" />
                    {{ t('send_test_broadcast') }}
                </Button>
            </form>
        </SettingsCard>
    </div>
</template>
