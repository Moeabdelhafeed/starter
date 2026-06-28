<script setup>
import { InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CheckCircle, XCircle } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

const { t } = useI18n();
const { formatDate } = useDateFormat();

const props = defineProps({
    templates: Object,
    selectedIds: { type: Array, default: () => [] },
    view: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['edit', 'delete', 'send', 'update:selectedIds']);

const isAllSelected = computed({
    get: () => props.templates.data.length > 0 && props.selectedIds.length === props.templates.data.length,
    set: (v) => emit('update:selectedIds', v ? props.templates.data.map((r) => r.id) : []),
});

const triggerSummary = (row) => {
    if (!row.trigger_model || !row.trigger_event) return t('no_trigger');
    const label = row.trigger_model.split('\\').pop();
    return `${label} · ${t(row.trigger_event)}`;
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
                            <Checkbox v-model="isAllSelected" />
                        </TableHead>
                        <TableHead class="py-4 font-bold">{{ t('slug') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('title') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('topic') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('trigger_model') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('last_sent_at') }}</TableHead>
                        <TableHead class="py-4 font-bold sticky-actions">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="templates">
                        <TableRow v-for="row in templates.data" :key="row.id">
                            <TableCell class="py-4">
                                <Checkbox
                                    :modelValue="selectedIds"
                                    :value="row.id"
                                    @update:modelValue="emit('update:selectedIds', $event)"
                                />
                            </TableCell>
                            <TableCell class="py-4 font-mono text-xs text-muted-foreground">{{ row.slug }}</TableCell>
                            <TableCell class="py-4 font-medium">{{ row.title_api }}</TableCell>
                            <TableCell>
                                <span class="inline-flex items-center rounded-full bg-cyan-500/10 px-2.5 py-0.5 text-xs font-medium text-cyan-600">
                                    {{ row.topic }}
                                </span>
                            </TableCell>
                            <TableCell class="text-xs">{{ triggerSummary(row) }}</TableCell>
                            <TableCell>
                                <span
                                    v-if="row.is_active"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600"
                                >
                                    <CheckCircle class="size-3" />
                                    {{ t('active') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-0.5 text-xs font-medium text-red-600"
                                >
                                    <XCircle class="size-3" />
                                    {{ t('inactive') }}
                                </span>
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                <span v-if="row.last_sent_at">{{ formatDate(row.last_sent_at) }}</span>
                                <span v-else>—</span>
                            </TableCell>
                            <TableCell class="sticky-actions">
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        class="border-emerald-500 text-emerald-500 shadow-none! hover:bg-emerald-500 hover:text-white"
                                        :disabled="!row.is_active"
                                        @click="emit('send', row)"
                                    >
                                        {{ t('send_now') }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                        @click="emit('edit', row)"
                                    >
                                        {{ t('edit') }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        @click="emit('delete', row)"
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
        data="templates"
    >
        <div
            v-for="row in templates.data"
            :key="row.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: checkbox + topic badge -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox
                    :modelValue="selectedIds"
                    :value="row.id"
                    @update:modelValue="emit('update:selectedIds', $event)"
                />
                <span class="inline-flex items-center rounded-full bg-cyan-500/10 px-2.5 py-0.5 text-xs font-medium text-cyan-600">
                    {{ row.topic }}
                </span>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate font-bold text-foreground">{{ row.title_api }}</h3>
                <p class="truncate font-mono text-xs text-muted-foreground">{{ row.slug }}</p>
            </div>

            <!-- Meta -->
            <div class="flex flex-col gap-2 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground">{{ t('trigger_model') }}:</span>
                    <span class="text-foreground">{{ triggerSummary(row) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground">{{ t('last_sent_at') }}:</span>
                    <span v-if="row.last_sent_at" class="text-foreground">{{ formatDate(row.last_sent_at) }}</span>
                    <span v-else class="text-foreground">—</span>
                </div>
            </div>

            <!-- Status + actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <span
                    v-if="row.is_active"
                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600"
                >
                    <CheckCircle class="size-3" />
                    {{ t('active') }}
                </span>
                <span
                    v-else
                    class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-0.5 text-xs font-medium text-red-600"
                >
                    <XCircle class="size-3" />
                    {{ t('inactive') }}
                </span>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        class="border-emerald-500 text-emerald-500 shadow-none! hover:bg-emerald-500 hover:text-white"
                        :disabled="!row.is_active"
                        @click="emit('send', row)"
                    >
                        {{ t('send_now') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                        @click="emit('edit', row)"
                    >
                        {{ t('edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                        @click="emit('delete', row)"
                    >
                        {{ t('delete') }}
                    </Button>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
