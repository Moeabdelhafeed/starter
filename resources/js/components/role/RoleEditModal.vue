<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { BaseModal } from '@/components/ui/modal';
import type { Permission } from '@/types';

interface EditableRole {
    id: number;
    name: string;
    permissions?: Permission[];
}

const { t } = useI18n();

const props = defineProps<{
    isOpen: boolean;
    role: EditableRole | null;
    permissions: Permission[];
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm({
    id: null as number | null,
    name: '',
    permissions: [] as string[],
});

/** `super_admin` and `fallback` are seeded roles whose permissions must stay untouched. */
const isLocked = computed(() => form.name === 'super_admin' || form.name === 'fallback');

const populateForm = () => {
    const newRole = props.role;
    if (!newRole) return;
    form.id = newRole.id;
    form.name = newRole.name;
    form.permissions = newRole.permissions ? newRole.permissions.map((p) => p.name) : [];
};

watch(() => props.role, populateForm, { immediate: true });
watch(
    () => props.isOpen,
    (open) => {
        if (open) populateForm();
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
    // POST + _method spoof — the production host blocks real PUT.
    form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('roles.update', form.id as number), {
        preserveScroll: true,
        preserveState: true,
        reset: ['roles', 'success', 'error', 'filters'],
        onSuccess: () => {
            close();
        },
    });
};

const updatePermission = (permissionName: string, checked: boolean) => {
    if (isLocked.value) return;

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
    <BaseModal :open="isOpen" :title="t('edit_role')" size="lg" :busy="form.processing" @close="close">
        <form id="role-edit-form" class="space-y-5" @submit.prevent="submit">
            <!-- Name -->
            <div class="space-y-2">
                <label for="role-edit-name" class="block text-sm font-medium text-foreground">{{ t('name') }}</label>
                <Input
                    id="role-edit-name"
                    v-model="form.name"
                    type="text"
                    :placeholder="t('name')"
                    disabled
                    class="border-border bg-muted/50 text-muted-foreground!"
                    :aria-invalid="form.errors.name ? true : undefined"
                    :aria-describedby="form.errors.name ? 'role-edit-name-error' : undefined"
                />
                <div v-if="form.errors.name" id="role-edit-name-error" class="text-sm text-destructive">
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
                            :disabled="isLocked"
                            :aria-label="permission.name"
                            @update:model-value="togglePermission(permission.name)"
                        />
                        <span
                            class="text-sm text-foreground select-none"
                            :class="isLocked ? 'cursor-not-allowed' : 'cursor-pointer'"
                            @click="togglePermission(permission.name)"
                        >
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
            <Button type="submit" form="role-edit-form" class="w-full sm:w-auto" :disabled="form.processing">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save') }}
            </Button>
        </template>
    </BaseModal>
</template>
