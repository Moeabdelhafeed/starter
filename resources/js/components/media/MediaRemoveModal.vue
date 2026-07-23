<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertTriangle } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    item: Object,
});

const emit = defineEmits(['close', 'confirm']);

const processing = ref(false);

const confirm = () => {
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
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="emit('close')">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-500">
                                <AlertTriangle class="h-5 w-5" />
                            </div>
                            <h3 class="text-lg font-semibold text-foreground">{{ t('remove_media') }}</h3>
                        </div>

                        <p class="mb-6 text-sm text-muted-foreground">
                            {{ t('confirm_remove_media') }}
                        </p>

                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" :disabled="processing" @click="emit('close')">
                                {{ t('cancel') }}
                            </Button>
                            <Button
                                type="button"
                                class="bg-red-500 text-white hover:bg-red-600"
                                :disabled="processing"
                                @click="confirm"
                            >
                                {{ processing ? t('saving') : t('remove') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
