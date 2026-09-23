<script lang="ts">
/** The columns this table actually reads. */
export interface ActivityLogRow {
    id: number;
    action: string;
    causer_name?: string | null;
    causer_email?: string | null;
    subject_type?: string | null;
    subject_id?: number | string | null;
    created_at: string;
    old_data?: Record<string, unknown> | null;
    new_data?: Record<string, unknown> | null;
}
</script>

<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { Clock, Eye, Trash2, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import type { Paginated } from '@/types';

const { formatDate } = useDateFormat();

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        logs: Paginated<ActivityLogRow>;
        selectedIds?: number[];
        view?: 'table' | 'grid';
    }>(),
    {
        selectedIds: () => [],
        view: 'table',
    },
);

const emit = defineEmits<{
    (e: 'view' | 'delete', log: ActivityLogRow): void;
    (e: 'update:selectedIds', ids: number[]): void;
}>();

/** Checkbox emits `boolean | unknown[]`; in multi-select mode it is always the id array. */
const onSelectionChange = (value: unknown) => emit('update:selectedIds', value as number[]);

const isAllSelected = computed({
    get: () => props.logs.data.length > 0 && props.selectedIds.length === props.logs.data.length,
    set: (value: boolean) => {
        if (value) {
            emit(
                'update:selectedIds',
                props.logs.data.map((l) => l.id),
            );
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const getActionColor = (action: string): string => {
    switch (action) {
        case 'created':
            return 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20 dark:bg-emerald-400/15 dark:text-emerald-300 dark:ring-emerald-400/30';
        case 'updated':
            return 'bg-amber-500/10 text-amber-600 ring-amber-500/20 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/30';
        case 'deleted':
            return 'bg-rose-500/10 text-rose-600 ring-rose-500/20 dark:bg-rose-400/15 dark:text-rose-300 dark:ring-rose-400/30';
        default:
            return 'bg-blue-500/10 text-blue-600 ring-blue-500/20 dark:bg-blue-400/15 dark:text-blue-300 dark:ring-blue-400/30';
    }
};

const getModelName = (subjectType?: string | null): string => {
    if (!subjectType) return 'N/A';
    const parts = subjectType.split('\\');
    return parts[parts.length - 1];
};
</script>

<template>
    <!-- Table view -->
    <div v-if="view === 'table'" class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow class="w-full text-start!">
                        <TableHead class="w-10 py-4">
                            <Checkbox v-model="isAllSelected" :aria-label="t('select_all')" />
                        </TableHead>
                        <TableHead class="py-4 font-bold">{{ t('user') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('action') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('target') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('date') }}</TableHead>
                        <TableHead class="sticky-actions py-4 text-end font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="logs">
                        <TableRow v-for="log in logs.data" :key="log.id" v-highlight="log.id" class="group">
                            <TableCell class="py-4">
                                <Checkbox
                                    :model-value="selectedIds"
                                    :value="log.id"
                                    :aria-label="log.causer_name || String(log.id)"
                                    @update:model-value="onSelectionChange"
                                />
                            </TableCell>
                            <TableCell class="py-4 text-start!">
                                <div class="flex max-w-[220px] min-w-0 flex-col">
                                    <span class="truncate font-bold text-foreground" :title="log.causer_name ?? ''">{{ log.causer_name }}</span>
                                    <span class="truncate text-xs text-muted-foreground" :title="log.causer_email ?? ''">{{ log.causer_email }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                        getActionColor(log.action),
                                    ]"
                                >
                                    {{ t(log.action) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span
                                    class="rounded-md border border-primary/40 bg-primary/20 px-2 py-1 text-[10px] font-bold text-primary uppercase"
                                    :title="`${getModelName(log.subject_type)} · ID: ${log.subject_id}`"
                                >
                                    {{ getModelName(log.subject_type) }}
                                </span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                <div class="flex items-center gap-2 text-xs whitespace-nowrap">
                                    <Clock class="size-3.5" />
                                    {{ formatDate(log.created_at) }}
                                </div>
                            </TableCell>
                            <TableCell class="sticky-actions text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        size="icon-sm"
                                        variant="outline"
                                        :title="t('view_details')"
                                        :aria-label="t('view_details')"
                                        class="border-primary/50 text-primary shadow-none! hover:bg-primary hover:text-white"
                                        @click="emit('view', log)"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        size="icon-sm"
                                        variant="outline"
                                        :title="t('delete')"
                                        :aria-label="t('delete')"
                                        class="border-red-500/50 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        @click="emit('delete', log)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!logs.data?.length" :colspan="6">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view — empty -->
    <div v-else-if="!logs.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <!-- Grid view -->
    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="logs">
        <div
            v-for="log in logs.data"
            :key="log.id"
            v-highlight="log.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: checkbox + action badge -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox
                    :model-value="selectedIds"
                    :value="log.id"
                    :aria-label="log.causer_name || String(log.id)"
                    @update:model-value="onSelectionChange"
                />
                <span :class="['inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', getActionColor(log.action)]">
                    {{ t(log.action) }}
                </span>
            </div>

            <!-- Causer -->
            <div class="flex items-center gap-2">
                <User class="size-4 shrink-0 text-muted-foreground" />
                <div class="min-w-0">
                    <p class="mb-1 truncate leading-none font-bold text-foreground">{{ log.causer_name }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ log.causer_email }}</p>
                </div>
            </div>

            <!-- Target -->
            <div class="flex flex-col gap-1">
                <span class="text-xs font-medium text-muted-foreground">{{ t('target') }}</span>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md border border-primary/40 bg-primary/20 px-2 py-1 text-[10px] font-bold text-primary uppercase">
                        {{ getModelName(log.subject_type) }}
                    </span>
                    <span class="text-xs text-muted-foreground">ID: {{ log.subject_id }}</span>
                </div>
            </div>

            <!-- Date -->
            <div class="flex flex-col gap-1">
                <span class="text-xs font-medium text-muted-foreground">{{ t('date') }}</span>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <Clock class="size-3.5" />
                    {{ formatDate(log.created_at) }}
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-auto flex items-center justify-end gap-2 border-t pt-4">
                <Button
                    size="icon-sm"
                    variant="outline"
                    :title="t('view_details')"
                    :aria-label="t('view_details')"
                    class="border-primary/50 text-primary shadow-none! hover:bg-primary hover:text-white"
                    @click="emit('view', log)"
                >
                    <Eye class="h-4 w-4" />
                </Button>
                <Button
                    size="icon-sm"
                    variant="outline"
                    :title="t('delete')"
                    :aria-label="t('delete')"
                    class="border-red-500/50 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                    @click="emit('delete', log)"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </InfiniteScroll>
</template>
