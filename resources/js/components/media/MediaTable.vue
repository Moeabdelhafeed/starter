<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { Download, Eye, FileText, Film, RefreshCw, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { MediaAsset, MediaRow, Paginated } from '@/types';

const { t } = useI18n();

withDefaults(
    defineProps<{
        items: Paginated<MediaRow>;
        view?: 'table' | 'grid';
    }>(),
    { view: 'table' },
);

const emit = defineEmits<{
    (e: 'view', item: MediaRow): void;
    (e: 'replace', item: MediaRow): void;
    (e: 'remove', item: MediaRow): void;
}>();

const getGroupBadgeClass = (group: string) => {
    const classes: Record<string, string> = {
        app: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        web: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };
    return classes[group] || classes.app;
};

const getGroupLabel = (group: string) => ({ app: t('app_group'), web: t('web_group') })[group] || group;

// Each item carries its morph under its own type key — see MediaItem::toApi().
const assetOf = (item: MediaRow): MediaAsset | null => item[item.type] ?? null;
const assetUrl = (item: MediaRow): string | null => {
    const asset = assetOf(item);
    return asset?.image_api ?? asset?.video_api ?? asset?.file_api ?? null;
};
const posterUrl = (item: MediaRow): string | null => assetOf(item)?.thumbnail?.image_api ?? null;

const formatSize = (bytes?: number) => {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <!-- Table view -->
    <div v-if="view === 'table'" class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
            <Table :scroll-label="t('dynamic_storage')">
                <TableHeader>
                    <TableRow class="w-full text-start!">
                        <TableHead class="py-4 font-bold">{{ t('preview') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('key') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('group') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('sub_group') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('type') }}</TableHead>
                        <TableHead class="sticky-actions py-4 font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="items">
                        <TableRow v-for="item in items.data" :key="item.id" v-highlight="item.id">
                            <TableCell class="py-4">
                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-muted">
                                    <img
                                        v-if="item.type === 'image' && assetUrl(item)"
                                        :src="assetUrl(item) ?? undefined"
                                        :alt="item.key"
                                        class="h-full w-full object-cover"
                                    />
                                    <video
                                        v-else-if="item.type === 'video' && assetUrl(item)"
                                        :src="assetUrl(item) ?? undefined"
                                        :poster="posterUrl(item) ?? undefined"
                                        class="h-full w-full object-cover"
                                        muted
                                    />
                                    <Film v-else-if="item.type === 'video'" class="size-5 text-muted-foreground" />
                                    <FileText v-else class="size-5 text-muted-foreground" />
                                </div>
                            </TableCell>
                            <TableCell class="py-4 font-medium">{{ item.key }}</TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="getGroupBadgeClass(item.group)"
                                >
                                    {{ getGroupLabel(item.group) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="item.sub_group"
                                    class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ item.sub_group }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">{{ t(item.type) }}</TableCell>
                            <TableCell class="sticky-actions">
                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="assetUrl(item)"
                                        variant="outline"
                                        class="border-blue-500 text-blue-500 shadow-none! hover:bg-blue-500 hover:text-white"
                                        @click="emit('view', item)"
                                    >
                                        <Eye class="me-2 size-4" />
                                        {{ t('view') }}
                                    </Button>
                                    <Button
                                        v-if="assetUrl(item)"
                                        as-child
                                        variant="outline"
                                        class="border-primary/40 text-primary shadow-none! hover:bg-primary hover:text-primary-foreground"
                                    >
                                        <a :href="assetUrl(item) ?? undefined" target="_blank" rel="noopener" download>
                                            <Download class="me-2 size-4" />
                                            {{ t('download') }}
                                        </a>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                        @click="emit('replace', item)"
                                    >
                                        <RefreshCw class="me-2 size-4" />
                                        {{ t('change') }}
                                    </Button>
                                    <Button
                                        v-if="assetUrl(item)"
                                        variant="outline"
                                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        @click="emit('remove', item)"
                                    >
                                        <Trash2 class="me-2 size-4" />
                                        {{ t('remove') }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!items.data?.length" :colspan="6">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view -->
    <div v-else-if="!items.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="items">
        <div
            v-for="item in items.data"
            :key="item.id"
            v-highlight="item.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <div class="flex items-start justify-end">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getGroupBadgeClass(item.group)">
                    {{ getGroupLabel(item.group) }}
                </span>
            </div>

            <!-- Preview -->
            <div class="flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl bg-muted">
                <img
                    v-if="item.type === 'image' && assetUrl(item)"
                    :src="assetUrl(item) ?? undefined"
                    :alt="item.key"
                    class="h-full w-full object-cover"
                />
                <video
                    v-else-if="item.type === 'video' && assetUrl(item)"
                    :src="assetUrl(item) ?? undefined"
                    :poster="posterUrl(item) ?? undefined"
                    class="h-full w-full object-cover"
                    controls
                />
                <div v-else class="flex flex-col items-center gap-2 text-muted-foreground">
                    <FileText class="size-8" />
                    <span class="max-w-[80%] truncate text-xs">{{ assetOf(item)?.name }}</span>
                    <span v-if="assetOf(item)?.size" class="text-xs">{{ formatSize(assetOf(item)?.size) }}</span>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <h3 class="font-bold break-all text-foreground">{{ item.key }}</h3>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span v-if="item.sub_group" class="inline-flex items-center rounded-full bg-muted px-2 py-0.5">{{ item.sub_group }}</span>
                    <span>{{ t(item.type) }}</span>
                </div>
            </div>

            <div class="mt-auto flex flex-wrap items-center justify-end gap-2 border-t pt-4">
                <Button
                    v-if="assetUrl(item)"
                    variant="outline"
                    class="border-blue-500 text-blue-500 shadow-none! hover:bg-blue-500 hover:text-white"
                    @click="emit('view', item)"
                >
                    <Eye class="me-2 size-4" />
                    {{ t('view') }}
                </Button>
                <Button
                    v-if="assetUrl(item)"
                    as-child
                    variant="outline"
                    class="border-primary/40 text-primary shadow-none! hover:bg-primary hover:text-primary-foreground"
                >
                    <a :href="assetUrl(item) ?? undefined" target="_blank" rel="noopener" download>
                        <Download class="me-2 size-4" />
                        {{ t('download') }}
                    </a>
                </Button>
                <Button
                    variant="outline"
                    class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                    @click="emit('replace', item)"
                >
                    <RefreshCw class="me-2 size-4" />
                    {{ t('change') }}
                </Button>
                <Button
                    v-if="assetUrl(item)"
                    variant="outline"
                    class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                    @click="emit('remove', item)"
                >
                    <Trash2 class="me-2 size-4" />
                    {{ t('remove') }}
                </Button>
            </div>
        </div>
    </InfiniteScroll>
</template>
