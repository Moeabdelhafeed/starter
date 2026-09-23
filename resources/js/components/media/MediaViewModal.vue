<script setup lang="ts">
import { Download, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import type { MediaAsset, MediaRow } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    isOpen: boolean;
    item: MediaRow | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// Each item carries its morph under its own type key — see MediaItem::toApi().
const asset = computed<MediaAsset | null>(() => (props.item ? (props.item[props.item.type] ?? null) : null));
const assetUrl = computed(() => asset.value?.image_api ?? asset.value?.video_api ?? asset.value?.file_api ?? null);
const posterUrl = computed(() => asset.value?.thumbnail?.image_api ?? null);

const formatSize = (bytes?: number) => {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
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
            <div v-if="isOpen && item" class="fixed inset-0 z-50 overflow-y-auto" @click.self="emit('close')">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="emit('close')"></div>

                <div class="relative flex min-h-full items-center justify-center p-4" @click.self="emit('close')">
                    <div class="relative w-full max-w-3xl transform overflow-hidden rounded-2xl bg-card p-6 text-start shadow-xl transition-all">
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div class="flex min-w-0 flex-col">
                                <h3 class="truncate text-lg font-semibold text-foreground" :title="item.key">{{ item.key }}</h3>
                                <span class="text-xs text-muted-foreground">{{ t(item.type) }}</span>
                            </div>
                            <Button variant="outline" size="sm" class="whitespace-nowrap" @click="emit('close')">{{ t('close') }}</Button>
                        </div>

                        <!-- The asset at a size worth looking at. The table's 48px thumbnail
                             answers "is something here"; this answers "is it the right one". -->
                        <div class="flex max-h-[65vh] min-h-[16rem] items-center justify-center overflow-hidden rounded-xl bg-muted">
                            <img
                                v-if="item.type === 'image' && assetUrl"
                                :src="assetUrl"
                                :alt="item.key"
                                class="max-h-[65vh] w-auto max-w-full object-contain"
                            />
                            <video
                                v-else-if="item.type === 'video' && assetUrl"
                                :src="assetUrl"
                                :poster="posterUrl ?? undefined"
                                class="max-h-[65vh] w-auto max-w-full"
                                controls
                                autoplay
                            />
                            <!-- A PDF or a CSV has nothing to render inline; say what it is
                                 and hand over the file instead of showing an empty box. -->
                            <div v-else class="flex flex-col items-center gap-2 p-10 text-muted-foreground">
                                <FileText class="h-10 w-10" />
                                <span class="max-w-full truncate text-sm">{{ asset?.name ?? t('no_file') }}</span>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5">{{ item.group }}</span>
                                <span v-if="item.sub_group" class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5">
                                    {{ item.sub_group }}
                                </span>
                                <span v-if="asset?.name" class="truncate">{{ asset.name }}</span>
                                <span v-if="asset?.size">{{ formatSize(asset.size) }}</span>
                            </div>

                            <a v-if="assetUrl" :href="assetUrl" target="_blank" rel="noopener" download>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-primary/40 whitespace-nowrap text-primary shadow-none! hover:bg-primary hover:text-primary-foreground"
                                >
                                    <Download class="me-2 h-4 w-4" />
                                    {{ t('download') }}
                                </Button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
