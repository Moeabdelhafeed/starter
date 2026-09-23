<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import Default from '@/layouts/default.vue';

defineOptions({ layout: Default });

interface ProfileUser {
    id: number;
    name: string;
    email: string;
    image: { image_api?: string | null } | null;
}

const props = defineProps<{
    user: ProfileUser;
}>();

const { t } = useI18n();

const form = useForm({
    name: props.user.name,
    image: null as File | null,
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

/**
 * Own-password change. Method-spoofed POST — the production host blocks real PUTs.
 */
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
    _method: 'PUT',
});

const submitPassword = () => {
    passwordForm.post(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset('current_password', 'password', 'password_confirmation');
            passwordForm.clearErrors();
        },
    });
};
</script>

<template>
    <Head :title="t('profile')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 text-start md:py-20">
            <!-- Header -->
            <div class="flex w-full items-center justify-between rounded-xl border bg-card p-4">
                <h2 class="text-lg font-semibold text-foreground">{{ t('profile') }}</h2>
            </div>

            <!-- Profile Form Card -->
            <div class="flex flex-col gap-5 overflow-hidden rounded-3xl border bg-card p-6">
                <!-- Error Box -->
                <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <AlertCircle class="h-4 w-4 shrink-0 text-red-500" aria-hidden="true" />
                        <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                    </div>
                    <ul class="space-y-1 ps-6">
                        <li v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">
                            <span class="font-medium">{{ t(String(field).toLowerCase()) }}</span
                            >: {{ error }}
                        </li>
                    </ul>
                </div>

                <form class="space-y-6" @submit.prevent="submit">
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
                                <label for="profile-name" class="block text-sm font-medium text-foreground">
                                    {{ t('name') }} <span class="text-red-500">*</span>
                                </label>
                                <Input id="profile-name" v-model="form.name" type="text" :placeholder="t('name')" />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Email (read-only) -->
                            <div class="space-y-2">
                                <label for="profile-email" class="block text-sm font-medium text-foreground">
                                    {{ t('email') }}
                                </label>
                                <Input id="profile-email" :model-value="user.email" type="email" disabled class="bg-muted/50 text-muted-foreground" />
                            </div>

                            <!-- Submit -->
                            <div class="flex pt-2">
                                <Button type="submit" :disabled="form.processing">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" aria-hidden="true" />
                                    {{ form.processing ? t('saving') : t('save') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="flex flex-col gap-5 overflow-hidden rounded-3xl border bg-card p-6">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">{{ t('change_password') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ t('change_password_hint') }}</p>
                </div>

                <form class="space-y-5" @submit.prevent="submitPassword">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <!-- Current password -->
                        <div class="space-y-2 md:col-span-2">
                            <label for="current-password" class="block text-sm font-medium text-foreground">
                                {{ t('current_password') }} <span class="text-red-500">*</span>
                            </label>
                            <Input
                                id="current-password"
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                :placeholder="t('password_placeholder')"
                                :disabled="passwordForm.processing"
                            />
                            <div v-if="passwordForm.errors.current_password" class="text-sm text-red-600">
                                {{ passwordForm.errors.current_password }}
                            </div>
                        </div>

                        <!-- New password -->
                        <div class="space-y-2">
                            <label for="new-password" class="block text-sm font-medium text-foreground">
                                {{ t('new_password') }} <span class="text-red-500">*</span>
                            </label>
                            <Input
                                id="new-password"
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="t('password_placeholder')"
                                :disabled="passwordForm.processing"
                            />
                            <div v-if="passwordForm.errors.password" class="text-sm text-red-600">
                                {{ passwordForm.errors.password }}
                            </div>
                        </div>

                        <!-- Confirm password -->
                        <div class="space-y-2">
                            <label for="confirm-password" class="block text-sm font-medium text-foreground">
                                {{ t('confirm_password') }} <span class="text-red-500">*</span>
                            </label>
                            <Input
                                id="confirm-password"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="t('password_placeholder')"
                                :disabled="passwordForm.processing"
                            />
                            <div v-if="passwordForm.errors.password_confirmation" class="text-sm text-red-600">
                                {{ passwordForm.errors.password_confirmation }}
                            </div>
                        </div>
                    </div>

                    <div class="flex pt-2">
                        <Button type="submit" :disabled="passwordForm.processing">
                            <Loader2 v-if="passwordForm.processing" class="me-2 h-4 w-4 animate-spin" aria-hidden="true" />
                            {{ passwordForm.processing ? t('saving') : t('change_password') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
