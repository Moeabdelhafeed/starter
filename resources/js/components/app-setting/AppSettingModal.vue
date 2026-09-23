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
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { AppSettingItem, Language } from '@/types';

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        languages?: Language[];
        /** Type string preset for create mode. */
        type?: string;
        /** Existing item for edit mode; null = create. */
        item?: AppSettingItem | null;
    }>(),
    { languages: () => [], type: '', item: null },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm<{
    type: string;
    url: string;
    is_active: boolean;
    image: File | null;
    remove_image: boolean;
    translations: Record<string, Record<string, string>>;
}>({
    type: '',
    url: '',
    is_active: true,
    image: null,
    remove_image: false,
    translations: {
        text: {},
    },
});

const resetForm = () => {
    if (props.item) {
        form.type = props.item.type;
        form.url = props.item.url || '';
        form.is_active = !!props.item.is_active;
        form.image = null;
        form.remove_image = false;
        form.translations = translationsToObject(props.item.translations, ['text']);
    } else {
        form.type = props.type;
        form.url = '';
        form.is_active = true;
        form.image = null;
        form.remove_image = false;
        form.translations = { text: {} };
    }
    form.clearErrors();
};

watch(
    () => props.isOpen,
    (open) => {
        if (open) resetForm();
    },
);

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submit = () => {
    if (props.item) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('app_settings.update', props.item.id), {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            reset: ['blocks', 'success', 'error'],
            onSuccess: () => close(),
        });
    } else {
        form.post(route('app_settings.store'), {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            reset: ['blocks', 'success', 'error'],
            onSuccess: () => close(),
        });
    }
};
</script>

<template>
    <BaseModal :open="isOpen" :title="item ? t('edit') : t('add_item')" size="md" :busy="form.processing" @close="close">
        <p class="mb-4 text-sm text-muted-foreground">{{ t('appset_type_' + form.type) }}</p>

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

        <form id="app-setting-form" @submit.prevent="submit" class="space-y-5">
            <!-- Renders its own per-locale labels. -->
            <TranslatableInput
                v-model="form.translations.text"
                :languages="languages"
                :label="t('link_text')"
                :required="true"
                :error="form.errors['translations.text']"
            />

            <div class="space-y-2">
                <label for="appset_url" class="block text-sm font-medium text-foreground">{{ t('link_url') }}</label>
                <Input id="appset_url" v-model="form.url" type="text" :placeholder="t('link_url')" dir="ltr" />
                <div v-if="form.errors.url" class="text-sm text-red-600">{{ form.errors.url }}</div>
            </div>

            <ImageUpload
                v-model="form.image"
                v-model:removed="form.remove_image"
                :preview-url="item?.image?.image_api ?? undefined"
                :label="t('image')"
                :error="form.errors.image"
            />

            <div class="flex items-center gap-2">
                <Checkbox id="appset_is_active" v-model="form.is_active" />
                <label for="appset_is_active" class="cursor-pointer text-sm font-medium text-foreground">
                    {{ t('active') }}
                </label>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="app-setting-form" :disabled="form.processing" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? (item ? t('saving') : t('creating')) : item ? t('save') : t('create') }}
            </Button>
        </template>
    </BaseModal>
</template>
