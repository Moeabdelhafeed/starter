<script setup>
import { InfiniteScroll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const { t } = useI18n();

const props = defineProps({
    translations: Object,
    activeLocale: String,
});

const emit = defineEmits(['edit']);

const getGroupBadgeClass = (group) => {
    const classes = {
        api: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        app: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        web: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };
    return classes[group] || classes.app;
};

const getGroupLabel = (group) => {
    const labels = {
        api: t('api_group'),
        app: t('app_group'),
        web: t('web_group'),
    };
    return labels[group] || group;
};
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow class="w-full">
                    <TableHead class="py-4 font-bold">{{ t('key') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('group') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('value') }}</TableHead>
                    <TableHead class="py-4 font-bold text-end">{{ t('actions') }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <InfiniteScroll class="contents" preserve-url data="translations">
                    <TableRow v-for="(trans, index) in translations.data" :key="index">
                        <TableCell class="py-4 font-medium">{{ trans.key }}</TableCell>
                        <TableCell>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getGroupBadgeClass(trans.group)"
                            >
                                {{ getGroupLabel(trans.group) }}
                            </span>
                        </TableCell>
                        <TableCell>{{ trans[activeLocale] }}</TableCell>
                        <TableCell class="text-end">
                            <Button
                                variant="outline"
                                class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                @click="emit('edit', trans)"
                            >
                                {{ t('edit') }}
                            </Button>
                        </TableCell>
                    </TableRow>
                </InfiniteScroll>
            </TableBody>
        </Table>
        </div>
    </div>
</template>
