<script setup>
import Default from '@/layouts/default.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import MediaFilters from '@/components/media/MediaFilters.vue';
import MediaTable from '@/components/media/MediaTable.vue';
import MediaEditModal from '@/components/media/MediaEditModal.vue';
import MediaRemoveModal from '@/components/media/MediaRemoveModal.vue';
import ViewToggle from '@/components/Shared/ViewToggle.vue';
import { useViewMode } from '@/composables/useViewMode';

defineOptions({
    layout: Default,
});

const { t } = useI18n();
const { view } = useViewMode('media');

defineProps({
    items: Object,
    groups: Array,
    subGroups: Array,
    filters: Object,
});

const isEditModalOpen = ref(false);
const isRemoveModalOpen = ref(false);
const selectedItem = ref(null);

const openReplaceModal = (item) => {
    selectedItem.value = item;
    isEditModalOpen.value = true;
};

const openRemoveModal = (item) => {
    selectedItem.value = item;
    isRemoveModalOpen.value = true;
};

const confirmRemove = (done) => {
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
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 md:py-20 text-start">
            <MediaFilters :filters="filters" :groups="groups" :sub-groups="subGroups" />

            <div class="flex w-full flex-col items-stretch justify-end gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
                <ViewToggle v-model="view" />
            </div>

            <MediaTable :items="items" :view="view" @replace="openReplaceModal" @remove="openRemoveModal" />
        </div>
    </div>

    <MediaEditModal
        :is-open="isEditModalOpen"
        :item="selectedItem"
        @close="isEditModalOpen = false"
    />
    <MediaRemoveModal
        :is-open="isRemoveModalOpen"
        :item="selectedItem"
        @close="isRemoveModalOpen = false"
        @confirm="confirmRemove"
    />
</template>
