<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { XIcon, Loader2, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    translation: Object,
    languages: Array,
});

// Detect Laravel-style `:placeholder` tokens (not `::`) in the original value of any locale.
// Source of truth: the default language's existing value. Translators must keep these intact.
const PLACEHOLDER_RE = /(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/g;

const requiredPlaceholders = computed(() => {
    if (!props.translation || !props.languages) return [];
    const defaultLang = props.languages.find(l => l.is_default) || props.languages[0];
    const source = props.translation?.[defaultLang?.code] || '';
    return Array.from(new Set([...source.matchAll(PLACEHOLDER_RE)].map(m => m[1])));
});

const localePlaceholders = (value) => {
    return Array.from(new Set([...(value || '').matchAll(PLACEHOLDER_RE)].map(m => m[1])));
};

const missingPlaceholders = (value) => {
    const have = localePlaceholders(value);
    return requiredPlaceholders.value.filter(p => !have.includes(p));
};

const hasAnyMissingPlaceholders = computed(() => {
    if (!requiredPlaceholders.value.length || !props.languages) return false;
    return props.languages.some(lang => missingPlaceholders(form[lang.code] || '').length > 0);
});

const emit = defineEmits(['close']);

const buildFormData = () => {
    const data = { id: null };
    if (props.languages) {
        props.languages.forEach(lang => {
            data[lang.code] = '';
        });
    }
    return data;
};

const form = useForm(buildFormData());

const populateForm = () => {
    const newTrans = props.translation;
    if (!newTrans) return;
    form.id = newTrans.id;
    props.languages?.forEach(lang => {
        form[lang.code] = newTrans[lang.code] || '';
    });
};

watch(() => props.translation, populateForm, { immediate: true });
watch(() => props.isOpen, (open) => {
    if (open) populateForm();
});

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const getGroupLabel = (group) => {
    const labels = {
        api: t('api_group'),
        app: t('app_group'),
        web: t('web_group'),
    };
    return labels[group] || group;
};

const getGroupBadgeClass = (group) => {
    const classes = {
        api: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        app: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        web: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };
    return classes[group] || classes.app;
};

const submit = () => {
    form.post(route('translations.edit'), {
        preserveState: true,
        preserveScroll: true,
        reset: ['translations', 'success', 'error', 'filters'],
        onSuccess: () => {
            close();
        },
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
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ t('edit_translation') }}
                            </h3>
                            <button
                                @click="close"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                <XIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="mb-2 flex items-center gap-2">
                                <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                            </div>
                            <ul class="space-y-1 ps-6">
                                <li v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Key and Group Fields (Read-only) -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">
                                        {{ t('key') }}
                                    </label>
                                    <Input :model-value="translation?.key" disabled class="bg-muted/50 border-border text-muted-foreground!" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">
                                        {{ t('group') }}
                                    </label>
                                    <div class="flex h-10 items-center rounded-lg border bg-muted/50 px-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="getGroupBadgeClass(translation?.group)"
                                        >
                                            {{ getGroupLabel(translation?.group) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-group (Read-only) -->
                            <div v-if="translation?.sub_group" class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('sub_group') }}
                                </label>
                                <div class="flex h-10 items-center rounded-lg border bg-muted/50 px-3">
                                    <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                        {{ translation.sub_group }}
                                    </span>
                                </div>
                            </div>

                            <!-- Required Placeholders Notice -->
                            <div v-if="requiredPlaceholders.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/40 dark:bg-amber-900/20">
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">{{ t('required_placeholders') }}</p>
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-300">{{ t('required_placeholders_desc') }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <code v-for="p in requiredPlaceholders" :key="p" class="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">:{{ p }}</code>
                                </div>
                            </div>

                            <!-- Dynamic Language Fields -->
                            <div v-for="lang in languages" :key="lang.code" class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ lang.name }} ({{ lang.native_name }})
                                </label>
                                <Input
                                    v-model="form[lang.code]"
                                    type="text"
                                    :placeholder="`${lang.name}...`"
                                    :dir="lang.direction"
                                />
                                <div v-if="form.errors[lang.code]" class="text-sm text-red-600">{{ form.errors[lang.code] }}</div>
                                <div v-else-if="missingPlaceholders(form[lang.code]).length" class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ t('missing_placeholders') }}: <code class="font-mono">{{ missingPlaceholders(form[lang.code]).map(p => ':' + p).join(', ') }}</code>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex gap-3 pt-4">
                                <Button type="button" variant="outline" @click="close" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing || hasAnyMissingPlaceholders" class="flex-1">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? t('saving') : t('save') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
