<script setup>
import Default from '@/layouts/default.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import { Plus } from 'lucide-vue-next';
import NotificationTemplateFilters from '@/components/notification-template/NotificationTemplateFilters.vue';
import NotificationTemplateTable from '@/components/notification-template/NotificationTemplateTable.vue';
import NotificationTemplateCreateModal from '@/components/notification-template/NotificationTemplateCreateModal.vue';
import NotificationTemplateEditModal from '@/components/notification-template/NotificationTemplateEditModal.vue';
import DeleteModal from '@/components/Shared/DeleteModal.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import BulkDeleteModal from '@/components/Shared/BulkDeleteModal.vue';

defineOptions({ layout: Default });

const { t } = useI18n();

defineProps({
    templates: Object,
    filters: Object,
    topics: Array,
    models: Array,
    events: Array,
    languages: Array,
});

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedTemplate = ref(null);
const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);

const openEditModal = (template) => {
    selectedTemplate.value = template;
    isEditModalOpen.value = true;
};

const openDeleteModal = (template) => {
    selectedTemplate.value = template;
    isDeleteModalOpen.value = true;
};

const sendNow = (template) => {
    router.post(
        route('notification_templates.send', template.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['templates', 'success', 'error', 'filters'],
        },
    );
};

const handleBulkDelete = () => {
    isBulkDeleteModalOpen.value = true;
};

const confirmBulkDelete = (done) => {
    router.delete(route('notification_templates.bulk-destroy'), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        preserveState: true,
        reset: ['templates', 'success', 'error', 'filters'],
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
    <Head :title="t('notification_templates')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-20 text-start">
            <NotificationTemplateFilters :filters="filters" />

            <BulkActions
                :selected-count="selectedIds.length"
                :actions="{ delete: true }"
                @delete="handleBulkDelete"
                @clear="selectedIds = []"
            />

            <div class="flex w-full items-center justify-start rounded-xl border bg-card p-4">
                <Button @click="isCreateModalOpen = true">
                    <Plus class="me-2 size-4" />
                    {{ t('create_notification_template') }}
                </Button>
            </div>

            <NotificationTemplateTable
                v-model:selected-ids="selectedIds"
                :templates="templates"
                @edit="openEditModal"
                @delete="openDeleteModal"
                @send="sendNow"
            />
        </div>
    </div>

    <NotificationTemplateCreateModal
        :is-open="isCreateModalOpen"
        :topics="topics"
        :models="models"
        :events="events"
        :languages="languages"
        @close="isCreateModalOpen = false"
    />

    <NotificationTemplateEditModal
        :is-open="isEditModalOpen"
        :template="selectedTemplate"
        :topics="topics"
        :models="models"
        :events="events"
        :languages="languages"
        @close="isEditModalOpen = false"
    />

    <DeleteModal
        :is-open="isDeleteModalOpen"
        :item-id="selectedTemplate?.id"
        :title="t('delete')"
        :message="t('confirm_delete_notification_template')"
        route-name="notification_templates.destroy"
        :reset-keys="['templates', 'success', 'error', 'filters']"
        @close="isDeleteModalOpen = false"
    />

    <BulkDeleteModal
        :is-open="isBulkDeleteModalOpen"
        :count="selectedIds.length"
        @close="isBulkDeleteModalOpen = false"
        @confirm="confirmBulkDelete"
    />
</template>
