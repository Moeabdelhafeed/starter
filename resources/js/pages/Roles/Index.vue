<script setup>
import Default from '@/layouts/default.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from 'lucide-vue-next';

import Button from '@/components/ui/button/Button.vue';
import RoleFilters from '@/components/role/RoleFilters.vue';
import RoleTable from '@/components/role/RoleTable.vue';
import RoleCreateModal from '@/components/role/RoleCreateModal.vue';
import RoleEditModal from '@/components/role/RoleEditModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import BulkDeleteModal from '@/components/Shared/BulkDeleteModal.vue';
import { router } from '@inertiajs/vue3';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

const props = defineProps({
    roles: Object,
    filters: Object,
    permissions: Array,
    currentUserRoles: Array,
});

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedRole = ref(null);
const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openEditModal = (role) => {
    selectedRole.value = role;
    isEditModalOpen.value = true;
};

const openDeleteModal = (role) => {
    selectedRole.value = role;
    isDeleteModalOpen.value = true;
};

const handleBulkDelete = () => {
    isBulkDeleteModalOpen.value = true;
};

const confirmBulkDelete = (done) => {
    router.delete(route('roles.bulk-destroy'), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        preserveState: true,
        reset: ['roles', 'success', 'error', 'filters'],
        onSuccess: () => {
            selectedIds.value = [];
            isBulkDeleteModalOpen.value = false;
            done();
        },
        onError: () => done(),
    });
};

const handleBulkTurnOn = () => {
    router.put(route('roles.bulk-update'), {
        ids: selectedIds.value,
        is_active: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['roles', 'success', 'error', 'filters'],
        onSuccess: () => (selectedIds.value = []),
    });
};

const handleBulkTurnOff = () => {
    router.put(route('roles.bulk-update'), {
        ids: selectedIds.value,
        is_active: false,
    }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['roles', 'success', 'error', 'filters'],
        onSuccess: () => (selectedIds.value = []),
    });
};

</script>

<template>
    <Head :title="t('roles')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-20 text-start">

            <!-- Filters Component -->
            <RoleFilters :filters="filters" />

            <!-- Bulk Actions -->
            <BulkActions
                :selected-count="selectedIds.length"
                @delete="handleBulkDelete"
                @turn-on="handleBulkTurnOn"
                @turn-off="handleBulkTurnOff"
                @clear="selectedIds = []"
            />

            <!-- Create Role Button -->
            <div class="flex w-full items-center justify-between rounded-xl border bg-card p-4">
                <Button @click="openCreateModal" class="gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('create_role') }}
                </Button>
            </div>

            <!-- Table Component -->
            <RoleTable v-model:selected-ids="selectedIds" :roles="roles" @edit="openEditModal" @delete="openDeleteModal" />
        </div>
    </div>

    <!-- Modals -->
    <RoleCreateModal :is-open="isCreateModalOpen" :permissions="permissions" @close="isCreateModalOpen = false" />
    <RoleEditModal
        :is-open="isEditModalOpen"
        :role="selectedRole"
        :permissions="permissions"
        @close="isEditModalOpen = false"
    />
    <DeleteModal
        :is-open="isDeleteModalOpen"
        :item-id="selectedRole?.id"
        :title="t('delete') + ' ' + t('role')"
        :message="t('confirm_delete_role')"
        route-name="roles.destroy"
        :reset-keys="['roles', 'success', 'error', 'filters']"
        @close="isDeleteModalOpen = false"
    >
        <div v-if="selectedRole" class="mt-2 font-semibold text-foreground">
            {{ selectedRole.name }}
        </div>
    </DeleteModal>
    <BulkDeleteModal
        :is-open="isBulkDeleteModalOpen"
        :count="selectedIds.length"
        :message="t('confirm_bulk_delete_role')"
        @close="isBulkDeleteModalOpen = false"
        @confirm="confirmBulkDelete"
    />
</template>

<style scoped></style>
