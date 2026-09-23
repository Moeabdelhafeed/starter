<script setup lang="ts">
import { Activity, Clock, Layers, User } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import { BaseModal } from '@/components/ui/modal';
import { useDateFormat } from '@/composables/useDateFormat';

import type { ActivityLogRow } from './ActivityLogTable.vue';

const { t } = useI18n();
const { formatDate } = useDateFormat();

defineProps<{
    isOpen: boolean;
    log: ActivityLogRow | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const close = () => {
    emit('close');
};

const getActionColor = (action?: string | null): string => {
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
    <BaseModal :open="isOpen" :title="`${t('log_details')} #${log?.id}`" size="lg" @close="close">
        <div class="space-y-6">
            <!-- Info Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-border bg-muted p-4">
                    <div class="mb-3 flex items-center gap-3">
                        <User class="size-4 text-primary" />
                        <span class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">{{ t('causer') }}</span>
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm leading-none font-bold text-foreground">{{ log?.causer_name }}</p>
                        <p class="text-xs leading-none text-muted-foreground">{{ log?.causer_email }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-muted p-4">
                    <div class="mb-3 flex items-center gap-3">
                        <Activity class="size-4 text-primary" />
                        <span class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">{{ t('action_context') }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[9px] font-bold text-muted-foreground uppercase">{{ t('action') }}</p>
                            <span
                                :class="[
                                    'mt-1 inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset',
                                    getActionColor(log?.action),
                                ]"
                            >
                                {{ t(log?.action ?? '') }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-muted-foreground uppercase">{{ t('target_id') }}</p>
                            <p class="mt-1 text-xs font-bold text-foreground">{{ log?.subject_id }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-muted p-4">
                    <div class="mb-3 flex items-center gap-3">
                        <Layers class="size-4 text-primary" />
                        <span class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">{{ t('target') }}</span>
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm leading-none font-bold text-foreground">{{ getModelName(log?.subject_type) }}</p>
                        <p class="text-xs leading-none text-muted-foreground">{{ log?.subject_type }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-muted p-4">
                    <div class="mb-3 flex items-center gap-3">
                        <Clock class="size-4 text-primary" />
                        <span class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">{{ t('date') }}</span>
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm leading-none font-bold text-foreground">{{ formatDate(log?.created_at) }}</p>
                        <p class="text-xs leading-none text-muted-foreground">{{ t('logged_at') }}</p>
                    </div>
                </div>
            </div>

            <!-- Data Diff -->
            <div class="space-y-4">
                <div v-if="log?.old_data && Object.keys(log.old_data).length > 0" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="size-1.5 rounded-full bg-rose-500" aria-hidden="true"></div>
                        <h3 class="text-[10px] font-bold tracking-tight text-foreground uppercase">{{ t('previous_state') }}</h3>
                    </div>
                    <div
                        class="overflow-x-auto rounded-xl border border-rose-100 bg-rose-50/10 p-4 font-mono text-[11px] dark:border-rose-400/20 dark:bg-rose-400/5"
                    >
                        <pre class="text-rose-900 dark:text-rose-200">{{ JSON.stringify(log.old_data, null, 2) }}</pre>
                    </div>
                </div>

                <div v-if="log?.new_data && Object.keys(log.new_data).length > 0" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></div>
                        <h3 class="text-[10px] font-bold tracking-tight text-foreground uppercase">{{ t('new_state') }}</h3>
                    </div>
                    <div
                        class="overflow-x-auto rounded-xl border border-emerald-100 bg-emerald-50/10 p-4 font-mono text-[11px] dark:border-emerald-400/20 dark:bg-emerald-400/5"
                    >
                        <pre class="text-emerald-900 dark:text-emerald-200">{{ JSON.stringify(log.new_data, null, 2) }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button type="button" variant="outline" class="w-full sm:w-auto sm:px-8" @click="close">
                {{ t('close') }}
            </Button>
        </template>
    </BaseModal>
</template>
