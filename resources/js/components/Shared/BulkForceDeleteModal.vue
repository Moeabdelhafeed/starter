<script setup lang="ts">
import { Trash } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import ConfirmDialog from '@/components/ui/modal/ConfirmDialog.vue';

const { t } = useI18n();

withDefaults(
    defineProps<{
        isOpen?: boolean;
        count?: number;
        message?: string;
    }>(),
    { isOpen: false, count: 0, message: '' },
);

const emit = defineEmits<{
    (e: 'close'): void;
    /** `done` re-enables the confirm button once the parent's request settles. */
    (e: 'confirm', done: () => void): void;
}>();

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
    <ConfirmDialog
        :open="isOpen"
        :title="t('bulk_force_delete')"
        :message="message || t('confirm_bulk_force_delete')"
        :warning-text="t('force_delete_warning')"
        :icon="Trash"
        variant="destructive"
        :processing="processing"
        :confirm-label="processing ? t('deleting') : t('force_delete')"
        @close="close"
        @confirm="submit"
    >
        <p class="mt-2 text-sm font-medium text-foreground">{{ t('items_selected', { count }) }}</p>
    </ConfirmDialog>
</template>
