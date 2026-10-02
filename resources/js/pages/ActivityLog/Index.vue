<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import ActivityLogFilters from '@/components/activity-log/ActivityLogFilters.vue';
import ActivityLogTable, { type ActivityLogRow } from '@/components/activity-log/ActivityLogTable.vue';
import ActivityLogViewModal from '@/components/activity-log/ActivityLogViewModal.vue';
import ExportButton from '@/components/Shared/ExportButton.vue';
import ViewToggle from '@/components/Shared/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';
import Default from '@/layouts/default.vue';
import type { FilterValue, Paginated, SelectOption } from '@/types';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { view } = useViewMode('activity_logs');

withDefaults(
    defineProps<{
        logs: Paginated<ActivityLogRow>;
        filters: Record<string, FilterValue>;
        actions: string[];
        subjectTypes: SelectOption[];
        causers: SelectOption[];
        hasExport?: boolean;
    }>(),
    { hasExport: false },
);

const isViewModalOpen = ref(false);
const selectedLog = ref<ActivityLogRow | null>(null);

const openViewModal = (log: ActivityLogRow) => {
    selectedLog.value = log;
    isViewModalOpen.value = true;
};
</script>

<template>
    <Head :title="t('activity_logs')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 text-start md:py-20">
            <!-- Filters Component -->
            <ActivityLogFilters :filters="filters" :actions="actions" :subject-types="subjectTypes" :causers="causers" />

            <div class="flex w-full flex-col items-stretch justify-between gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
                <ViewToggle v-model="view" />
                <ExportButton route-name="activity_logs.export" :filters="filters" :show="hasExport" />
            </div>

            <!-- Table / Grid Component -->
            <ActivityLogTable :logs="logs" :view="view" @view="openViewModal" />
        </div>
    </div>

    <!-- Read-only: an audit trail nobody can edit. View is the only action. -->
    <ActivityLogViewModal :is-open="isViewModalOpen" :log="selectedLog" @close="isViewModalOpen = false" />
</template>
