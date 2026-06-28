<script setup>
import { InfiniteScroll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Eye, Trash2, User, Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import { useHighlight } from '@/composables/useHighlight';
import { useDateFormat } from '@/composables/useDateFormat';

const { isHighlighted } = useHighlight();
const { formatDate } = useDateFormat();

const { t } = useI18n();

const props = defineProps({
    logs: Object,
    selectedIds: {
        type: Array,
        default: () => [],
    },
    view: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['view', 'delete', 'update:selectedIds']);

const isAllSelected = computed({
    get: () => props.logs.data.length > 0 && props.selectedIds.length === props.logs.data.length,
    set: (value) => {
        if (value) {
            emit('update:selectedIds', props.logs.data.map((l) => l.id));
        } else {
            emit('update:selectedIds', []);
        }
    },
});


const getActionColor = (action) => {
    switch (action) {
        case 'created': return 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20 dark:bg-emerald-400/15 dark:text-emerald-300 dark:ring-emerald-400/30';
        case 'updated': return 'bg-amber-500/10 text-amber-600 ring-amber-500/20 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/30';
        case 'deleted': return 'bg-rose-500/10 text-rose-600 ring-rose-500/20 dark:bg-rose-400/15 dark:text-rose-300 dark:ring-rose-400/30';
        default: return 'bg-blue-500/10 text-blue-600 ring-blue-500/20 dark:bg-blue-400/15 dark:text-blue-300 dark:ring-blue-400/30';
    }
};


const getModelName = (subjectType) => {
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
                    <TableHead class="py-4 w-10">
                        <Checkbox v-model="isAllSelected" />
                    </TableHead>
                    <TableHead class="py-4 font-bold">{{ t('user') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('action') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('target') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('date') }}</TableHead>
                    <TableHead class="py-4 font-bold text-end sticky-actions">{{ t('actions') }}</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <InfiniteScroll class="contents" preserve-url data="logs">
                    <TableRow v-for="log in logs.data" :key="log.id"
                        :class="['group', isHighlighted(log.id) ? 'animate-pulse ring-2 ring-primary/70 bg-primary/10' : '']">
                        <TableCell class="py-4">
                            <Checkbox :modelValue="selectedIds" @update:modelValue="emit('update:selectedIds', $event)" :value="log.id" />
                        </TableCell>
                        <TableCell class="py-4 text-start!">
                            <div class="flex items-center gap-3">
                               
                                <div>
                                    <p class="font-bold text-foreground leading-none mb-1">{{ log.causer_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ log.causer_email }}</p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', getActionColor(log.action)]">
                                {{ t(log.action) }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-primary/20 px-2 py-1 text-[10px] font-bold uppercase text-primary border border-primary/40 italic-none">
                                    {{ getModelName(log.subject_type) }}
                                </span>
                                <span class="text-muted-foreground text-xs italic-none">ID: {{ log.subject_id }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            <div class="flex items-center gap-2 text-xs italic-none">
                                <Clock class="size-3.5" />
                                {{ formatDate(log.created_at) }}
                            </div>
                        </TableCell>
                        <TableCell class="text-end sticky-actions">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    class="border-primary/50 text-primary shadow-none! hover:bg-primary hover:text-white"
                                    @click="emit('view', log)"
                                >
                                    {{ t('view_details') }}
                                </Button>
                                <Button
                                    variant="outline"
                                    class="border-red-500/50 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                    @click="emit('delete', log)"
                                >
                                    {{ t('delete') }}
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
    <InfiniteScroll
        v-else
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        preserve-url
        data="logs"
    >
        <div
            v-for="log in logs.data"
            :key="log.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
            :class="isHighlighted(log.id) ? 'animate-pulse ring-2 ring-primary/70 bg-primary/10' : ''"
        >
            <!-- Top: checkbox + action badge -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox
                    :modelValue="selectedIds"
                    @update:modelValue="emit('update:selectedIds', $event)"
                    :value="log.id"
                />
                <span :class="['inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', getActionColor(log.action)]">
                    {{ t(log.action) }}
                </span>
            </div>

            <!-- Causer -->
            <div class="flex items-center gap-2">
                <User class="size-4 shrink-0 text-muted-foreground" />
                <div class="min-w-0">
                    <p class="truncate font-bold text-foreground leading-none mb-1">{{ log.causer_name }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ log.causer_email }}</p>
                </div>
            </div>

            <!-- Target -->
            <div class="flex flex-col gap-1">
                <span class="text-xs font-medium text-muted-foreground">{{ t('target') }}</span>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-primary/20 px-2 py-1 text-[10px] font-bold uppercase text-primary border border-primary/40">
                        {{ getModelName(log.subject_type) }}
                    </span>
                    <span class="text-muted-foreground text-xs">ID: {{ log.subject_id }}</span>
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
                    variant="outline"
                    class="border-primary/50 text-primary shadow-none! hover:bg-primary hover:text-white"
                    @click="emit('view', log)"
                >
                    {{ t('view_details') }}
                </Button>
                <Button
                    variant="outline"
                    class="border-red-500/50 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                    @click="emit('delete', log)"
                >
                    {{ t('delete') }}
                </Button>
            </div>
        </div>
    </InfiniteScroll>
</template>
