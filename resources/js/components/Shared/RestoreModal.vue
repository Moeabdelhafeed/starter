<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import ConfirmDialog from '@/components/ui/modal/ConfirmDialog.vue';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        isOpen?: boolean;
        title?: string;
        message?: string;
        routeName: string;
        itemId?: number | string | null;
        resetKeys?: string[];
    }>(),
    { isOpen: false, title: '', message: '', itemId: null, resetKeys: () => ['success', 'error', 'filters'] },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm({});

const close = () => emit('close');

const submit = () => {
    if (!props.itemId) return;

    form.post(route(props.routeName, props.itemId), {
        preserveScroll: true,
        preserveState: true,
        reset: props.resetKeys,
        onSuccess: close,
    });
};
</script>

<template>
    <ConfirmDialog
        :open="isOpen"
        :title="title || t('restore_item')"
        :message="message || t('restore_confirmation')"
        :icon="RotateCcw"
        variant="info"
        :processing="form.processing"
        :errors="form.errors"
        :confirm-label="form.processing ? t('restoring') : t('restore')"
        @close="close"
        @confirm="submit"
    >
        <slot />
    </ConfirmDialog>
</template>
