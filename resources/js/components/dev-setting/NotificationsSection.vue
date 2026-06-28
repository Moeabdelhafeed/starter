<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Loader2, Flame, CheckCircle, XCircle, Send, Hash, Plus, X } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import LocalBaseGrid from '@/components/dev-setting/LocalBaseGrid.vue';

const { t } = useI18n();

const props = defineProps({
    firebaseConfigExists: Boolean,
    firebaseCredentialsPath: String,
    baseFirebaseExists: Boolean,
    topicsConfig: Object,
});

// Local Firebase upload (.env / firebase-auth.json — used by this machine)
const firebaseForm = useForm({ firebase_json: null });
const handleFirebaseFile = (e) => {
    firebaseForm.firebase_json = e.target.files[0] || null;
};
const uploadFirebase = () => {
    firebaseForm.post(route('dev_settings.firebase'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { firebaseForm.reset(); },
    });
};

// Base Firebase upload (firebase-base.json — inherited by deploy targets)
const baseFirebaseForm = useForm({ firebase_json: null });
const handleBaseFirebaseFile = (e) => {
    baseFirebaseForm.firebase_json = e.target.files[0] || null;
};
const uploadBaseFirebase = () => {
    baseFirebaseForm.post(route('dev_settings.base_firebase'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { baseFirebaseForm.reset(); },
    });
};
const deleteBaseFirebase = () => {
    router.post(route('dev_settings.base_firebase_delete'), {}, {
        preserveScroll: true,
        preserveState: true,
        reset: ['baseFirebaseExists', 'success', 'error'],
    });
};

// Test FCM
const fcmToken = ref('');
const testingFcm = ref(false);
const sendTestFcm = () => {
    testingFcm.value = true;
    router.post(route('dev_settings.test_fcm'), { token: fcmToken.value }, {
        preserveScroll: true,
        onFinish: () => { testingFcm.value = false; },
    });
};

// FCM Topics chip editor
const topicsForm = useForm({
    topics: [...(props.topicsConfig?.topics ?? [])],
});

const newTopic = ref('');

const addTopic = () => {
    const value = (newTopic.value || '').toLowerCase().trim();
    if (!value) return;
    if (!/^[a-z0-9_-]+$/.test(value)) return;
    if (topicsForm.topics.includes(value)) return;
    topicsForm.topics.push(value);
    newTopic.value = '';
};

const removeTopic = (topic) => {
    topicsForm.topics = topicsForm.topics.filter((t) => t !== topic);
};

const submitTopics = () => {
    topicsForm.put(route('dev_settings.topics'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['topicsConfig', 'success', 'error'],
    });
};

const testTopicForm = useForm({
    topic: props.topicsConfig?.topics?.[0] || '',
    title: 'Test',
    body: 'Test broadcast from DevSettings',
});

const sendTestTopic = () => {
    testTopicForm.post(route('dev_settings.test_topic'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Firebase Configuration -->
        <SettingsCard :title="t('firebase_config')" :description="t('firebase_config_desc')">
            <template #icon><Flame class="size-5 text-orange-500" /></template>

            <LocalBaseGrid>
                <!-- Local: firebase-auth.json used by this machine -->
                <template #local>
                    <div class="flex items-center gap-2">
                        <CheckCircle v-if="firebaseConfigExists" class="size-4 text-emerald-500" />
                        <XCircle v-else class="size-4 text-muted-foreground" />
                        <span class="text-sm" :class="firebaseConfigExists ? 'text-emerald-600' : 'text-muted-foreground'">
                            {{ firebaseConfigExists ? t('firebase_configured') : t('firebase_not_configured') }}
                        </span>
                    </div>
                    <p v-if="firebaseCredentialsPath" class="text-xs font-mono text-muted-foreground break-all">
                        {{ firebaseCredentialsPath }}
                    </p>

                    <form @submit.prevent="uploadFirebase" class="space-y-3">
                        <input type="file" accept=".json" @change="handleFirebaseFile"
                            class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20" />
                        <Button type="submit" :disabled="firebaseForm.processing || !firebaseForm.firebase_json">
                            <Loader2 v-if="firebaseForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ firebaseForm.processing ? t('uploading') : t('upload_firebase_json') }}
                        </Button>
                    </form>

                    <!-- Test FCM (uses local credentials) -->
                    <div v-if="firebaseConfigExists" class="border-t border-border pt-4">
                        <h3 class="mb-3 text-sm font-medium text-foreground">{{ t('test_fcm') }}</h3>
                        <div class="flex items-center gap-3">
                            <Input v-model="fcmToken" type="text" :placeholder="t('fcm_token_placeholder')" class="flex-1" />
                            <Button :disabled="testingFcm || !fcmToken" @click="sendTestFcm">
                                <Loader2 v-if="testingFcm" class="me-2 h-4 w-4 animate-spin" />
                                <Send v-else class="me-2 size-4" />
                                {{ t('send') }}
                            </Button>
                        </div>
                    </div>
                </template>

                <!-- Base: firebase-base.json deployed to targets (per-flavor can override) -->
                <template #base>
                    <div class="flex items-center gap-2">
                        <CheckCircle v-if="baseFirebaseExists" class="size-4 text-emerald-500" />
                        <XCircle v-else class="size-4 text-muted-foreground" />
                        <span class="text-sm" :class="baseFirebaseExists ? 'text-emerald-600' : 'text-muted-foreground'">
                            {{ baseFirebaseExists ? t('firebase_configured') : t('firebase_not_configured') }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ t('base_firebase_hint') }}</p>

                    <form @submit.prevent="uploadBaseFirebase" class="space-y-3">
                        <input type="file" accept=".json" @change="handleBaseFirebaseFile"
                            class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20" />
                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="baseFirebaseForm.processing || !baseFirebaseForm.firebase_json">
                                <Loader2 v-if="baseFirebaseForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ baseFirebaseForm.processing ? t('uploading') : t('upload_firebase_json') }}
                            </Button>
                            <Button v-if="baseFirebaseExists" type="button" variant="outline"
                                class="border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                                @click="deleteBaseFirebase">
                                {{ t('remove') }}
                            </Button>
                        </div>
                    </form>
                </template>
            </LocalBaseGrid>
        </SettingsCard>

        <!-- FCM Topics -->
        <SettingsCard :title="t('fcm_topics')" :description="t('fcm_topics_desc')">
            <template #icon><Hash class="size-5 text-cyan-500" /></template>

            <form @submit.prevent="submitTopics" class="space-y-6">
                <div class="rounded-xl border bg-muted/30 p-4">
                    <p class="mb-3 text-xs font-medium text-muted-foreground">{{ t('topics_list') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="topic in topicsForm.topics"
                            :key="topic"
                            class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary"
                        >
                            {{ topic }}
                            <button
                                type="button"
                                class="rounded-full p-0.5 transition-colors hover:bg-primary/20"
                                @click="removeTopic(topic)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                        <span v-if="topicsForm.topics.length === 0" class="text-sm text-muted-foreground">
                            {{ t('no_topics_yet') }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Input
                        v-model="newTopic"
                        :placeholder="t('topic_name_placeholder')"
                        class="flex-1"
                        @keydown.enter.prevent="addTopic"
                    />
                    <Button type="button" variant="outline" @click="addTopic">
                        <Plus class="me-2 size-4" />
                        {{ t('add_topic') }}
                    </Button>
                </div>

                <p class="text-xs text-muted-foreground">{{ t('topic_name_hint') }}</p>

                <div class="pt-2">
                    <Button type="submit" :disabled="topicsForm.processing">
                        <Loader2 v-if="topicsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ topicsForm.processing ? t('saving') : t('save_topics') }}
                    </Button>
                </div>
            </form>

            <!-- Test broadcast -->
            <div class="mt-8 rounded-xl border border-dashed bg-muted/20 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <Send class="size-4 text-cyan-500" />
                    <h3 class="text-sm font-semibold text-foreground">{{ t('test_topic_broadcast') }}</h3>
                </div>
                <p class="mb-4 text-xs text-muted-foreground">{{ t('test_topic_broadcast_desc') }}</p>

                <form @submit.prevent="sendTestTopic" class="space-y-3">
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-foreground">{{ t('topic') }}</label>
                        <select
                            v-model="testTopicForm.topic"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                        >
                            <option v-for="topic in topicsForm.topics" :key="topic" :value="topic">{{ topic }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-foreground">{{ t('title') }}</label>
                            <Input v-model="testTopicForm.title" type="text" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-foreground">{{ t('body') }}</label>
                            <Input v-model="testTopicForm.body" type="text" />
                        </div>
                    </div>

                    <Button type="submit" :disabled="testTopicForm.processing || !testTopicForm.topic">
                        <Loader2 v-if="testTopicForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Send v-else class="me-2 h-4 w-4" />
                        {{ testTopicForm.processing ? t('sending') : t('send_test_broadcast') }}
                    </Button>
                </form>
            </div>
        </SettingsCard>
    </div>
</template>
