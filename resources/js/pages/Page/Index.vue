<script setup>
import Default from '@/layouts/default.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from 'lucide-vue-next';

import Button from '@/components/ui/button/Button.vue';
import PageFilters from '@/components/page/PageFilters.vue';
import PageTable from '@/components/page/PageTable.vue';
import PageCreateModal from '@/components/page/PageCreateModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import BulkDeleteModal from '@/components/Shared/BulkDeleteModal.vue';
import ViewToggle from '@/components/Shared/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { view } = useViewMode('pages');

const props = defineProps({
    pages: Object,
    languages: Array,
    filters: Object,
});

const isCreateModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedPage = ref(null);
const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openDeleteModal = (page) => {
    selectedPage.value = page;
    isDeleteModalOpen.value = true;
};

const handleBulkDelete = () => {
    isBulkDeleteModalOpen.value = true;
};

const confirmBulkDelete = (done) => {
    router.post(route('pages.bulk-destroy'), { ids: selectedIds.value, _method: 'DELETE' }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['pages', 'success', 'error', 'filters'],
        onSuccess: () => {
            selectedIds.value = [];
            isBulkDeleteModalOpen.value = false;
            done();
        },
        onError: () => done(),
    });
};

const handleBulkTurnOn = () => {
    router.post(route('pages.bulk-update'), {
        ids: selectedIds.value,
        is_active: true,
        _method: 'PUT',
    }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['pages', 'success', 'error', 'filters'],
        onSuccess: () => (selectedIds.value = []),
    });
};

const handleBulkTurnOff = () => {
    router.post(route('pages.bulk-update'), {
        ids: selectedIds.value,
        is_active: false,
        _method: 'PUT',
    }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['pages', 'success', 'error', 'filters'],
        onSuccess: () => (selectedIds.value = []),
    });
};
</script>

<template>
    <Head :title="t('pages')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 md:py-20 text-start">
            <!-- Filters Component -->
            <PageFilters :filters="filters" />

            <!-- Bulk Actions -->
            <BulkActions
                :selected-count="selectedIds.length"
                @delete="handleBulkDelete"
                @turn-on="handleBulkTurnOn"
                @turn-off="handleBulkTurnOff"
                @clear="selectedIds = []"
            />

            <!-- Create Page Button -->
            <div class="flex w-full flex-col items-stretch justify-between gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
                <Button @click="openCreateModal">
                    <Plus class="me-2" />
                    {{ t('create_page') }}
                </Button>
                <ViewToggle v-model="view" />
            </div>

            <!-- Table / Grid Component -->
            <PageTable v-model:selected-ids="selectedIds" :pages="pages" :view="view" @delete="openDeleteModal" />
        </div>
    </div>

    <!-- Modals -->
    <PageCreateModal :is-open="isCreateModalOpen" :languages="languages" @close="isCreateModalOpen = false" />
    <DeleteModal
        :is-open="isDeleteModalOpen"
        :item-id="selectedPage?.id"
        :title="t('delete') + ' ' + t('page')"
        :message="t('confirm_delete_page')"
        route-name="pages.destroy"
        :reset-keys="['pages', 'success', 'error', 'filters']"
        @close="isDeleteModalOpen = false"
    />
    <BulkDeleteModal
        :is-open="isBulkDeleteModalOpen"
        :count="selectedIds.length"
        @close="isBulkDeleteModalOpen = false"
        @confirm="confirmBulkDelete"
    />
</template>
