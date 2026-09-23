<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import TranslatableTextarea from '@/components/ui/translatable-input/TranslatableTextarea.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Language, NotificationTemplateRow, Topic, TriggerModel } from '@/types';

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        template: NotificationTemplateRow | null;
        topics?: Topic[];
        models?: TriggerModel[];
        events?: string[];
        languages?: Language[];
    }>(),
    { topics: () => [], models: () => [], events: () => [], languages: () => [] },
);

const emit = defineEmits<{ (e: 'close'): void }>();

/**
 * A system template's slug and trigger are the code's, not the admin's — something
 * calls `forSystemType()` with that key. The copy, topic and on/off switch stay
 * editable. The server enforces the same thing; this just stops the dead end.
 */
const isSystem = computed(() => !!props.template?.system_type);

const form = useForm<{
    _method: string;
    slug: string;
    topic: string;
    trigger_type: 'manual' | 'model_event';
    trigger_model: string;
    trigger_event: string;
    is_active: boolean;
    translations: Record<string, Record<string, string>>;
}>({
    _method: 'PUT',
    slug: '',
    topic: '',
    trigger_type: 'manual',
    trigger_model: '',
    trigger_event: '',
    is_active: true,
    translations: { title: {}, body: {} },
});

const selectedTopic = computed(() => props.topics.find((topic) => topic.name === form.topic) ?? null);

const restrictedLanguages = computed(() => {
    const code = selectedTopic.value?.lang;
    if (!code) return props.languages;
    return props.languages.filter((l) => l.code === code);
});

watch(
    () => props.template,
    (row) => {
        if (!row) return;
        form.slug = row.slug;
        form.topic = row.topic;
        form.trigger_model = row.trigger_model || '';
        form.trigger_event = row.trigger_event || '';
        form.trigger_type = row.trigger_model && row.trigger_event ? 'model_event' : 'manual';
        form.is_active = Boolean(row.is_active);
        form.translations = translationsToObject(row.translations || [], ['title', 'body']);
    },
    { immediate: true },
);

const close = () => {
    form.clearErrors();
    emit('close');
};

const submit = () => {
    if (!props.template) return;

    if (form.trigger_type === 'manual') {
        form.trigger_model = '';
        form.trigger_event = '';
    }
    form.post(route('notification_templates.update', props.template.id), {
        preserveScroll: true,
        preserveState: true,
        reset: ['templates', 'success', 'error', 'filters'],
        onSuccess: () => close(),
    });
};
</script>

<template>
    <BaseModal :open="isOpen && !!template" :title="t('edit_notification_template')" size="xl" :busy="form.processing" @close="close">
        <form id="notification-template-edit-form" @submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label :for="`nt_edit_slug`" class="text-sm font-medium">{{ t('slug') }}</label>
                    <Input :id="`nt_edit_slug`" v-model="form.slug" :disabled="isSystem" />
                    <p v-if="isSystem" class="text-xs text-muted-foreground">{{ t('system_template_locked') }}</p>
                    <p v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</p>
                </div>

                <div class="space-y-2">
                    <label :for="`nt_edit_topic`" class="text-sm font-medium">{{ t('topic') }}</label>
                    <Select v-model="form.topic">
                        <SelectTrigger :id="`nt_edit_topic`"><SelectValue :placeholder="t('select_topic')" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="tp in topics" :key="tp.name" :value="tp.name">{{ tp.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.topic" class="text-xs text-destructive">{{ form.errors.topic }}</p>
                </div>

                <fieldset class="space-y-2 sm:col-span-2" :disabled="isSystem">
                    <legend class="text-sm font-medium text-foreground">{{ t('trigger') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        <label
                            class="flex flex-1 cursor-pointer items-start gap-2 rounded-xl border border-border bg-muted/30 p-3 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2"
                            :class="form.trigger_type === 'manual' ? 'border-primary bg-primary/5' : ''"
                        >
                            <input type="radio" value="manual" v-model="form.trigger_type" class="mt-1 size-4 accent-primary" />
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-foreground">{{ t('trigger_on_click') }}</span>
                                <span class="block text-xs text-muted-foreground">{{ t('trigger_on_click_desc') }}</span>
                            </span>
                        </label>
                        <label
                            class="flex flex-1 cursor-pointer items-start gap-2 rounded-xl border border-border bg-muted/30 p-3 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2"
                            :class="form.trigger_type === 'model_event' ? 'border-primary bg-primary/5' : ''"
                        >
                            <input type="radio" value="model_event" v-model="form.trigger_type" class="mt-1 size-4 accent-primary" />
                            <span class="flex-1">
                                <span class="block text-sm font-medium text-foreground">{{ t('trigger_model_event') }}</span>
                                <span class="block text-xs text-muted-foreground">{{ t('trigger_model_event_desc') }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div v-if="form.trigger_type === 'model_event'" class="space-y-2">
                    <label :for="`nt_edit_trigger_model`" class="text-sm font-medium">{{ t('trigger_model') }}</label>
                    <Select v-model="form.trigger_model" :disabled="isSystem">
                        <SelectTrigger :id="`nt_edit_trigger_model`"><SelectValue :placeholder="t('select_trigger_model')" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="m in models" :key="m.class" :value="m.class">{{ m.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.trigger_model" class="text-xs text-destructive">{{ form.errors.trigger_model }}</p>
                </div>

                <div v-if="form.trigger_type === 'model_event'" class="space-y-2">
                    <label :for="`nt_edit_trigger_event`" class="text-sm font-medium">{{ t('trigger_event') }}</label>
                    <Select v-model="form.trigger_event" :disabled="isSystem">
                        <SelectTrigger :id="`nt_edit_trigger_event`"><SelectValue :placeholder="t('select_trigger_event')" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="e in events" :key="e" :value="e">{{ t(e) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.trigger_event" class="text-xs text-destructive">{{ form.errors.trigger_event }}</p>
                </div>
            </div>

            <!-- These render their own per-locale labels. -->
            <TranslatableInput v-model="form.translations.title" :languages="restrictedLanguages" :label="t('title')" :required="true" />
            <TranslatableTextarea v-model="form.translations.body" :languages="restrictedLanguages" :label="t('body')" :required="true" />

            <label class="flex items-center gap-2">
                <Checkbox v-model="form.is_active" />
                <span class="text-sm text-foreground">{{ t('active') }}</span>
            </label>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="close">{{ t('cancel') }}</Button>
            <Button type="submit" form="notification-template-edit-form" :disabled="form.processing" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save_changes') }}
            </Button>
        </template>
    </BaseModal>
</template>
