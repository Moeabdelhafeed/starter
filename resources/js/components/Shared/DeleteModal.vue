<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import ConfirmDialog from '@/components/ui/modal/ConfirmDialog.vue';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        isOpen?: boolean;
        title: string;
        message: string;
        routeName: string;
        itemId?: number | string | null;
        resetKeys?: string[];
    }>(),
    { isOpen: false, itemId: null, resetKeys: () => ['success', 'error', 'filters'] },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm({});

const close = () => emit('close');

const submit = () => {
    if (!props.itemId) return;

    // POST + _method spoof — the production host blocks real DELETE.
    form.transform((data) => ({ ...data, _method: 'DELETE' })).post(route(props.routeName, props.itemId), {
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
        :title="title"
        :message="message"
        :icon="Trash2"
        variant="destructive"
        :processing="form.processing"
        :errors="form.errors"
        :confirm-label="form.processing ? t('deleting') : t('delete')"
        @close="close"
        @confirm="submit"
    >
        <slot />
    </ConfirmDialog>
</template>
