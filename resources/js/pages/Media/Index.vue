<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import MediaEditModal from '@/components/media/MediaEditModal.vue';
import MediaFilters from '@/components/media/MediaFilters.vue';
import MediaRemoveModal from '@/components/media/MediaRemoveModal.vue';
import MediaTable from '@/components/media/MediaTable.vue';
import MediaViewModal from '@/components/media/MediaViewModal.vue';
import ViewToggle from '@/components/Shared/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';
import Default from '@/layouts/default.vue';
import type { MediaFilterState, MediaRow, Paginated } from '@/types';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { view } = useViewMode('media');

withDefaults(
    defineProps<{
        items: Paginated<MediaRow>;
        filters: MediaFilterState;
        groups?: string[];
        subGroups?: string[];
    }>(),
    { groups: () => [], subGroups: () => [] },
);

const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isRemoveModalOpen = ref(false);
const selectedItem = ref<MediaRow | null>(null);

const openViewModal = (item: MediaRow) => {
    selectedItem.value = item;
    isViewModalOpen.value = true;
};

const openReplaceModal = (item: MediaRow) => {
    selectedItem.value = item;
    isEditModalOpen.value = true;
};

const openRemoveModal = (item: MediaRow) => {
    selectedItem.value = item;
    isRemoveModalOpen.value = true;
};

const confirmRemove = (done: () => void) => {
    if (!selectedItem.value) return done();

    router.post(
        route('media.remove', selectedItem.value.id),
        { _method: 'PUT' },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['items', 'success', 'error', 'filters'],
            onSuccess: () => {
                isRemoveModalOpen.value = false;
                done();
            },
            onError: () => done(),
        },
    );
};
</script>

<template>
    <Head :title="t('dynamic_storage')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 text-start md:py-20">
            <MediaFilters :filters="filters" :groups="groups" :sub-groups="subGroups" />

            <div class="flex w-full flex-col items-stretch justify-end gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
                <ViewToggle v-model="view" />
            </div>

            <MediaTable :items="items" :view="view" @view="openViewModal" @replace="openReplaceModal" @remove="openRemoveModal" />
        </div>
    </div>

    <MediaViewModal :is-open="isViewModalOpen" :item="selectedItem" @close="isViewModalOpen = false" />
    <MediaEditModal :is-open="isEditModalOpen" :item="selectedItem" @close="isEditModalOpen = false" />
    <MediaRemoveModal :is-open="isRemoveModalOpen" :item="selectedItem" @close="isRemoveModalOpen = false" @confirm="confirmRemove" />
</template>
