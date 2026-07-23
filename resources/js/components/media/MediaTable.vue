<script setup>
import { InfiniteScroll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FileText, Film, Download, RefreshCw, Trash2 } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    items: Object,
    view: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['replace', 'remove']);

const getGroupBadgeClass = (group) => {
    const classes = {
        app: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        web: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };
    return classes[group] || classes.app;
};

const getGroupLabel = (group) => ({ app: t('app_group'), web: t('web_group') }[group] || group);

const formatSize = (bytes) => {
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
            <Table>
                <TableHeader>
                    <TableRow class="w-full text-start!">
                        <TableHead class="py-4 font-bold">{{ t('preview') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('key') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('group') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('sub_group') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('type') }}</TableHead>
                        <TableHead class="py-4 font-bold sticky-actions">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="items">
                        <TableRow v-for="item in items.data" :key="item.id">
                            <TableCell class="py-4">
                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-muted">
                                    <img v-if="item.type === 'image' && item.url" :src="item.url" :alt="item.key" class="h-full w-full object-cover" />
                                    <video v-else-if="item.type === 'video' && item.url" :src="item.url" class="h-full w-full object-cover" muted />
                                    <Film v-else-if="item.type === 'video'" class="h-5 w-5 text-muted-foreground" />
                                    <FileText v-else class="h-5 w-5 text-muted-foreground" />
                                </div>
                            </TableCell>
                            <TableCell class="py-4 font-medium">{{ item.key }}</TableCell>
                            <TableCell>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getGroupBadgeClass(item.group)">
                                    {{ getGroupLabel(item.group) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span v-if="item.sub_group" class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                    {{ item.sub_group }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">{{ t(item.type) }}</TableCell>
                            <TableCell class="sticky-actions">
                                <div class="flex items-center gap-2">
                                    <a v-if="item.url" :href="item.url" target="_blank" rel="noopener" download>
                                        <Button variant="outline" class="border-primary/40 text-primary shadow-none! hover:bg-primary hover:text-primary-foreground">
                                            <Download class="me-2 size-4" />
                                            {{ t('download') }}
                                        </Button>
                                    </a>
                                    <Button
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                        @click="emit('replace', item)"
                                    >
                                        <RefreshCw class="me-2 size-4" />
                                        {{ t('change') }}
                                    </Button>
                                    <Button
                                        v-if="item.url"
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
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view -->
    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="items">
        <div v-for="item in items.data" :key="item.id" class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md">
            <div class="flex items-start justify-end">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getGroupBadgeClass(item.group)">
                    {{ getGroupLabel(item.group) }}
                </span>
            </div>

            <!-- Preview -->
            <div class="flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl bg-muted">
                <img v-if="item.type === 'image' && item.url" :src="item.url" :alt="item.key" class="h-full w-full object-cover" />
                <video v-else-if="item.type === 'video' && item.url" :src="item.url" class="h-full w-full object-cover" controls />
                <div v-else class="flex flex-col items-center gap-2 text-muted-foreground">
                    <FileText class="h-8 w-8" />
                    <span class="max-w-[80%] truncate text-xs">{{ item.name }}</span>
                    <span v-if="item.size" class="text-xs">{{ formatSize(item.size) }}</span>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <h3 class="break-all font-bold text-foreground">{{ item.key }}</h3>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span v-if="item.sub_group" class="inline-flex items-center rounded-full bg-muted px-2 py-0.5">{{ item.sub_group }}</span>
                    <span>{{ t(item.type) }}</span>
                </div>
            </div>

            <div class="mt-auto flex items-center justify-end gap-2 border-t pt-4">
                <a v-if="item.url" :href="item.url" target="_blank" rel="noopener" download>
                    <Button variant="outline" class="border-primary/40 text-primary shadow-none! hover:bg-primary hover:text-primary-foreground">
                        <Download class="me-2 size-4" />
                        {{ t('download') }}
                    </Button>
                </a>
                <Button
                    variant="outline"
                    class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                    @click="emit('replace', item)"
                >
                    <RefreshCw class="me-2 size-4" />
                    {{ t('change') }}
                </Button>
                <Button
                    v-if="item.url"
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
