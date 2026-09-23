<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import type { AvailableLocale } from '@/types';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        availableLocales?: AvailableLocale[];
    }>(),
    { availableLocales: () => [] },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const searchQuery = ref('');
const selectedLocaleCode = ref('');

const filteredLocales = computed(() => {
    if (!searchQuery.value) {
        return props.availableLocales;
    }
    const query = searchQuery.value.toLowerCase();
    return props.availableLocales.filter(
        (locale) =>
            locale.code.toLowerCase().includes(query) ||
            locale.name.toLowerCase().includes(query) ||
            locale.native_name.toLowerCase().includes(query),
    );
});

const form = useForm<{
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
    image: File | null;
    is_active: boolean;
    is_default: boolean;
}>({
    code: '',
    name: '',
    native_name: '',
    direction: 'ltr',
    image: null,
    is_active: true,
    is_default: false,
});

const selectLocale = (locale: AvailableLocale) => {
    selectedLocaleCode.value = locale.code;
    form.code = locale.code;
    form.name = locale.name;
    form.native_name = locale.native_name;
    form.direction = locale.direction;
    searchQuery.value = '';
};

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
        selectedLocaleCode.value = '';
        searchQuery.value = '';
    }, 200);
};

const submit = () => {
    form.post(route('languages.store'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['languages', 'availableLocales', 'success', 'error', 'filters'],
        forceFormData: true,
        onSuccess: () => {
            close();
        },
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('create_language')" size="md" :busy="form.processing" @close="close">
        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
            <div class="mb-2 flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0 text-red-500" />
                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
            </div>
            <ul class="space-y-1 ps-6">
                <li v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600">
                    {{ error }}
                </li>
            </ul>
        </div>

        <form id="language-create-form" @submit.prevent="submit" class="space-y-5">
            <!-- Language Selector -->
            <div class="space-y-2">
                <label for="language_create_search" class="block text-sm font-medium text-foreground">
                    {{ t('select_language') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <Input
                        id="language_create_search"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="selectedLocaleCode ? '' : t('search_language')"
                        class="w-full"
                    />
                    <div v-if="selectedLocaleCode && !searchQuery" class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <span class="text-foreground">{{ form.name }} ({{ form.native_name }})</span>
                    </div>
                </div>
                <div v-if="searchQuery || !selectedLocaleCode" class="max-h-48 overflow-y-auto rounded-lg border border-border bg-card">
                    <button
                        v-for="locale in filteredLocales"
                        :key="locale.code"
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-start transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        @click="selectLocale(locale)"
                    >
                        <span class="flex flex-col">
                            <span class="text-sm font-medium text-foreground">{{ locale.name }}</span>
                            <span class="text-xs text-muted-foreground">{{ locale.native_name }} ({{ locale.code }})</span>
                        </span>
                        <span class="text-xs text-muted-foreground">{{ locale.direction.toUpperCase() }}</span>
                    </button>
                    <p v-if="filteredLocales.length === 0" class="px-3 py-4 text-center text-sm text-muted-foreground">
                        {{ t('no_languages_found') }}
                    </p>
                </div>
                <div v-if="form.errors.code" class="text-sm text-red-600">{{ form.errors.code }}</div>
            </div>

            <!-- Selected Language Info (readonly) -->
            <div v-if="selectedLocaleCode" class="grid grid-cols-2 gap-4 rounded-lg border border-border bg-muted/50 p-4">
                <div>
                    <span class="text-xs text-muted-foreground">{{ t('language_code') }}</span>
                    <p class="font-medium text-foreground">{{ form.code }}</p>
                </div>
                <div>
                    <span class="text-xs text-muted-foreground">{{ t('direction') }}</span>
                    <p class="font-medium text-foreground">{{ form.direction.toUpperCase() }}</p>
                </div>
                <div>
                    <span class="text-xs text-muted-foreground">{{ t('language_name') }}</span>
                    <p class="font-medium text-foreground">{{ form.name }}</p>
                </div>
                <div>
                    <span class="text-xs text-muted-foreground">{{ t('native_name') }}</span>
                    <p class="font-medium text-foreground">{{ form.native_name }}</p>
                </div>
            </div>

            <ImageUpload v-model="form.image" :label="t('image')" :error="form.errors.image" />

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-foreground">
                    <Checkbox v-model="form.is_active" />
                    {{ t('active') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-foreground">
                    <Checkbox v-model="form.is_default" />
                    {{ t('is_default') }}
                </label>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="language-create-form" :disabled="form.processing" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('creating') : t('create') }}
            </Button>
        </template>
    </BaseModal>
</template>
