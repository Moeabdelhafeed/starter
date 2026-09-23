<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import ViewToggle from '@/components/Shared/ViewToggle.vue';
import TranslationEditModal from '@/components/translation/TranslationEditModal.vue';
import TranslationFilters from '@/components/translation/TranslationFilters.vue';
import TranslationTable from '@/components/translation/TranslationTable.vue';
import Button from '@/components/ui/button/Button.vue';
import { useViewMode } from '@/composables/useViewMode';
import Default from '@/layouts/default.vue';
import type { Language, Paginated, TranslationFilterState, TranslationRow } from '@/types';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        translations: Paginated<TranslationRow>;
        filters: TranslationFilterState;
        languages?: Language[];
        groups?: string[];
        subGroups?: string[];
    }>(),
    { languages: () => [], groups: () => [], subGroups: () => [] },
);

const { view } = useViewMode('translations');

const defaultLang = props.languages?.find((l) => l.is_default);
const activeLocale = ref<string>(String(route().params.locale ?? defaultLang?.code ?? 'en'));
const isEditModalOpen = ref(false);
const selectedTranslation = ref<TranslationRow | null>(null);

const changeLocale = (newLocale: string) => {
    activeLocale.value = newLocale;
    router.get(
        route('translations'),
        { locale: newLocale, search: props.filters.search, group: props.filters.group, sub_group: props.filters.sub_group },
        {
            preserveState: true,
            preserveScroll: true,
            reset: ['translations', 'success', 'error', 'filters'],
        },
    );
};

const openEditModal = (translation: TranslationRow) => {
    selectedTranslation.value = translation;
    isEditModalOpen.value = true;
};
</script>

<template>
    <Head :title="t('translations')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 text-start md:py-20">
            <!-- Filters Component -->
            <TranslationFilters :filters="filters" :active-locale="activeLocale" :groups="groups" :sub-groups="subGroups" />

            <!-- Locale Switcher + View Toggle -->
            <div class="flex flex-col gap-4 rounded-3xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-max gap-2 rounded-xl bg-muted p-2">
                    <Button
                        v-for="lang in languages"
                        :key="lang.code"
                        :aria-pressed="activeLocale === lang.code"
                        :class="
                            activeLocale === lang.code ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'
                        "
                        @click="changeLocale(lang.code)"
                    >
                        {{ lang.native_name }}
                    </Button>
                </div>

                <ViewToggle v-model="view" />
            </div>

            <!-- Table Component -->
            <TranslationTable :translations="translations" :active-locale="activeLocale" :view="view" @edit="openEditModal" />
        </div>
    </div>

    <!-- Modals -->
    <TranslationEditModal :is-open="isEditModalOpen" :translation="selectedTranslation" :languages="languages" @close="isEditModalOpen = false" />
</template>
