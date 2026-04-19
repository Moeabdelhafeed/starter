<script setup>
import Default from '@/layouts/default.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, Loader2, Save, ImagePlus } from 'lucide-vue-next';
import { useTranslations } from '@/composables/useTranslations';
import { computed } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import TranslatableMarkdown from '@/components/ui/translatable-input/TranslatableMarkdown.vue';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = defineProps({
    page: Object,
    languages: Array,
});

const translations = translationsToObject(props.page.translations, ['name', 'content']);

const form = useForm({
    _method: 'PUT',
    slug: props.page.slug || '',
    is_active: props.page.is_active,
    image: null,
    translations: translations,
});

const imagePreview = computed(() => {
    if (form.image) {
        return URL.createObjectURL(form.image);
    }
    return props.page.image?.image_api || null;
});

const handleImageChange = (e) => {
    form.image = e.target.files[0] || null;
};

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
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-20 text-start">
            <!-- Header -->
            <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Link :href="route('pages')">
                            <Button variant="outline" size="icon">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
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

            <!-- Form -->
            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <!-- Basic Info Card -->
                <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('basic_info') }}</h2>

                    <!-- Slug -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">
                            {{ t('slug') }} <span class="text-red-500">*</span>
                        </label>
                        <Input v-model="form.slug" type="text" :placeholder="t('slug')" />
                        <p v-if="form.errors.slug" class="text-sm text-red-600">{{ form.errors.slug }}</p>
                    </div>

                    <!-- Image -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">
                            {{ t('image') }}
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-muted overflow-hidden">
                                <img
                                    v-if="imagePreview"
                                    :src="imagePreview"
                                    :alt="page.name_api"
                                    class="h-full w-full object-cover"
                                />
                                <ImagePlus v-else class="h-6 w-6 text-muted-foreground" />
                            </div>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleImageChange"
                                class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20"
                            />
                        </div>
                    </div>

                    <!-- Active Checkbox -->
                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <label for="is_active" class="text-sm font-medium text-foreground cursor-pointer">
                            {{ t('active') }}
                        </label>
                    </div>

                    <!-- Translatable Name -->
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

                    <!-- Translatable Content (Markdown) -->
                    <TranslatableMarkdown
                        v-model="form.translations.content"
                        :languages="languages"
                        :placeholder="t('enter_content')"
                    />
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
