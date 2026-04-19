<script setup>
import { router, InfiniteScroll, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FileText } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    pages: Object,
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['delete', 'update:selectedIds']);

const isAllSelected = computed({
    get: () => props.pages.data.length > 0 && props.selectedIds.length === props.pages.data.length,
    set: (value) => {
        if (value) {
            emit('update:selectedIds', props.pages.data.map((p) => p.id));
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const toggleStatus = (page) => {
    const newStatus = !page.is_active;
    page.is_active = newStatus;

    router.put(
        route('pages.update', page.id),
        {
            slug: page.slug,
            is_active: newStatus,
            translations: page.translations.reduce((acc, t) => {
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
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-4 md:p-6">
        <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow class="w-full text-start!">
                    <TableHead class="py-4 w-10">
                        <Checkbox v-model="isAllSelected" />
                    </TableHead>
                    <TableHead class="py-4 font-bold">{{ t('image') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('name') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('slug') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('actions') }}</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <InfiniteScroll class="contents" preserve-url data="pages">
                    <TableRow v-for="page in pages.data" :key="page.id">
                        <TableCell class="py-4">
                            <Checkbox :modelValue="selectedIds" @update:modelValue="emit('update:selectedIds', $event)" :value="page.id" />
                        </TableCell>
                        <TableCell class="py-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted overflow-hidden">
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
                            {{ page.name_api }}
                        </TableCell>
                        <TableCell class="py-4 text-muted-foreground">
                            /{{ page.slug }}
                        </TableCell>
                        <TableCell>
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                :class="page.is_active ? 'bg-primary' : 'bg-border'"
                                @click="toggleStatus(page)"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="page.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                                />
                            </button>
                        </TableCell>

                        <TableCell>
                            <div class="flex items-center gap-2">
                                <Link :href="route('pages.edit', page.id)">
                                    <Button
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                    >
                                        {{ t('edit') }}
                                    </Button>
                                </Link>
                                <Button
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
            </TableBody>
        </Table>
        </div>
    </div>
</template>
