<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import { BaseModal } from '@/components/ui/modal';
import type { MediaAsset, MediaRow } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    isOpen: boolean;
    item: MediaRow | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm<{ file: File | null; thumbnail: File | null; _method: string }>({
    file: null,
    thumbnail: null,
    _method: 'PUT',
});

// Restrict the picker to the current asset's kind; files accept anything.
const accept = computed(() => {
    if (props.item?.type === 'image') return 'image/*';
    if (props.item?.type === 'video') return 'video/*';
    return '*/*';
});

// Show the current asset as the preview for image/video.
// The morph lives under the item's type key — see MediaItem::toApi().
const asset = computed<MediaAsset | null>(() => (props.item ? (props.item[props.item.type] ?? null) : null));
const previewUrl = computed(() => asset.value?.image_api ?? asset.value?.video_api ?? undefined);

// A swap can change the type, so follow the picked file and fall back to the stored one.
const isVideo = computed(() => (form.file ? (form.file.type || '').startsWith('video/') : props.item?.type === 'video'));

const maxSizeMb = computed(() => (props.item?.type === 'video' ? 20 : props.item?.type === 'file' ? 10 : 2));

watch(isVideo, (video) => {
    if (!video) form.thumbnail = null;
});

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (!props.item) return;

    form.post(route('media.update', props.item.id), {
        preserveScroll: true,
        preserveState: true,
        reset: ['items', 'success', 'error', 'filters'],
        forceFormData: true,
        onSuccess: () => close(),
    });
};
</script>

<template>
    <BaseModal :open="isOpen" :title="t('change_media')" size="md" :busy="form.processing" @close="close">
        <form id="media-edit-form" @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1">
                <p class="text-sm font-medium text-foreground">{{ item?.key }}</p>
                <p class="text-xs text-muted-foreground">{{ item?.group }} / {{ item?.sub_group || 'general' }} · {{ item ? t(item.type) : '' }}</p>
            </div>

            <ImageUpload
                v-model="form.file"
                :accept="accept"
                :preview-url="previewUrl"
                :removable="false"
                :max-size-mb="maxSizeMb"
                :label="t('change')"
                :error="form.errors.file"
            />

            <div v-if="isVideo" class="space-y-1">
                <ImageUpload
                    v-model="form.thumbnail"
                    accept="image/*"
                    :preview-url="asset?.thumbnail?.image_api"
                    :removable="false"
                    :max-size-mb="2"
                    :label="t('video_thumbnail')"
                    :error="form.errors.thumbnail"
                />
                <p class="text-xs text-muted-foreground">{{ t('video_thumbnail_hint') }}</p>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="close">
                {{ t('cancel') }}
            </Button>
            <Button type="submit" form="media-edit-form" :disabled="form.processing || !form.file" class="w-full sm:w-auto">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? t('saving') : t('save') }}
            </Button>
        </template>
    </BaseModal>
</template>
