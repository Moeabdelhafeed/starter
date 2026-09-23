<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { EyeIcon, EyeOffIcon, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';

interface EditableAppUser {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    username?: string | null;
}

const { t } = useI18n();
const page = usePage();
const authFields = computed(() => page.props.auth_fields as { email?: boolean; phone?: boolean; username?: boolean });

const props = defineProps<{
    isOpen: boolean;
    user: EditableAppUser | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const showPassword = ref(false);

const form = useForm({
    id: null as number | null,
    name: '',
    email: '',
    phone: '',
    username: '',
    password: '',
});

const populateForm = () => {
    const newUser = props.user;
    if (!newUser) return;
    form.id = newUser.id;
    form.name = newUser.name;
    form.email = newUser.email || '';
    form.phone = newUser.phone || '';
    form.username = newUser.username || '';
    form.password = '';
};

watch(() => props.user, populateForm, { immediate: true });
watch(
    () => props.isOpen,
    (open) => {
        if (open) populateForm();
    },
);

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    showPassword.value = false;
};

const submit = () => {
    // POST + _method spoof — the production host blocks real PUT.
    form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('app_users.update', form.id as number), {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error', 'filters'],
        onSuccess: () => {
            close();
        },
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('edit_app_user')" size="md" :busy="form.processing" @close="close">
        <form id="app-user-edit-form" class="space-y-5" @submit.prevent="submit">
            <!-- Name -->
            <div class="space-y-2">
                <label for="app-user-edit-name" class="block text-sm font-medium text-foreground">{{ t('name') }}</label>
                <Input
                    id="app-user-edit-name"
                    v-model="form.name"
                    type="text"
                    :placeholder="t('name')"
                    :aria-invalid="form.errors.name ? true : undefined"
                    :aria-describedby="form.errors.name ? 'app-user-edit-name-error' : undefined"
                />
                <div v-if="form.errors.name" id="app-user-edit-name-error" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Email -->
            <div v-if="authFields.email" class="space-y-2">
                <label for="app-user-edit-email" class="block text-sm font-medium text-foreground">{{ t('email') }}</label>
                <Input
                    id="app-user-edit-email"
                    v-model="form.email"
                    type="email"
                    :placeholder="t('email')"
                    :aria-invalid="form.errors.email ? true : undefined"
                    :aria-describedby="form.errors.email ? 'app-user-edit-email-error' : undefined"
                />
                <div v-if="form.errors.email" id="app-user-edit-email-error" class="text-sm text-destructive">
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- Phone -->
            <div v-if="authFields.phone" class="space-y-2">
                <label for="app-user-edit-phone" class="block text-sm font-medium text-foreground">{{ t('phone') }}</label>
                <Input
                    id="app-user-edit-phone"
                    v-model="form.phone"
                    type="text"
                    :placeholder="t('phone')"
                    :aria-invalid="form.errors.phone ? true : undefined"
                    :aria-describedby="form.errors.phone ? 'app-user-edit-phone-error' : undefined"
                />
                <div v-if="form.errors.phone" id="app-user-edit-phone-error" class="text-sm text-destructive">
                    {{ form.errors.phone }}
                </div>
            </div>

            <!-- Username -->
            <div v-if="authFields.username" class="space-y-2">
                <label for="app-user-edit-username" class="block text-sm font-medium text-foreground">{{ t('username') }}</label>
                <Input
                    id="app-user-edit-username"
                    v-model="form.username"
                    type="text"
                    :placeholder="t('username')"
                    :aria-invalid="form.errors.username ? true : undefined"
                    :aria-describedby="form.errors.username ? 'app-user-edit-username-error' : undefined"
                />
                <div v-if="form.errors.username" id="app-user-edit-username-error" class="text-sm text-destructive">
                    {{ form.errors.username }}
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <label for="app-user-edit-password" class="block text-sm font-medium text-foreground">
                    {{ t('password') }} ({{ t('leave_blank_to_keep') }})
                </label>
                <div class="relative">
                    <Input
                        id="app-user-edit-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :placeholder="t('password_placeholder')"
                        class="pe-10"
                        :aria-invalid="form.errors.password ? true : undefined"
                        :aria-describedby="form.errors.password ? 'app-user-edit-password-error' : undefined"
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
                <div v-if="form.errors.password" id="app-user-edit-password-error" class="text-sm text-destructive">
                    {{ form.errors.password }}
                </div>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="form.processing" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="app-user-edit-form" class="w-full sm:w-auto" :disabled="form.processing">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save_changes') }}
            </Button>
        </template>
    </BaseModal>
</template>
