<script setup>
import { useI18n } from 'vue-i18n';
import { Loader2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { ref } from 'vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    count: {
        type: Number,
        default: 0,
    },
    message: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

const processing = ref(false);

const close = () => {
    if (!processing.value) {
        emit('close');
    }
};

const submit = () => {
    processing.value = true;
    emit('confirm', () => {
        processing.value = false;
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
                            <h3 class="text-lg font-semibold text-foreground">{{ t('bulk_delete') }}</h3>
                        </div>

                        <!-- Modal Body -->
                        <div class="mb-6">
                            <p class="text-sm text-muted-foreground">
                                {{ message || t('confirm_bulk_delete') }}
                            </p>
                            <p class="mt-2 text-sm font-medium text-foreground">
                                {{ t('items_selected', { count }) }}
                            </p>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" @click="close" :disabled="processing">
                                {{ t('cancel') }}
                            </Button>
                            <Button
                                type="button"
                                class="bg-red-600 text-white hover:bg-red-700 focus:ring-red-500"
                                :disabled="processing"
                                @click="submit"
                            >
                                <Loader2 v-if="processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ processing ? t('deleting') : t('delete') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
