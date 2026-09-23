<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Loader2, Save } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import TranslatableMarkdown from '@/components/ui/translatable-input/TranslatableMarkdown.vue';
import { useTranslations } from '@/composables/useTranslations';
import Default from '@/layouts/default.vue';
import type { Language } from '@/types';

interface PageResource {
    id: number;
    slug: string | null;
    /** Listed in PROTECTED_PAGES: the slug is frozen and the page cannot be deleted. */
    is_protected: boolean;
    is_active: boolean;
    name_api: string;
    image?: { image_api?: string | null } | null;
    translations: { field: string; locale: string; value: string }[];
}

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = withDefaults(
    defineProps<{
        page: PageResource;
        languages?: Language[];
    }>(),
    { languages: () => [] },
);

const translations = translationsToObject(props.page.translations, ['name', 'content']);

const form = useForm<{
    _method: string;
    slug: string;
    is_active: boolean;
    image: File | null;
    remove_image: boolean;
    translations: Record<string, Record<string, string>>;
}>({
    _method: 'PUT',
    slug: props.page.slug || '',
    is_active: props.page.is_active,
    image: null,
    remove_image: false,
    translations: translations,
});

const submit = () => {
    form.post(route('pages.update', props.page.id), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="t('edit_page') + ' - ' + page.name_api" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 text-start md:py-20">
            <!-- Header -->
            <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Button as-child variant="outline" size="icon" :aria-label="t('pages')">
                            <Link :href="route('pages')">
                                <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                            </Link>
                        </Button>
                        <div>
                            <h1 class="text-xl font-bold tracking-tight text-foreground">
                                {{ t('edit_page') }}
                            </h1>
                            <p class="text-sm text-muted-foreground">{{ page.name_api }}</p>
                        </div>
                    </div>
                    <Button @click="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Save v-else class="me-2 h-4 w-4" />
                        {{ form.processing ? t('saving') : t('save') }}
                    </Button>
                </div>
            </div>

            <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
                <div class="mb-2 flex items-center gap-2">
                    <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                    <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                </div>
                <ul class="space-y-1 ps-6">
                    <li v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">{{ error }}</li>
                </ul>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <!-- Basic Info Card -->
                <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('basic_info') }}</h2>

                    <div class="space-y-2">
                        <label for="page_slug" class="block text-sm font-medium text-foreground">
                            {{ t('slug') }} <span class="text-red-500">*</span>
                        </label>
                        <Input id="page_slug" v-model="form.slug" type="text" :placeholder="t('slug')" :disabled="page.is_protected" />
                        <p v-if="page.is_protected" class="text-sm text-muted-foreground">{{ t('protected_page_locked') }}</p>
                        <p v-if="form.errors.slug" class="text-sm text-red-600">{{ form.errors.slug }}</p>
                    </div>

                    <ImageUpload
                        v-model="form.image"
                        v-model:removed="form.remove_image"
                        :preview-url="page.image?.image_api ?? undefined"
                        :label="t('image')"
                        :error="form.errors.image"
                    />

                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" v-model="form.is_active" :disabled="page.is_protected" />
                        <label for="is_active" class="cursor-pointer text-sm font-medium text-foreground">
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
                </div>

                <!-- Content Card -->
                <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('content') }}</h2>

                    <TranslatableMarkdown v-model="form.translations.content" :languages="languages" :placeholder="t('enter_content')" />
                </div>

                <!-- Save Button (bottom) -->
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing" size="lg">
                        <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Save v-else class="me-2 h-4 w-4" />
                        {{ form.processing ? t('saving') : t('save') }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
