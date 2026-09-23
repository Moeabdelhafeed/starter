<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import type { Language } from '@/types';

const { t } = useI18n();

withDefaults(
    defineProps<{
        isOpen: boolean;
        languages?: Language[];
    }>(),
    { languages: () => [] },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm<{
    slug: string;
    is_active: boolean;
    image: File | null;
    translations: { name: Record<string, string>; content: Record<string, string> };
}>({
    slug: '',
    is_active: true,
    image: null,
    translations: {
        name: {},
        content: {},
    },
});

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submit = () => {
    form.post(route('pages.store'), {
        preserveScroll: true,
        preserveState: false,
        forceFormData: true,
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('create_page')" size="md" :busy="form.processing" @close="close">
        <p class="mb-4 text-sm text-muted-foreground">{{ t('create_page_hint') }}</p>

        <!-- Error Box -->
        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
            <div class="mb-2 flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0 text-red-500" />
                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
            </div>
            <ul class="space-y-1 ps-6">
                <li v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">
                    {{ error }}
                </li>
            </ul>
        </div>

        <form id="page-create-form" @submit.prevent="submit" class="space-y-5">
            <div class="space-y-2">
                <label for="page_create_slug" class="block text-sm font-medium text-foreground">
                    {{ t('slug') }} <span class="text-red-500">*</span>
                </label>
                <Input id="page_create_slug" v-model="form.slug" type="text" :placeholder="t('slug')" />
                <div v-if="form.errors.slug" class="text-sm text-red-600">{{ form.errors.slug }}</div>
            </div>

            <ImageUpload v-model="form.image" :label="t('image')" :error="form.errors.image" />

            <div class="flex items-center gap-2">
                <Checkbox id="page_create_is_active" v-model="form.is_active" />
                <label for="page_create_is_active" class="cursor-pointer text-sm font-medium text-foreground">
                    {{ t('active') }}
                </label>
            </div>

            <!-- Renders its own per-locale labels. -->
            <TranslatableInput
                v-model="form.translations.name"
                :languages="languages"
                :label="t('name')"
                :required="true"
                :placeholder="t('enter_name')"
            />
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="page-create-form" :disabled="form.processing" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('creating') : t('create') }}
            </Button>
        </template>
    </BaseModal>
</template>
