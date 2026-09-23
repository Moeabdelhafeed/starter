<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import BaseModal from './BaseModal.vue';

/** Destructive/irreversible confirm flows. Built on BaseModal. */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        message?: string;
        /** Extra emphasised line under the message (e.g. "this cannot be undone"). */
        warningText?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'default' | 'destructive' | 'warning' | 'info';
        /** lucide-vue-next component shown in the tinted header circle. */
        icon?: Component;
        processing?: boolean;
        /** Inertia form errors, rendered as an error box. */
        errors?: Record<string, string>;
        size?: 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    { variant: 'destructive', processing: false, size: 'sm' },
);

const emit = defineEmits<{ (e: 'close'): void; (e: 'confirm'): void }>();

const { t } = useI18n();

const iconTint = computed(
    () =>
        ({
            default: 'text-primary',
            destructive: 'text-destructive',
            warning: 'text-warning',
            info: 'text-info',
        })[props.variant],
);

const buttonVariant = computed(
    () => ({ default: 'default', destructive: 'destructive', warning: 'warning', info: 'default' })[props.variant] as
        | 'default'
        | 'destructive'
        | 'warning',
);

const errorList = computed(() => Object.values(props.errors ?? {}));
</script>

<template>
    <BaseModal
        :open="open"
        :title="title"
        :size="size"
        :busy="processing"
        @close="emit('close')"
    >
        <template v-if="icon" #icon>
            <component :is="icon" :class="['h-5 w-5 shrink-0', iconTint]" aria-hidden="true" />
        </template>

        <div
            v-if="errorList.length"
            class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-4"
            role="alert"
        >
            <div class="mb-2 flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0 text-destructive" />
                <p class="text-sm font-semibold text-destructive">{{ t('please_fix_errors') }}</p>
            </div>
            <ul class="space-y-1 ps-6">
                <li v-for="(error, index) in errorList" :key="index" class="text-sm text-destructive">{{ error }}</li>
            </ul>
        </div>

        <p v-if="message" class="text-sm text-muted-foreground">{{ message }}</p>
        <p v-if="warningText" class="mt-2 text-sm font-medium text-destructive">{{ warningText }}</p>

        <slot />

        <template #footer>
            <Button type="button" variant="outline" :disabled="processing" @click="emit('close')">
                {{ cancelLabel || t('cancel') }}
            </Button>
            <Button type="button" :variant="buttonVariant" :disabled="processing" @click="emit('confirm')">
                <Loader2 v-if="processing" class="me-2 h-4 w-4 animate-spin" />
                {{ confirmLabel || t('confirm') }}
            </Button>
        </template>
    </BaseModal>
</template>
