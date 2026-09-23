<script setup lang="ts">
import { InfiniteScroll, router } from '@inertiajs/vue3';
import { Star } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { LanguageRow, Paginated } from '@/types';

const { t } = useI18n();

withDefaults(
    defineProps<{
        languages: Paginated<LanguageRow>;
        view?: 'table' | 'grid';
    }>(),
    { view: 'table' },
);

const emit = defineEmits<{
    (e: 'edit', language: LanguageRow): void;
    (e: 'delete', language: LanguageRow): void;
}>();

const toggleStatus = (lang: LanguageRow) => {
    const newStatus = !lang.is_active;
    lang.is_active = newStatus;

    router.post(
        route('languages.update', lang.id),
        {
            code: lang.code,
            name: lang.name,
            native_name: lang.native_name,
            direction: lang.direction,
            is_active: newStatus,
            is_default: lang.is_default,
            _method: 'PUT',
        },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['languages', 'success', 'error', 'filters'],
            onError: () => {
                lang.is_active = !newStatus;
            },
        },
    );
};
</script>

<template>
    <!-- Table view -->
    <div v-if="view === 'table'" class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
            <Table :scroll-label="t('languages')">
                <TableHeader>
                    <TableRow class="w-full text-start!">
                        <TableHead class="py-4 font-bold">{{ t('language_code') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('language_name') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('native_name') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('direction') }}</TableHead>
                        <TableHead class="py-4 text-center font-bold">{{ t('image') }}</TableHead>
                        <TableHead class="py-4 text-center font-bold">{{ t('status') }}</TableHead>
                        <TableHead class="sticky-actions py-4 text-end font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="languages">
                        <TableRow v-for="lang in languages.data" :key="lang.id" v-highlight="lang.id">
                            <TableCell class="py-4 font-mono font-medium uppercase">
                                <div class="flex items-center gap-2">
                                    {{ lang.code }}
                                    <Star v-if="lang.is_default" class="size-4 fill-yellow-400 text-yellow-400" :aria-label="t('is_default')" />
                                </div>
                            </TableCell>
                            <TableCell class="py-4">{{ lang.name }}</TableCell>
                            <TableCell class="py-4">{{ lang.native_name }}</TableCell>
                            <TableCell class="py-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="lang.direction === 'rtl' ? 'bg-purple-500/10 text-purple-600' : 'bg-blue-500/10 text-blue-600'"
                                >
                                    {{ lang.direction.toUpperCase() }}
                                </span>
                            </TableCell>
                            <TableCell class="py-4 text-center">
                                <img
                                    v-if="lang.image?.image_api"
                                    :src="lang.image.image_api"
                                    :alt="lang.name"
                                    class="mx-auto h-6 w-9 rounded object-cover"
                                />
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="py-4 text-center">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="lang.is_active"
                                    :aria-label="`${t('status')}: ${lang.name}`"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="lang.is_active ? 'bg-primary' : 'bg-border'"
                                    :disabled="lang.is_default"
                                    @click="toggleStatus(lang)"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="lang.is_active ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                                    />
                                </button>
                            </TableCell>
                            <TableCell class="sticky-actions">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                        @click="emit('edit', lang)"
                                    >
                                        {{ t('edit') }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        :disabled="lang.is_default"
                                        @click="emit('delete', lang)"
                                    >
                                        {{ t('delete') }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!languages.data?.length" :colspan="7">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view -->
    <div v-else-if="!languages.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="languages">
        <div
            v-for="lang in languages.data"
            :key="lang.id"
            v-highlight="lang.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: flag image + code/default -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="h-9 w-12 shrink-0 overflow-hidden rounded bg-muted">
                        <img v-if="lang.image?.image_api" :src="lang.image.image_api" :alt="lang.name" class="h-full w-full object-cover" />
                    </div>
                    <span class="font-mono font-medium uppercase">{{ lang.code }}</span>
                    <Star v-if="lang.is_default" class="size-4 fill-yellow-400 text-yellow-400" :aria-label="t('is_default')" />
                </div>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="lang.direction === 'rtl' ? 'bg-purple-500/10 text-purple-600' : 'bg-blue-500/10 text-blue-600'"
                >
                    {{ lang.direction.toUpperCase() }}
                </span>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate font-bold text-foreground">{{ lang.name }}</h3>
                <p class="truncate text-sm text-muted-foreground">{{ lang.native_name }}</p>
            </div>

            <!-- Status + actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="lang.is_active"
                    :aria-label="`${t('status')}: ${lang.name}`"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    :class="lang.is_active ? 'bg-primary' : 'bg-border'"
                    :disabled="lang.is_default"
                    @click="toggleStatus(lang)"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                        :class="lang.is_active ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                    />
                </button>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                        @click="emit('edit', lang)"
                    >
                        {{ t('edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                        :disabled="lang.is_default"
                        @click="emit('delete', lang)"
                    >
                        {{ t('delete') }}
                    </Button>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
