<script setup>
import Default from '@/layouts/default.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from 'lucide-vue-next';

import Button from '@/components/ui/button/Button.vue';
import LanguageFilters from '@/components/language/LanguageFilters.vue';
import LanguageTable from '@/components/language/LanguageTable.vue';
import LanguageCreateModal from '@/components/language/LanguageCreateModal.vue';
import LanguageEditModal from '@/components/language/LanguageEditModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

const props = defineProps({
    languages: Object,
    filters: Object,
    availableLocales: Array,
});

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedLanguage = ref(null);

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openEditModal = (language) => {
    selectedLanguage.value = language;
    isEditModalOpen.value = true;
};

const openDeleteModal = (language) => {
    selectedLanguage.value = language;
    isDeleteModalOpen.value = true;
};
</script>

<template>

    <Head :title="t('languages')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-20 text-start">
            <!-- Filters Component -->
            <LanguageFilters :filters="filters" />

            <!-- Create Language Button -->
            <div class="flex w-full items-center justify-between rounded-xl border bg-card p-4">
                <Button @click="openCreateModal">
                    <Plus class="me-2" />
                    {{ t('create_language') }}
                </Button>
            </div>

            <!-- Table Component -->
            <LanguageTable :languages="languages" @edit="openEditModal" @delete="openDeleteModal" />
        </div>
    </div>

    <!-- Modals -->
    <LanguageCreateModal :is-open="isCreateModalOpen" :available-locales="availableLocales" @close="isCreateModalOpen = false" />
    <LanguageEditModal :is-open="isEditModalOpen" :language="selectedLanguage" @close="isEditModalOpen = false" />
    <DeleteModal :is-open="isDeleteModalOpen" :item-id="selectedLanguage?.id"
        :title="t('delete') + ' ' + t('languages')" :message="t('confirm_delete_language')"
        route-name="languages.destroy" :reset-keys="['languages', 'availableLocales', 'success', 'error', 'filters']"
        @close="isDeleteModalOpen = false" />
</template>
