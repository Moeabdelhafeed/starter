<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import type { Permission } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    isOpen: boolean;
    permissions: Permission[];
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm({
    name: '',
    permissions: [] as string[],
});

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submit = () => {
    form.post(route('roles.store'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['roles', 'success', 'error', 'filters'],
        onSuccess: () => {
            close();
        },
    });
};

const updatePermission = (permissionName: string, checked: boolean) => {
    if (checked) {
        if (!form.permissions.includes(permissionName)) {
            form.permissions.push(permissionName);
        }

        // Special logic for extra_attributes
        if (permissionName === 'extra_attributes') {
            props.permissions.forEach((p) => {
                if (p.name.startsWith('attribute.') && !form.permissions.includes(p.name)) {
                    form.permissions.push(p.name);
                }
            });
        }
    } else {
        form.permissions = form.permissions.filter((p) => p !== permissionName);

        if (permissionName === 'extra_attributes') {
            form.permissions = form.permissions.filter((p) => !p.startsWith('attribute.'));
        }

        if (permissionName.startsWith('attribute.')) {
            form.permissions = form.permissions.filter((p) => p !== 'extra_attributes');
        }
    }
};

const togglePermission = (permissionName: string) => {
    updatePermission(permissionName, !form.permissions.includes(permissionName));
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('create_role')" size="lg" :busy="form.processing" @close="close">
        <form id="role-create-form" class="space-y-5" @submit.prevent="submit">
            <!-- Name -->
            <div class="space-y-2">
                <label for="role-create-name" class="block text-sm font-medium text-foreground">
                    {{ t('name') }} <span class="text-destructive">*</span>
                </label>
                <Input
                    id="role-create-name"
                    v-model="form.name"
                    type="text"
                    :placeholder="t('name')"
                    :aria-invalid="form.errors.name ? true : undefined"
                    :aria-describedby="form.errors.name ? 'role-create-name-error' : undefined"
                />
                <div v-if="form.errors.name" id="role-create-name-error" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Permissions -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-foreground">{{ t('permissions') }}</label>
                <div role="group" :aria-label="t('permissions')" class="grid max-h-60 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-2">
                    <div v-for="permission in permissions" :key="permission.id" class="flex items-center gap-2">
                        <Checkbox
                            :model-value="form.permissions.includes(permission.name)"
                            :aria-label="permission.name"
                            @update:model-value="togglePermission(permission.name)"
                        />
                        <span class="cursor-pointer text-sm text-foreground select-none" @click="togglePermission(permission.name)">
                            {{ permission.name }}
                        </span>
                    </div>
                </div>
                <div v-if="form.errors.permissions" class="text-sm text-destructive">
                    {{ form.errors.permissions }}
                </div>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="form.processing" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="role-create-form" class="w-full sm:w-auto" :disabled="form.processing">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('creating') : t('create') }}
            </Button>
        </template>
    </BaseModal>
</template>
