<script setup lang="ts">
import { ImageIcon, UploadCloud, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        /** The newly picked File (v-model). */
        modelValue?: File | null;
        /** Whether the existing image is flagged for removal (v-model:removed). */
        removed?: boolean;
        /** URL of an already-saved image to show as the initial preview. */
        previewUrl?: string | null;
        accept?: string;
        label?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        /** Show the remove (X) button on the preview. */
        removable?: boolean;
        /** Max size in MB; rejects larger files with an inline message. */
        maxSizeMb?: number;
        /** Preview crop shape. */
        shape?: 'square' | 'circle';
    }>(),
    {
        modelValue: null,
        removed: false,
        previewUrl: null,
        accept: 'image/*',
        label: '',
        error: '',
        disabled: false,
        required: false,
        removable: true,
        maxSizeMb: 2,
        shape: 'square',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: File | null): void;
    (e: 'update:removed', value: boolean): void;
}>();

const dragOver = ref(false);
const localError = ref('');
const objectUrl = ref<string | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

const isVideo = computed(() => props.accept.includes('video'));

const preview = computed(() => {
    if (objectUrl.value) return objectUrl.value;
    if (props.removed) return null;
    return props.previewUrl;
});

const hasPreview = computed(() => !!preview.value);

function revokeUrl(): void {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
        objectUrl.value = null;
    }
}

function setFile(file: File): void {
    localError.value = '';

    if (props.maxSizeMb && file.size > props.maxSizeMb * 1024 * 1024) {
        localError.value = t('file_too_large', { size: props.maxSizeMb });
        return;
    }

    revokeUrl();
    objectUrl.value = URL.createObjectURL(file);
    emit('update:modelValue', file);
    emit('update:removed', false);
}

function onPick(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) setFile(file);
}

function onDrop(e: DragEvent): void {
    dragOver.value = false;
    if (props.disabled) return;
    const file = e.dataTransfer?.files?.[0];
    if (file) setFile(file);
}

function openPicker(): void {
    if (!props.disabled) inputEl.value?.click();
}

function remove(): void {
    revokeUrl();
    localError.value = '';
    if (inputEl.value) inputEl.value.value = '';
    emit('update:modelValue', null);
    // Only flag removal when there was a saved image to delete server-side.
    emit('update:removed', !!props.previewUrl);
}

// If the picked file is cleared externally, drop the local object URL.
watch(
    () => props.modelValue,
    (val) => {
        if (!val) revokeUrl();
    },
);

onBeforeUnmount(revokeUrl);

const shownError = computed(() => props.error || localError.value);
</script>

<template>
    <div class="space-y-2">
        <label v-if="label" class="block text-sm font-medium text-foreground">
            {{ label }} <span v-if="required" class="text-destructive">*</span>
        </label>

        <div
            class="group relative flex min-h-40 w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed p-4 text-center transition-colors"
            :class="[
                dragOver ? 'border-primary bg-primary/5' : 'border-input bg-muted/30 hover:border-primary/60 hover:bg-muted/50',
                disabled ? 'pointer-events-none opacity-60' : '',
            ]"
            role="button"
            tabindex="0"
            @click="openPicker"
            @keydown.enter.prevent="openPicker"
            @keydown.space.prevent="openPicker"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
        >
            <!-- Preview -->
            <template v-if="hasPreview">
                <video
                    v-if="isVideo"
                    :src="preview ?? undefined"
                    class="max-h-40 w-auto rounded-xl object-contain"
                    controls
                    @click.stop
                />
                <img
                    v-else
                    :src="preview ?? undefined"
                    alt=""
                    class="max-h-40 w-auto object-contain"
                    :class="shape === 'circle' ? 'h-28 w-28 rounded-full object-cover' : 'rounded-xl'"
                />

                <button
                    v-if="removable"
                    type="button"
                    class="absolute end-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-destructive/90 text-destructive-foreground shadow-sm transition-colors hover:bg-destructive"
                    :title="t('remove')"
                    @click.stop="remove"
                >
                    <X class="h-4 w-4" />
                </button>

                <p class="mt-1 text-xs text-muted-foreground">{{ t('click_or_drag_to_replace') }}</p>
            </template>

            <!-- Empty state -->
            <template v-else>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <ImageIcon v-if="!isVideo" class="h-6 w-6" />
                    <UploadCloud v-else class="h-6 w-6" />
                </div>
                <p class="text-sm font-medium text-foreground">{{ t('drag_drop_or_click') }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ isVideo ? t('video_size_hint', { size: maxSizeMb }) : t('max_size_mb', { size: maxSizeMb }) }}
                </p>
            </template>

            <input
                ref="inputEl"
                type="file"
                :accept="accept"
                class="hidden"
                :disabled="disabled"
                @change="onPick"
            />
        </div>

        <p v-if="shownError" class="text-sm text-destructive">{{ shownError }}</p>
    </div>
</template>
