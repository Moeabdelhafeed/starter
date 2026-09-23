<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Paginated, TranslationRow } from '@/types';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        translations: Paginated<TranslationRow>;
        activeLocale?: string;
        view?: 'table' | 'grid';
    }>(),
    { activeLocale: '', view: 'table' },
);

const emit = defineEmits<{ (e: 'edit', translation: TranslationRow): void }>();

const getGroupBadgeClass = (group: string) => {
    const classes: Record<string, string> = {
        api: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        app: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        web: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };
    return classes[group] || classes.app;
};

const getGroupLabel = (group: string) => {
    const labels: Record<string, string> = {
        api: t('api_group'),
        app: t('app_group'),
        web: t('web_group'),
    };
    return labels[group] || group;
};

const localeValue = (row: TranslationRow) => (props.activeLocale ? (row[props.activeLocale] as string | null) : null);
</script>

<template>
    <!-- Table view -->
    <div v-if="view === 'table'" class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
            <Table :scroll-label="t('translations')">
                <TableHeader>
                    <TableRow class="w-full">
                        <TableHead class="py-4 font-bold">{{ t('key') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('group') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('sub_group') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('value') }}</TableHead>
                        <TableHead class="sticky-actions py-4 text-end font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="translations">
                        <TableRow v-for="(trans, index) in translations.data" :key="index">
                            <TableCell class="py-4 font-medium">{{ trans.key }}</TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="getGroupBadgeClass(trans.group)"
                                >
                                    {{ getGroupLabel(trans.group) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="trans.sub_group"
                                    class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ trans.sub_group }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell>{{ localeValue(trans) }}</TableCell>
                            <TableCell class="sticky-actions text-end">
                                <Button
                                    variant="outline"
                                    class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                    @click="emit('edit', trans)"
                                >
                                    {{ t('edit') }}
                                </Button>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!translations.data?.length" :colspan="5">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view -->
    <div v-else-if="!translations.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="translations">
        <div
            v-for="(trans, index) in translations.data"
            :key="index"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Key + group -->
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-bold break-all text-foreground">{{ trans.key }}</h3>
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getGroupBadgeClass(trans.group)">
                        {{ getGroupLabel(trans.group) }}
                    </span>
                    <span
                        v-if="trans.sub_group"
                        class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        {{ trans.sub_group }}
                    </span>
                </div>
            </div>

            <!-- Value -->
            <div class="flex flex-col gap-1">
                <span class="text-xs font-medium text-muted-foreground">{{ t('value') }}</span>
                <p class="text-sm text-foreground">{{ localeValue(trans) }}</p>
            </div>

            <!-- Actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <Button
                    variant="outline"
                    class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                    @click="emit('edit', trans)"
                >
                    {{ t('edit') }}
                </Button>
            </div>
        </div>
    </InfiniteScroll>
</template>
