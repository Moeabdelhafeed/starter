<script setup>
import Default from '@/layouts/default.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import AppSettingBlock from '@/components/app-setting/AppSettingBlock.vue';
import AppSettingModal from '@/components/app-setting/AppSettingModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

const props = defineProps({
    blocks: {
        type: Object,
        default: () => ({}),
    },
    types: {
        type: Array,
        default: () => [],
    },
    languages: {
        type: Array,
        default: () => [],
    },
});

const isModalOpen = ref(false);
const modalType = ref('');
const selectedItem = ref(null);

const isDeleteModalOpen = ref(false);
const itemToDelete = ref(null);

const openCreateModal = (type) => {
    selectedItem.value = null;
    modalType.value = type;
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    selectedItem.value = item;
    modalType.value = item.type;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    setTimeout(() => {
        selectedItem.value = null;
    }, 200);
};

const openDeleteModal = (item) => {
    itemToDelete.value = item;
    isDeleteModalOpen.value = true;
};
</script>

<template>
    <Head :title="t('app_settings')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 md:py-20 text-start">
            <!-- Header -->
            <div class="flex flex-col gap-2 rounded-3xl border bg-card p-6">
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ t('app_settings') }}
                </h1>
                <p class="text-sm text-muted-foreground">{{ t('app_settings_desc') }}</p>
            </div>

            <!-- One block per type -->
            <AppSettingBlock
                v-for="type in types"
                :key="type"
                :type="type"
                :items="blocks[type] || []"
                @add="openCreateModal"
                @edit="openEditModal"
                @delete="openDeleteModal"
            />
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <AppSettingModal
        :is-open="isModalOpen"
        :languages="languages"
        :type="modalType"
        :item="selectedItem"
        @close="closeModal"
    />

    <!-- Delete Modal -->
    <DeleteModal
        :is-open="isDeleteModalOpen"
        :item-id="itemToDelete?.id"
        :title="t('delete')"
        :message="t('confirm_delete')"
        route-name="app_settings.destroy"
        :reset-keys="['blocks', 'success', 'error']"
        @close="isDeleteModalOpen = false"
    />
</template>
