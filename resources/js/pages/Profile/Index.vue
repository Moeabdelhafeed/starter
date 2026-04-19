<script setup>
import Default from '@/layouts/default.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Loader2, AlertCircle, UserIcon } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';

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
    _method: 'PUT',
});

const imagePreview = ref(props.user.image?.image_api || null);

const handleImageChange = (e) => {
    const file = e.target.files[0] || null;
    form.image = file;
    if (file) {
        imagePreview.value = URL.createObjectURL(file);
    }
};

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
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-20 text-start">
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
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-[auto_1fr]">
                        <!-- Avatar Section -->
                        <div class="flex flex-col items-center gap-3">
                            <div class="relative h-24 w-24 overflow-hidden rounded-full bg-muted">
                                <img
                                    v-if="imagePreview"
                                    :src="imagePreview"
                                    alt="Profile image"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <UserIcon class="h-12 w-12 text-muted-foreground" />
                                </div>
                            </div>
                            <label
                                class="cursor-pointer rounded-full bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition-colors hover:bg-primary/20"
                            >
                                {{ t('change_image') }}
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleImageChange"
                                />
                            </label>
                            <div v-if="form.errors.image" class="text-sm text-red-600">
                                {{ form.errors.image }}
                            </div>
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
