<script setup>
import { InfiniteScroll, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Star } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    languages: Object,
    view: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['edit', 'delete']);

const toggleStatus = (lang) => {
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
        <Table>
            <TableHeader>
                <TableRow class="w-full text-start!">
                    <TableHead class="py-4 font-bold">{{ t('language_code') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('language_name') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('native_name') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('direction') }}</TableHead>
                    <TableHead class="py-4 font-bold text-center">{{ t('image') }}</TableHead>
                    <TableHead class="py-4 font-bold text-center">{{ t('status') }}</TableHead>
                    <TableHead class="py-4 font-bold text-end sticky-actions">{{ t('actions') }}</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <InfiniteScroll class="contents" preserve-url data="languages">
                    <TableRow v-for="lang in languages.data" :key="lang.id">
                        <TableCell class="py-4 font-mono font-medium uppercase">
                            <div class="flex items-center gap-2">
                                {{ lang.code }}
                                <Star v-if="lang.is_default" class="size-4 fill-yellow-400 text-yellow-400" />
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
                                v-if="lang.image"
                                :src="lang.image.image_api"
                                :alt="lang.name"
                                class="mx-auto h-6 w-9 rounded object-cover"
                            />
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="py-4 text-center">
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                :class="lang.is_active ? 'bg-primary' : 'bg-border'"
                                @click="toggleStatus(lang)"
                                :disabled="lang.is_default"
                                :style="lang.is_default ? 'opacity: 0.5; cursor: not-allowed;' : ''"
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
                                    size="sm"
                                    class="border-yellow-500/50 text-yellow-600 shadow-none! hover:bg-yellow-500 hover:text-white"
                                    @click="emit('edit', lang)"
                                >
                                    {{ t('edit') }}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-red-500/50 text-red-600 shadow-none! hover:bg-red-500 hover:text-white"
                                    @click="emit('delete', lang)"
                                    :disabled="lang.is_default"
                                >
                                    {{ t('delete') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </InfiniteScroll>
            </TableBody>
        </Table>
        </div>
    </div>

    <!-- Grid view -->
    <InfiniteScroll
        v-else
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        preserve-url
        data="languages"
    >
        <div
            v-for="lang in languages.data"
            :key="lang.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: flag image + code/default -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="h-9 w-12 shrink-0 overflow-hidden rounded bg-muted">
                        <img
                            v-if="lang.image"
                            :src="lang.image.image_api"
                            :alt="lang.name"
                            class="h-full w-full object-cover"
                        />
                    </div>
                    <span class="font-mono font-medium uppercase">{{ lang.code }}</span>
                    <Star v-if="lang.is_default" class="size-4 fill-yellow-400 text-yellow-400" />
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
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                    :class="lang.is_active ? 'bg-primary' : 'bg-border'"
                    @click="toggleStatus(lang)"
                    :disabled="lang.is_default"
                    :style="lang.is_default ? 'opacity: 0.5; cursor: not-allowed;' : ''"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                        :class="lang.is_active ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                    />
                </button>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="border-yellow-500/50 text-yellow-600 shadow-none! hover:bg-yellow-500 hover:text-white"
                        @click="emit('edit', lang)"
                    >
                        {{ t('edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="border-red-500/50 text-red-600 shadow-none! hover:bg-red-500 hover:text-white"
                        @click="emit('delete', lang)"
                        :disabled="lang.is_default"
                    >
                        {{ t('delete') }}
                    </Button>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
