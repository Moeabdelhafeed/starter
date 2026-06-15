<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { XIcon, Loader2, Check, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    role: Object,
    permissions: Array,
});

const emit = defineEmits(['close']);

const form = useForm({
    id: null,
    name: '',
    permissions: [],
});

const populateForm = () => {
    const newRole = props.role;
    if (!newRole) return;
    form.id = newRole.id;
    form.name = newRole.name;
    form.permissions = newRole.permissions ? newRole.permissions.map((p) => p.name) : [];
};

watch(() => props.role, populateForm, { immediate: true });
watch(() => props.isOpen, (open) => {
    if (open) populateForm();
});

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submit = () => {
    form.put(route('roles.update', form.id), {
        preserveScroll: true,
        preserveState: true,
        reset: ['roles', 'success', 'error', 'filters'],
        onSuccess: () => {
            close();
        },
    });
};

const updatePermission = (permissionName, checked) => {
    if (form.name === 'super_admin' || form.name === 'fallback') return;

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
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <!-- Modal Container -->
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <!-- Modal Content -->
                    <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Modal Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ t('edit_role') }}
                            </h3>
                            <button
                                @click="close"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                <XIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Error Box -->
                        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="mb-2 flex items-center gap-2">
                                <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                            </div>
                            <ul class="space-y-1 ps-6">
                                <li v-if="form.errors.name" class="text-sm text-red-600">
                                    <span class="font-medium">{{ t('name') }}</span>: {{ form.errors.name }}
                                </li>
                                <li v-if="form.errors.permissions" class="text-sm text-red-600">
                                    <span class="font-medium">{{ t('permissions') }}</span>: {{ form.errors.permissions }}
                                </li>
                            </ul>
                        </div>

                        <!-- Modal Body -->
                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Name -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('name') }}</label>
                                <Input v-model="form.name" type="text" :placeholder="t('name')" disabled class="bg-muted/50 border-border text-muted-foreground!" />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Permissions -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('permissions') }}</label>
                                <div class="grid max-h-60 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-2">
                                    <div v-for="permission in permissions" :key="permission.id" class="flex items-center gap-2">
                                        <div
                                            @click="updatePermission(permission.name, !form.permissions.includes(permission.name))"
                                            class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-md border transition-all duration-200"
                                            :class="[
                                                form.permissions.includes(permission.name)
                                                    ? 'border-primary bg-primary text-white'
                                                    : 'border-border bg-card hover:border-primary',
                                                form.name === 'super_admin' || form.name === 'fallback' ? 'opacity-60 cursor-not-allowed' : '',
                                            ]"
                                        >
                                            <Check v-if="form.permissions.includes(permission.name)" class="h-3.5 w-3.5 stroke-[3]" />
                                        </div>
                                        <label
                                            class="cursor-pointer text-sm text-foreground select-none"
                                            @click="updatePermission(permission.name, !form.permissions.includes(permission.name))"
                                        >
                                            {{ permission.name }}
                                        </label>
                                    </div>
                                </div>
                                <div v-if="form.errors.permissions" class="text-sm text-red-600">
                                    {{ form.errors.permissions }}
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex gap-3 pt-4">
                                <Button type="button" variant="outline" @click="close" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing" class="flex-1">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? t('saving') : t('save') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
