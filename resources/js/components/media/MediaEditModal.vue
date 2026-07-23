<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { XIcon } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    item: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    file: null,
    _method: 'PUT',
});

// Restrict the picker to the current asset's kind; files accept anything.
const accept = computed(() => {
    if (props.item?.type === 'image') return 'image/*';
    if (props.item?.type === 'video') return 'video/*';
    return '*/*';
});

// Show the current asset as the preview for image/video.
const previewUrl = computed(() =>
    props.item && (props.item.type === 'image' || props.item.type === 'video') ? props.item.url : null,
);

const maxSizeMb = computed(() => (props.item?.type === 'video' ? 20 : props.item?.type === 'file' ? 10 : 2));

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
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ t('change_media') }}
                            </h3>
                            <button
                                @click="close"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                <XIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-foreground">{{ item?.key }}</p>
                                <p class="text-xs text-muted-foreground">{{ item?.group }} / {{ item?.sub_group || 'general' }} · {{ t(item?.type) }}</p>
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

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="close">{{ t('cancel') }}</Button>
                                <Button type="submit" :disabled="form.processing || !form.file">
                                    {{ form.processing ? t('saving') : t('save') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
