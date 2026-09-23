<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { EyeIcon, EyeOffIcon, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Role } from '@/types';

const { t } = useI18n();

defineProps<{
    isOpen: boolean;
    roles: Role[];
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const showPassword = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    image: null as File | null,
});

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    showPassword.value = false;
};

const submit = () => {
    form.post(route('users.store'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error', 'filters'],
        forceFormData: true,
        onSuccess: () => {
            close();
        },
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('create_user')" size="md" :busy="form.processing" @close="close">
        <form id="user-create-form" class="space-y-5" @submit.prevent="submit">
            <!-- Name -->
            <div class="space-y-2">
                <label for="user-create-name" class="block text-sm font-medium text-foreground">
                    {{ t('name') }} <span class="text-destructive">*</span>
                </label>
                <Input
                    id="user-create-name"
                    v-model="form.name"
                    type="text"
                    :placeholder="t('name')"
                    :aria-invalid="form.errors.name ? true : undefined"
                    :aria-describedby="form.errors.name ? 'user-create-name-error' : undefined"
                />
                <div v-if="form.errors.name" id="user-create-name-error" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Email -->
            <div class="space-y-2">
                <label for="user-create-email" class="block text-sm font-medium text-foreground">
                    {{ t('email') }} <span class="text-destructive">*</span>
                </label>
                <Input
                    id="user-create-email"
                    v-model="form.email"
                    type="email"
                    :placeholder="t('email')"
                    :aria-invalid="form.errors.email ? true : undefined"
                    :aria-describedby="form.errors.email ? 'user-create-email-error' : undefined"
                />
                <div v-if="form.errors.email" id="user-create-email-error" class="text-sm text-destructive">
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- Role -->
            <div class="space-y-2">
                <label for="user-create-role" class="block text-sm font-medium text-foreground">
                    {{ t('role') }} <span class="text-destructive">*</span>
                </label>
                <Select v-model="form.role">
                    <SelectTrigger
                        id="user-create-role"
                        :aria-invalid="form.errors.role ? true : undefined"
                        :aria-describedby="form.errors.role ? 'user-create-role-error' : undefined"
                    >
                        <SelectValue :placeholder="t('select_role')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in roles" :key="r.id" :value="r.name">
                            {{ r.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.role" id="user-create-role-error" class="text-sm text-destructive">
                    {{ form.errors.role }}
                </div>
            </div>

            <!-- Image -->
            <ImageUpload v-model="form.image" :label="t('image')" :error="form.errors.image" shape="circle" />

            <!-- Password -->
            <div class="space-y-2">
                <label for="user-create-password" class="block text-sm font-medium text-foreground">
                    {{ t('password') }} <span class="text-destructive">*</span>
                </label>
                <div class="relative">
                    <Input
                        id="user-create-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :placeholder="t('password_placeholder')"
                        class="pe-10"
                        :aria-invalid="form.errors.password ? true : undefined"
                        :aria-describedby="form.errors.password ? 'user-create-password-error' : undefined"
                    />
                    <button
                        type="button"
                        :aria-label="t('password')"
                        :aria-pressed="showPassword"
                        class="absolute inset-y-0 end-0 flex items-center rounded-md pe-3 text-muted-foreground hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        @click="showPassword = !showPassword"
                    >
                        <EyeIcon v-if="!showPassword" class="h-5 w-5" />
                        <EyeOffIcon v-else class="h-5 w-5" />
                    </button>
                </div>
                <div v-if="form.errors.password" id="user-create-password-error" class="text-sm text-destructive">
                    {{ form.errors.password }}
                </div>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="form.processing" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="user-create-form" class="w-full sm:w-auto" :disabled="form.processing">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('creating') : t('create') }}
            </Button>
        </template>
    </BaseModal>
</template>
