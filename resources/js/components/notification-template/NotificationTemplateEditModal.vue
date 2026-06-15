<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, watch } from 'vue';
import { XIcon, Loader2 } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import TranslatableTextarea from '@/components/ui/translatable-input/TranslatableTextarea.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = defineProps({
    isOpen: Boolean,
    template: Object,
    topics: Array,
    models: Array,
    events: Array,
    languages: Array,
});

const emit = defineEmits(['close']);

const form = useForm({
    _method: 'PUT',
    slug: '',
    topic: '',
    trigger_type: 'manual',
    trigger_model: '',
    trigger_event: '',
    is_active: true,
    translations: { title: {}, body: {} },
});

const selectedTopic = computed(() => props.topics.find((t) => t.name === form.topic) ?? null);

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
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen && template" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-card p-6 text-start shadow-xl">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-semibold">{{ t('edit_notification_template') }}</h2>
                            <button @click="close" class="rounded-full p-1 hover:bg-muted">
                                <XIcon class="size-4" />
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">{{ t('slug') }}</label>
                                    <Input v-model="form.slug" />
                                    <p v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</p>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">{{ t('topic') }}</label>
                                    <Select v-model="form.topic">
                                        <SelectTrigger><SelectValue :placeholder="t('select_topic')" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="tp in topics" :key="tp.name" :value="tp.name">{{ tp.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <label class="text-sm font-medium">{{ t('trigger') }}</label>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            class="flex flex-1 cursor-pointer items-start gap-2 rounded-xl border bg-muted/30 p-3"
                                            :class="form.trigger_type === 'manual' ? 'border-primary bg-primary/5' : ''"
                                        >
                                            <input type="radio" value="manual" v-model="form.trigger_type" class="mt-1 size-4 accent-primary" />
                                            <div class="flex-1">
                                                <p class="text-sm font-medium">{{ t('trigger_on_click') }}</p>
                                                <p class="text-xs text-muted-foreground">{{ t('trigger_on_click_desc') }}</p>
                                            </div>
                                        </label>
                                        <label
                                            class="flex flex-1 cursor-pointer items-start gap-2 rounded-xl border bg-muted/30 p-3"
                                            :class="form.trigger_type === 'model_event' ? 'border-primary bg-primary/5' : ''"
                                        >
                                            <input type="radio" value="model_event" v-model="form.trigger_type" class="mt-1 size-4 accent-primary" />
                                            <div class="flex-1">
                                                <p class="text-sm font-medium">{{ t('trigger_model_event') }}</p>
                                                <p class="text-xs text-muted-foreground">{{ t('trigger_model_event_desc') }}</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="form.trigger_type === 'model_event'" class="space-y-2">
                                    <label class="text-sm font-medium">{{ t('trigger_model') }}</label>
                                    <Select v-model="form.trigger_model">
                                        <SelectTrigger><SelectValue :placeholder="t('select_trigger_model')" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="m in models" :key="m.class" :value="m.class">{{ m.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div v-if="form.trigger_type === 'model_event'" class="space-y-2">
                                    <label class="text-sm font-medium">{{ t('trigger_event') }}</label>
                                    <Select v-model="form.trigger_event">
                                        <SelectTrigger><SelectValue :placeholder="t('select_trigger_event')" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="e in events" :key="e" :value="e">{{ t(e) }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <TranslatableInput v-model="form.translations.title" :languages="restrictedLanguages" :label="t('title')" :required="true" />
                            <TranslatableTextarea v-model="form.translations.body" :languages="restrictedLanguages" :label="t('body')" :required="true" />

                            <label class="flex items-center gap-2">
                                <Checkbox v-model="form.is_active" />
                                <span class="text-sm">{{ t('active') }}</span>
                            </label>

                            <div class="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" @click="close">{{ t('cancel') }}</Button>
                                <Button type="submit" :disabled="form.processing">
                                    <Loader2 v-if="form.processing" class="me-2 size-4 animate-spin" />
                                    {{ form.processing ? t('saving') : t('save_changes') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
