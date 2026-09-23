<script setup lang="ts">
import { InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { FileText, Lock } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PageRow, Paginated } from '@/types';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        pages: Paginated<PageRow>;
        selectedIds?: number[];
        view?: 'table' | 'grid';
    }>(),
    { selectedIds: () => [], view: 'table' },
);

const emit = defineEmits<{
    (e: 'delete', page: PageRow): void;
    (e: 'update:selectedIds', ids: number[]): void;
}>();

/** Checkbox emits a boolean when single and an array in multi-select mode; only the array reaches here. */
const setSelected = (value: unknown) => emit('update:selectedIds', (value as number[]) ?? []);

const isAllSelected = computed({
    get: () => props.pages.data.length > 0 && props.selectedIds.length === props.pages.data.length,
    set: (value: boolean) => {
        if (value) {
            emit(
                'update:selectedIds',
                props.pages.data.map((p) => p.id),
            );
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const toggleStatus = (page: PageRow) => {
    // The switch is disabled for a protected page, and the server refuses anyway; this
    // keeps the two in step rather than relying on the attribute alone.
    if (page.is_protected) return;

    const newStatus = !page.is_active;
    page.is_active = newStatus;

    router.post(
        route('pages.update', page.id),
        {
            slug: page.slug,
            is_active: newStatus,
            _method: 'PUT',
            translations: page.translations.reduce<Record<string, Record<string, string>>>((acc, t) => {
                if (!acc[t.field]) acc[t.field] = {};
                acc[t.field][t.locale] = t.value;
                return acc;
            }, {}),
        },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['pages', 'success', 'error', 'filters'],
            onError: () => {
                page.is_active = !newStatus;
            },
        },
    );
};
</script>

<template>
    <!-- Table view -->
    <div v-if="view === 'table'" class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
            <Table :scroll-label="t('pages')">
                <TableHeader>
                    <TableRow class="w-full text-start!">
                        <TableHead class="w-10 py-4">
                            <Checkbox v-model="isAllSelected" />
                        </TableHead>
                        <TableHead class="py-4 font-bold">{{ t('image') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('name') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('slug') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                        <TableHead class="sticky-actions py-4 font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="pages">
                        <TableRow v-for="page in pages.data" :key="page.id" v-highlight="page.id">
                            <TableCell class="py-4">
                                <Checkbox :modelValue="selectedIds" @update:modelValue="setSelected" :value="page.id" />
                            </TableCell>
                            <TableCell class="py-4">
                                <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-muted">
                                    <img
                                        v-if="page.image?.image_api"
                                        :src="page.image.image_api"
                                        :alt="page.name_api"
                                        class="h-full w-full object-cover"
                                    />
                                    <FileText v-else class="h-5 w-5 text-muted-foreground" />
                                </div>
                            </TableCell>
                            <TableCell class="py-4 font-medium">
                                <div class="flex flex-col gap-1">
                                    <span>{{ page.name_api }}</span>
                                    <span
                                        v-if="page.missing_translations?.length"
                                        :title="t('missing_translations_hint')"
                                        class="inline-flex w-fit items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600"
                                    >
                                        {{ t('missing_translations') }}: {{ page.missing_translations.join(', ') }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="py-4 text-muted-foreground">
                                <span class="inline-flex items-center gap-1.5">
                                    /{{ page.slug }}
                                    <Lock v-if="page.is_protected" class="h-3.5 w-3.5 text-muted-foreground" :aria-label="t('protected_page')" />
                                </span>
                            </TableCell>
                            <TableCell>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="page.is_active"
                                    :aria-label="`${t('status')}: ${page.name_api}`"
                                    :disabled="page.is_protected"
                                    :title="page.is_protected ? t('protected_page_must_stay_active') : undefined"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                    :class="page.is_active ? 'bg-primary' : 'bg-border'"
                                    @click="toggleStatus(page)"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="page.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                                    />
                                </button>
                            </TableCell>

                            <TableCell class="sticky-actions">
                                <div class="flex items-center gap-2">
                                    <Button
                                        as-child
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                    >
                                        <Link :href="route('pages.edit', page.id)">{{ t('edit') }}</Link>
                                    </Button>
                                    <Button
                                        v-if="!page.is_protected"
                                        variant="outline"
                                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        @click="emit('delete', page)"
                                    >
                                        {{ t('delete') }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!pages.data?.length" :colspan="6">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view -->
    <div v-else-if="!pages.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="pages">
        <div
            v-for="page in pages.data"
            :key="page.id"
            v-highlight="page.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: checkbox + image -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox :modelValue="selectedIds" @update:modelValue="setSelected" :value="page.id" />
                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted">
                    <img v-if="page.image?.image_api" :src="page.image.image_api" :alt="page.name_api" class="h-full w-full object-cover" />
                    <FileText v-else class="h-5 w-5 text-muted-foreground" />
                </div>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate font-bold text-foreground">{{ page.name_api }}</h3>
                <p class="flex items-center gap-1.5 truncate text-sm text-muted-foreground">
                    /{{ page.slug }}
                    <Lock v-if="page.is_protected" class="h-3.5 w-3.5 shrink-0" :aria-label="t('protected_page')" />
                </p>
                <span
                    v-if="page.missing_translations?.length"
                    :title="t('missing_translations_hint')"
                    class="inline-flex w-fit items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600"
                >
                    {{ t('missing_translations') }}: {{ page.missing_translations.join(', ') }}
                </span>
            </div>

            <!-- Status + actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="page.is_active"
                    :aria-label="`${t('status')}: ${page.name_api}`"
                    :disabled="page.is_protected"
                    :title="page.is_protected ? t('protected_page_must_stay_active') : undefined"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    :class="page.is_active ? 'bg-primary' : 'bg-border'"
                    @click="toggleStatus(page)"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                        :class="page.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                    />
                </button>

                <div class="flex items-center gap-2">
                    <Button as-child variant="outline" class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white">
                        <Link :href="route('pages.edit', page.id)">{{ t('edit') }}</Link>
                    </Button>
                    <Button
                        v-if="!page.is_protected"
                        variant="outline"
                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                        @click="emit('delete', page)"
                    >
                        {{ t('delete') }}
                    </Button>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
