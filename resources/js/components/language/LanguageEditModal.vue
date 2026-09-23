<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { LanguageRow } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    isOpen: boolean;
    language: LanguageRow | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm<{
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
    image: File | null;
    remove_image: boolean;
    is_active: boolean;
    is_default: boolean;
    _method: string;
}>({
    code: '',
    name: '',
    native_name: '',
    direction: 'ltr',
    image: null,
    remove_image: false,
    is_active: true,
    is_default: false,
    _method: 'PUT',
});

const populateForm = () => {
    const newLang = props.language;
    if (!newLang) return;
    form.code = newLang.code;
    form.name = newLang.name;
    form.native_name = newLang.native_name;
    form.direction = newLang.direction;
    form.is_active = newLang.is_active;
    form.is_default = newLang.is_default;
    form.image = null;
    form.remove_image = false;
};

watch(() => props.language, populateForm, { immediate: true });
watch(
    () => props.isOpen,
    (open) => {
        if (open) populateForm();
    },
);

const close = () => {
    emit('close');
    setTimeout(() => {
        form.clearErrors();
    }, 200);
};

const submit = () => {
    if (!props.language) return;

    form.post(route('languages.update', props.language.id), {
        preserveScroll: true,
        preserveState: true,
        reset: ['languages', 'success', 'error', 'filters'],
        forceFormData: true,
        onSuccess: () => {
            close();
        },
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('edit_language')" size="md" :busy="form.processing" @close="close">
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

        <form id="language-edit-form" @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-foreground">{{ t('language_code') }}</label>
                    <div class="flex h-10 items-center rounded-lg border border-border bg-muted/50 px-3 text-sm text-muted-foreground">
                        {{ form.code }}
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="language_edit_direction" class="block text-sm font-medium text-foreground">
                        {{ t('direction') }} <span class="text-red-500">*</span>
                    </label>
                    <Select v-model="form.direction">
                        <SelectTrigger id="language_edit_direction">
                            <SelectValue :placeholder="t('direction')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="ltr">LTR</SelectItem>
                            <SelectItem value="rtl">RTL</SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.direction" class="text-sm text-red-600">{{ form.errors.direction }}</div>
                </div>
            </div>

            <div class="space-y-2">
                <label for="language_edit_name" class="block text-sm font-medium text-foreground">
                    {{ t('language_name') }} <span class="text-red-500">*</span>
                </label>
                <Input id="language_edit_name" v-model="form.name" type="text" placeholder="English, Arabic, French..." />
                <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
            </div>

            <div class="space-y-2">
                <label for="language_edit_native_name" class="block text-sm font-medium text-foreground">
                    {{ t('native_name') }} <span class="text-red-500">*</span>
                </label>
                <Input id="language_edit_native_name" v-model="form.native_name" type="text" placeholder="English, العربية, Français..." />
                <div v-if="form.errors.native_name" class="text-sm text-red-600">{{ form.errors.native_name }}</div>
            </div>

            <ImageUpload
                v-model="form.image"
                v-model:removed="form.remove_image"
                :preview-url="language?.image?.image_api ?? undefined"
                :label="t('image')"
                :error="form.errors.image"
            />

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
            <Button type="submit" form="language-edit-form" :disabled="form.processing" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save_changes') }}
            </Button>
        </template>
    </BaseModal>
</template>
