<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import TranslatableTextarea from '@/components/ui/translatable-input/TranslatableTextarea.vue';
import type { Language, Topic, TriggerModel } from '@/types';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        topics?: Topic[];
        models?: TriggerModel[];
        events?: string[];
        languages?: Language[];
    }>(),
    { topics: () => [], models: () => [], events: () => [], languages: () => [] },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm<{
    slug: string;
    topic: string;
    trigger_type: 'manual' | 'model_event';
    trigger_model: string;
    trigger_event: string;
    is_active: boolean;
    translations: { title: Record<string, string>; body: Record<string, string> };
}>({
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

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    if (form.trigger_type === 'manual') {
        form.trigger_model = '';
        form.trigger_event = '';
    }
    form.post(route('notification_templates.store'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['templates', 'success', 'error', 'filters'],
        onSuccess: () => close(),
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('create_notification_template')" size="xl" :busy="form.processing" @close="close">
        <form id="notification-template-create-form" @submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label :for="`nt_create_slug`" class="text-sm font-medium">{{ t('slug') }}</label>
                    <Input :id="`nt_create_slug`" v-model="form.slug" placeholder="post_created" />
                    <p v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</p>
                </div>

                <div class="space-y-2">
                    <label :for="`nt_create_topic`" class="text-sm font-medium">{{ t('topic') }}</label>
                    <Select v-model="form.topic">
                        <SelectTrigger :id="`nt_create_topic`"><SelectValue :placeholder="t('select_topic')" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="!topics.length" disabled value="_none">{{ t('no_topics_configured') }}</SelectItem>
                            <SelectItem v-for="tp in topics" :key="tp.name" :value="tp.name">{{ tp.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.topic" class="text-xs text-destructive">{{ form.errors.topic }}</p>
                </div>

                <fieldset class="space-y-2 sm:col-span-2">
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
                    <label :for="`nt_create_trigger_model`" class="text-sm font-medium">{{ t('trigger_model') }}</label>
                    <Select v-model="form.trigger_model">
                        <SelectTrigger :id="`nt_create_trigger_model`"><SelectValue :placeholder="t('select_trigger_model')" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="!models.length" disabled value="_none">{{ t('no_models_with_trigger') }}</SelectItem>
                            <SelectItem v-for="m in models" :key="m.class" :value="m.class">{{ m.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.trigger_model" class="text-xs text-destructive">{{ form.errors.trigger_model }}</p>
                </div>

                <div v-if="form.trigger_type === 'model_event'" class="space-y-2">
                    <label :for="`nt_create_trigger_event`" class="text-sm font-medium">{{ t('trigger_event') }}</label>
                    <Select v-model="form.trigger_event">
                        <SelectTrigger :id="`nt_create_trigger_event`"><SelectValue :placeholder="t('select_trigger_event')" /></SelectTrigger>
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
            <Button type="submit" form="notification-template-create-form" :disabled="form.processing" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save') }}
            </Button>
        </template>
    </BaseModal>
</template>
