<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { ConfirmDialog } from '@/components/ui/modal';
import type { MediaRow } from '@/types';

const { t } = useI18n();

defineProps<{
    isOpen: boolean;
    item: MediaRow | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    /** The page owns the request; `done` clears the busy state either way. */
    (e: 'confirm', done: () => void): void;
}>();

const processing = ref(false);

const confirm = () => {
    processing.value = true;
    emit('confirm', () => {
        processing.value = false;
    });
};
</script>

<template>
    <ConfirmDialog
        :open="isOpen"
        :title="t('remove_media')"
        :message="t('confirm_remove_media')"
        :confirm-label="processing ? t('saving') : t('remove')"
        variant="destructive"
        :icon="AlertTriangle"
        :processing="processing"
        @close="emit('close')"
        @confirm="confirm"
    />
</template>
