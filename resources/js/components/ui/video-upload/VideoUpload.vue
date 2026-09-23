<script setup lang="ts">
/**
 * Thin wrapper around ImageUpload preset for video files.
 * Same API: v-model (File), v-model:removed (boolean), previewUrl, label, error.
 */
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';

const model = defineModel<File | null>({ default: null });
const removed = defineModel<boolean>('removed', { default: false });

withDefaults(
    defineProps<{
        previewUrl?: string | null;
        label?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        removable?: boolean;
        /** Videos are larger — default 20MB. */
        maxSizeMb?: number;
    }>(),
    {
        previewUrl: null,
        label: '',
        error: '',
        disabled: false,
        required: false,
        removable: true,
        maxSizeMb: 20,
    },
);
</script>

<template>
    <ImageUpload
        v-model="model"
        v-model:removed="removed"
        accept="video/*"
        :preview-url="previewUrl"
        :label="label"
        :error="error"
        :disabled="disabled"
        :required="required"
        :removable="removable"
        :max-size-mb="maxSizeMb"
    />
</template>
