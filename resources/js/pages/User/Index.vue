<script setup>
import Default from '@/layouts/default.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

import Button from '@/components/ui/button/Button.vue';
import UserFilters from '@/components/user/UserFilters.vue';
import UserTable from '@/components/user/UserTable.vue';
import UserCreateModal from '@/components/user/UserCreateModal.vue';
import UserEditModal from '@/components/user/UserEditModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import BulkDeleteModal from '@/components/Shared/BulkDeleteModal.vue';
import RestoreModal from '@/components/Shared/RestoreModal.vue';
import ForceDeleteModal from '@/components/Shared/ForceDeleteModal.vue';
import BulkRestoreModal from '@/components/Shared/BulkRestoreModal.vue';
import BulkForceDeleteModal from '@/components/Shared/BulkForceDeleteModal.vue';
import ExportButton from '@/components/Shared/ExportButton.vue';
import ViewToggle from '@/components/Shared/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { view } = useViewMode('users');

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
    hasSoftDeletes: {
        type: Boolean,
        default: false,
    },
    hasExport: {
        type: Boolean,
        default: false,
    },
});

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const isRestoreModalOpen = ref(false);
const isForceDeleteModalOpen = ref(false);
const selectedUser = ref(null);
const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);
const isBulkRestoreModalOpen = ref(false);
const isBulkForceDeleteModalOpen = ref(false);

// Check if we're viewing trashed items
const isViewingTrashed = computed(() => props.filters?.trashed === 'only');

// Dynamic bulk actions based on trashed filter
const bulkActions = computed(() => ({
    delete: !isViewingTrashed.value,
    statusOn: !isViewingTrashed.value,
    statusOff: !isViewingTrashed.value,
    restore: props.hasSoftDeletes && isViewingTrashed.value,
    forceDelete: props.hasSoftDeletes && isViewingTrashed.value,
}));

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openEditModal = (user) => {
    selectedUser.value = user;
    isEditModalOpen.value = true;
};

const openDeleteModal = (user) => {
    selectedUser.value = user;
    isDeleteModalOpen.value = true;
};

const handleBulkDelete = () => {
    isBulkDeleteModalOpen.value = true;
};

const confirmBulkDelete = (done) => {
    router.delete(route('users.bulk-destroy'), {
        data: { ids: selectedIds.value },
        onSuccess: () => {
            selectedIds.value = [];
            isBulkDeleteModalOpen.value = false;
            done();
        },
        onError: () => done(),
    });
};

const handleBulkTurnOn = () => {
    router.put(route('users.bulk-update'), {
        ids: selectedIds.value,
        is_active: true,
    }, {
        onSuccess: () => (selectedIds.value = []),
    });
};

const handleBulkTurnOff = () => {
    router.put(route('users.bulk-update'), {
        ids: selectedIds.value,
        is_active: false,
    }, {
        onSuccess: () => (selectedIds.value = []),
    });
};

// Restore actions
const openRestoreModal = (user) => {
    selectedUser.value = user;
    isRestoreModalOpen.value = true;
};

const openForceDeleteModal = (user) => {
    selectedUser.value = user;
    isForceDeleteModalOpen.value = true;
};

const handleBulkRestore = () => {
    isBulkRestoreModalOpen.value = true;
};

const confirmBulkRestore = (done) => {
    router.post(route('users.bulk-restore'), {
        ids: selectedIds.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error', 'filters'],
        onSuccess: () => {
            selectedIds.value = [];
            isBulkRestoreModalOpen.value = false;
            done();
        },
        onError: () => done(),
    });
};

const handleBulkForceDelete = () => {
    isBulkForceDeleteModalOpen.value = true;
};

const confirmBulkForceDelete = (done) => {
    router.post(route('users.bulk-force-delete'), {
        ids: selectedIds.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error', 'filters'],
        onSuccess: () => {
            selectedIds.value = [];
            isBulkForceDeleteModalOpen.value = false;
            done();
        },
        onError: () => done(),
    });
};

</script>

<template>
    <Head :title="t('users')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 md:py-20 text-start">
            <!-- Filters Component -->
            <UserFilters :filters="filters" :roles="roles" :has-soft-deletes="hasSoftDeletes" />

            <!-- Bulk Actions -->
            <BulkActions
                :selected-count="selectedIds.length"
                :actions="bulkActions"
                @delete="handleBulkDelete"
                @turn-on="handleBulkTurnOn"
                @turn-off="handleBulkTurnOff"
                @restore="handleBulkRestore"
                @force-delete="handleBulkForceDelete"
                @clear="selectedIds = []"
            />

            <!-- Create User Button -->
            <div class="flex w-full flex-col items-stretch justify-between gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
                <Button @click="openCreateModal">
                    <Plus class="me-2" />
                    {{ t('create_user') }}
                </Button>
                <div class="flex items-center gap-3">
                    <ViewToggle v-model="view" />
                    <ExportButton route-name="users.export" :filters="filters" :show="hasExport" />
                </div>
            </div>

            <!-- Table / Grid Component -->
            <UserTable
                v-model:selected-ids="selectedIds"
                :users="users"
                :view="view"
                :has-soft-deletes="hasSoftDeletes"
                @edit="openEditModal"
                @delete="openDeleteModal"
                @restore="openRestoreModal"
                @force-delete="openForceDeleteModal"
            />
        </div>
    </div>

    <!-- Modals -->
    <UserCreateModal :is-open="isCreateModalOpen" :roles="roles" @close="isCreateModalOpen = false" />
    <UserEditModal :is-open="isEditModalOpen" :user="selectedUser" :roles="roles" @close="isEditModalOpen = false" />
    <DeleteModal
        :is-open="isDeleteModalOpen"
        :item-id="selectedUser?.id"
        :title="t('delete') + ' ' + t('users')"
        :message="t('confirm_delete')"
        route-name="users.destroy"
        :reset-keys="['users', 'success', 'error', 'filters']"
        @close="isDeleteModalOpen = false"
    />
    <BulkDeleteModal
        :is-open="isBulkDeleteModalOpen"
        :count="selectedIds.length"
        @close="isBulkDeleteModalOpen = false"
        @confirm="confirmBulkDelete"
    />
    <RestoreModal
        :is-open="isRestoreModalOpen"
        :item-id="selectedUser?.id"
        :title="t('restore') + ' ' + t('users')"
        :message="t('restore_confirmation')"
        route-name="users.restore"
        :reset-keys="['users', 'success', 'error', 'filters']"
        @close="isRestoreModalOpen = false"
    />
    <ForceDeleteModal
        :is-open="isForceDeleteModalOpen"
        :item-id="selectedUser?.id"
        :title="t('force_delete') + ' ' + t('users')"
        :message="t('force_delete_confirmation')"
        route-name="users.force-delete"
        :reset-keys="['users', 'success', 'error', 'filters']"
        @close="isForceDeleteModalOpen = false"
    />
    <BulkRestoreModal
        :is-open="isBulkRestoreModalOpen"
        :count="selectedIds.length"
        @close="isBulkRestoreModalOpen = false"
        @confirm="confirmBulkRestore"
    />
    <BulkForceDeleteModal
        :is-open="isBulkForceDeleteModalOpen"
        :count="selectedIds.length"
        @close="isBulkForceDeleteModalOpen = false"
        @confirm="confirmBulkForceDelete"
    />
</template>

<style scoped></style>
