<script setup>
import Default from '@/layouts/default.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import ActivityLogFilters from '@/components/activity-log/ActivityLogFilters.vue';
import ActivityLogTable from '@/components/activity-log/ActivityLogTable.vue';
import ActivityLogViewModal from '@/components/activity-log/ActivityLogViewModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import BulkDeleteModal from '@/components/Shared/BulkDeleteModal.vue';
import ExportButton from '@/components/Shared/ExportButton.vue';
import ViewToggle from '@/components/Shared/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';
import { router } from '@inertiajs/vue3';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { view } = useViewMode('activity_logs');

const props = defineProps({
    logs: Object,
    filters: Object,
    actions: Array,
    subjectTypes: Array,
    causers: Array,
    hasExport: {
        type: Boolean,
        default: false,
    },
});

const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedLog = ref(null);
const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);

const openViewModal = (log) => {
    selectedLog.value = log;
    isViewModalOpen.value = true;
};

const openDeleteModal = (log) => {
    selectedLog.value = log;
    isDeleteModalOpen.value = true;
};

const handleBulkDelete = () => {
    isBulkDeleteModalOpen.value = true;
};

const confirmBulkDelete = (done) => {
    router.post(route('activity_logs.bulk-destroy'), { ids: selectedIds.value, _method: 'DELETE' }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['logs', 'success', 'error', 'filters'],
        onSuccess: () => {
            selectedIds.value = [];
            isBulkDeleteModalOpen.value = false;
            done();
        },
        onError: () => done(),
    });
};
</script>

<template>
    <Head :title="t('activity_logs')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 md:py-20 text-start">
            <!-- Filters Component -->
            <ActivityLogFilters :filters="filters" :actions="actions" :subject-types="subjectTypes" :causers="causers" />

            <!-- Bulk Actions -->
            <BulkActions
                :selected-count="selectedIds.length"
                :actions="{ delete: true, statusOn: false, statusOff: false }"
                @delete="handleBulkDelete"
                @clear="selectedIds = []"
            />

            <div class="flex w-full flex-col items-stretch justify-between gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
                <ViewToggle v-model="view" />
                <ExportButton route-name="activity_logs.export" :filters="filters" :show="hasExport" />
            </div>

            <!-- Table / Grid Component -->
            <ActivityLogTable
                v-model:selected-ids="selectedIds"
                :logs="logs"
                :view="view"
                @view="openViewModal"
                @delete="openDeleteModal"
            />
        </div>
    </div>

    <!-- Modals -->
    <ActivityLogViewModal 
        :is-open="isViewModalOpen" 
        :log="selectedLog" 
        @close="isViewModalOpen = false" 
    />
    <DeleteModal
        :is-open="isDeleteModalOpen"
        :item-id="selectedLog?.id"
        :title="t('delete') + ' ' + t('activity_logs')"
        :message="t('confirm_delete_log')"
        route-name="activity_logs.destroy"
        :reset-keys="['logs', 'success', 'error', 'filters']"
        @close="isDeleteModalOpen = false"
    />
    <BulkDeleteModal
        :is-open="isBulkDeleteModalOpen"
        :count="selectedIds.length"
        @close="isBulkDeleteModalOpen = false"
        @confirm="confirmBulkDelete"
    />
</template>

<style scoped></style>
