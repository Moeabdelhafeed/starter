<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Loader2, AlertCircle } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    routeName: {
        type: String,
        required: true,
    },
    itemId: {
        type: [Number, String],
        default: null,
    },
    resetKeys: {
        type: Array,
        default: () => ['success', 'error', 'filters'],
    },
});

const emit = defineEmits(['close']);

const form = useForm({});

const close = () => {
    emit('close');
};

const submit = () => {
    if (!props.itemId) return;

    // POST + _method spoof — the production host blocks real DELETE.
    form.transform((data) => ({ ...data, _method: 'DELETE' })).post(route(props.routeName, props.itemId), {
        preserveScroll: true,
        preserveState: true,
        reset: props.resetKeys,
        onSuccess: () => {
            close();
        },
    });
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
                    <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Modal Header -->
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-foreground">{{ title }}</h3>
                        </div>

                        <!-- Error Box -->
                        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="mb-2 flex items-center gap-2">
                                <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                            </div>
                            <ul class="space-y-1 ps-6">
                                <li v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>

                        <!-- Modal Body -->
                        <div class="mb-6">
                            <p class="text-sm text-muted-foreground">
                                {{ message }}
                            </p>
                            <slot />
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" @click="close">
                                {{ t('cancel') }}
                            </Button>
                            <Button
                                type="button"
                                class="bg-red-600 text-white hover:bg-red-700 focus:ring-red-500"
                                :disabled="form.processing"
                                @click="submit"
                            >
                                <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ form.processing ? t('deleting') : t('delete') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
