<script setup>
import Default from '@/layouts/default.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Loader2, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

const props = defineProps({
    user: Object,
});

const form = useForm({
    name: props.user.name,
    image: null,
    remove_image: false,
    _method: 'PUT',
});

const submit = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.clearErrors();
        },
    });
};
</script>

<template>
    <Head :title="t('profile')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 md:py-20 text-start">
            <!-- Header -->
            <div class="flex w-full items-center justify-between rounded-xl border bg-card p-4">
                <h2 class="text-lg font-semibold text-foreground">{{ t('profile') }}</h2>
            </div>

            <!-- Profile Form Card -->
            <div class="flex flex-col gap-5 overflow-hidden rounded-3xl border bg-card p-6">
                <!-- Error Box -->
                <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                        <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                    </div>
                    <ul class="space-y-1 ps-6">
                        <li v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">
                            <span class="font-medium">{{ t(field.toLowerCase()) }}</span>: {{ error }}
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-[16rem_1fr]">
                        <!-- Avatar Section -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('image') }}</label>
                            <ImageUpload
                                v-model="form.image"
                                v-model:removed="form.remove_image"
                                :preview-url="user.image?.image_api || null"
                                :error="form.errors.image"
                                shape="circle"
                            />
                        </div>

                        <!-- Fields Section -->
                        <div class="space-y-5">
                            <!-- Name -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('name') }} <span class="text-red-500">*</span>
                                </label>
                                <Input v-model="form.name" type="text" :placeholder="t('name')" />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Email (read-only) -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('email') }}
                                </label>
                                <Input :model-value="user.email" type="email" disabled class="bg-muted/50 text-muted-foreground" />
                            </div>

                            <!-- Submit -->
                            <div class="flex pt-2">
                                <Button type="submit" :disabled="form.processing">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? t('saving') : t('save') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
