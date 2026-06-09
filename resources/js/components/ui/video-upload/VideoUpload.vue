<script setup>
/**
 * Thin wrapper around ImageUpload preset for video files.
 * Same API: v-model (File), v-model:removed (Boolean), previewUrl, label, error.
 */
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';

const model = defineModel({ type: [Object, null], default: null });
const removed = defineModel('removed', { type: Boolean, default: false });

defineProps({
    previewUrl: { type: String, default: null },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    removable: { type: Boolean, default: true },
    /** Videos are larger — default 20MB. */
    maxSizeMb: { type: Number, default: 20 },
});
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
