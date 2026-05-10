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
import { router } from '@inertiajs/vue3';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

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
    router.delete(route('activity_logs.bulk-destroy'), {
        data: { ids: selectedIds.value },
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
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-20 text-start">
            <!-- Filters Component -->
            <ActivityLogFilters :filters="filters" :actions="actions" :subject-types="subjectTypes" :causers="causers" />

            <!-- Bulk Actions -->
            <BulkActions
                :selected-count="selectedIds.length"
                :actions="{ delete: true, statusOn: false, statusOff: false }"
                @delete="handleBulkDelete"
                @clear="selectedIds = []"
            />

            <div v-if="hasExport" class="flex w-full items-center justify-end rounded-xl border bg-card p-4">
                <ExportButton route-name="activity_logs.export" :filters="filters" :show="hasExport" />
            </div>

            <!-- Table Component -->
            <ActivityLogTable
                v-model:selected-ids="selectedIds" 
                :logs="logs" 
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
