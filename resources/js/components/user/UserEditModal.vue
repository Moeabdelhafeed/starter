<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { EyeIcon, EyeOffIcon, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Role } from '@/types';

interface EditableUser {
    id: number;
    name: string;
    email: string;
    image?: { image_api?: string | null } | null;
    roles: Role[];
}

const { t } = useI18n();
const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const props = defineProps<{
    isOpen: boolean;
    user: EditableUser | null;
    roles: Role[];
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const showPassword = ref(false);

const form = useForm({
    id: null as number | null,
    name: '',
    email: '',
    password: '',
    role: '',
    image: null as File | null,
    remove_image: false,
    _method: 'PUT',
});

const currentImageUrl = ref<string | undefined>(undefined);

const populateForm = () => {
    const newUser = props.user;
    if (!newUser) return;
    form.id = newUser.id;
    form.name = newUser.name;
    form.email = newUser.email;
    form.password = '';
    form.role = newUser.roles.length > 0 ? newUser.roles[0].name : '';
    form.image = null;
    form.remove_image = false;
    currentImageUrl.value = newUser.image?.image_api || undefined;
};

watch(() => props.user, populateForm, { immediate: true });
watch(
    () => props.isOpen,
    (open) => {
        if (open) populateForm();
    },
);

const isSelf = computed(() => form.id === currentUser.value?.id);

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    showPassword.value = false;
};

const submit = () => {
    form.post(route('users.update', form.id as number), {
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
    <BaseModal :open="isOpen" :title="t('edit')" size="md" :busy="form.processing" @close="close">
        <form id="user-edit-form" class="space-y-5" @submit.prevent="submit">
            <!-- Name -->
            <div class="space-y-2">
                <label for="user-edit-name" class="block text-sm font-medium text-foreground">{{ t('name') }}</label>
                <Input
                    id="user-edit-name"
                    v-model="form.name"
                    type="text"
                    :placeholder="t('name')"
                    :aria-invalid="form.errors.name ? true : undefined"
                    :aria-describedby="form.errors.name ? 'user-edit-name-error' : undefined"
                />
                <div v-if="form.errors.name" id="user-edit-name-error" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Email -->
            <div class="space-y-2">
                <label for="user-edit-email" class="block text-sm font-medium text-foreground">{{ t('email') }}</label>
                <Input
                    id="user-edit-email"
                    v-model="form.email"
                    type="email"
                    :placeholder="t('email')"
                    :aria-invalid="form.errors.email ? true : undefined"
                    :aria-describedby="form.errors.email ? 'user-edit-email-error' : undefined"
                />
                <div v-if="form.errors.email" id="user-edit-email-error" class="text-sm text-destructive">
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- Image -->
            <ImageUpload
                v-model="form.image"
                v-model:removed="form.remove_image"
                :preview-url="currentImageUrl"
                :label="t('image')"
                :error="form.errors.image"
                shape="circle"
            />

            <!-- Role -->
            <div class="space-y-2">
                <label for="user-edit-role" class="block text-sm font-medium text-foreground">{{ t('role') }}</label>
                <Select v-model="form.role" :disabled="isSelf">
                    <SelectTrigger
                        id="user-edit-role"
                        :class="{ 'border-border bg-muted/50 text-muted-foreground!': isSelf }"
                        :aria-invalid="form.errors.role ? true : undefined"
                        :aria-describedby="form.errors.role ? 'user-edit-role-error' : undefined"
                    >
                        <SelectValue :placeholder="t('select_role')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in roles" :key="r.id" :value="r.name">
                            {{ r.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.role" id="user-edit-role-error" class="text-sm text-destructive">
                    {{ form.errors.role }}
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <label for="user-edit-password" class="block text-sm font-medium text-foreground">{{ t('password') }}</label>
                <div class="relative">
                    <Input
                        id="user-edit-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :placeholder="t('password_placeholder')"
                        class="pe-10"
                        :aria-invalid="form.errors.password ? true : undefined"
                        :aria-describedby="form.errors.password ? 'user-edit-password-hint user-edit-password-error' : 'user-edit-password-hint'"
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
                <p id="user-edit-password-hint" class="text-xs text-muted-foreground">{{ t('leave_blank_to_keep') }}</p>
                <div v-if="form.errors.password" id="user-edit-password-error" class="text-sm text-destructive">
                    {{ form.errors.password }}
                </div>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="form.processing" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="user-edit-form" class="w-full sm:w-auto" :disabled="form.processing">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save') }}
            </Button>
        </template>
    </BaseModal>
</template>
