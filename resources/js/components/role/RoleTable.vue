<script lang="ts">
export interface PermissionChip {
    id: number;
    name: string;
}

/** The columns this table actually reads. */
export interface RoleRow {
    id: number;
    name: string;
    is_active: boolean;
    users_count: number;
    permissions?: PermissionChip[];
}
</script>

<script setup lang="ts">
import { InfiniteScroll, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Paginated } from '@/types';

const { t } = useI18n();
const page = usePage();
const currentUserRoles = computed(() => (page.props.currentUserRoles as string[] | undefined) ?? []);

const props = withDefaults(
    defineProps<{
        roles: Paginated<RoleRow>;
        selectedIds?: number[];
        view?: 'table' | 'grid';
    }>(),
    {
        selectedIds: () => [],
        view: 'table',
    },
);

const emit = defineEmits<{
    (e: 'edit' | 'delete', role: RoleRow): void;
    (e: 'update:selectedIds', ids: number[]): void;
}>();

/** Checkbox emits `boolean | unknown[]`; in multi-select mode it is always the id array. */
const onSelectionChange = (value: unknown) => emit('update:selectedIds', value as number[]);

/** `super_admin` and `fallback` are seeded, permission-locked roles. */
const isLocked = (role: RoleRow) => role.name === 'super_admin' || role.name === 'fallback';
const cannotDelete = (role: RoleRow) => isLocked(role) || currentUserRoles.value.includes(role.name);

const isAllSelected = computed({
    get: () => props.roles.data.length > 0 && props.selectedIds.length === props.roles.data.length,
    set: (value: boolean) => {
        if (value) {
            emit(
                'update:selectedIds',
                props.roles.data.map((r) => r.id),
            );
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const toggleStatus = (role: RoleRow) => {
    // Optimistic update
    const newStatus = !role.is_active;
    role.is_active = newStatus;

    router.post(
        route('roles.update', role.id),
        {
            is_active: newStatus,
            _method: 'PUT',
        },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['roles', 'success', 'error', 'filters'],
            onError: () => {
                // Revert on error
                role.is_active = !newStatus;
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
                        <TableHead class="w-10 py-4">
                            <Checkbox v-model="isAllSelected" :aria-label="t('select_all')" />
                        </TableHead>
                        <TableHead class="py-4 font-bold">{{ t('name') }}</TableHead>
                        <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                        <TableHead class="py-4 text-center font-bold">{{ t('users_count') }}</TableHead>
                        <TableHead class="sticky-actions py-4 text-end font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="roles">
                        <TableRow v-for="role in roles.data" :key="role.id" v-highlight="role.id">
                            <TableCell class="py-4">
                                <Checkbox
                                    :model-value="selectedIds"
                                    :value="role.id"
                                    :aria-label="role.name"
                                    @update:model-value="onSelectionChange"
                                />
                            </TableCell>
                            <TableCell class="py-4 text-xs font-medium tracking-wider uppercase">{{ role.name }}</TableCell>
                            <TableCell>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="role.is_active"
                                    :aria-label="`${t('status')} — ${role.name}`"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="role.is_active ? 'bg-primary' : 'bg-border'"
                                    :disabled="isLocked(role)"
                                    @click="toggleStatus(role)"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="role.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                                    />
                                </button>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                    {{ role.users_count }}
                                </span>
                            </TableCell>

                            <TableCell class="sticky-actions">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        size="icon-sm"
                                        variant="outline"
                                        :title="t('edit')"
                                        :aria-label="t('edit')"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                        :disabled="isLocked(role)"
                                        @click="emit('edit', role)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        size="icon-sm"
                                        variant="outline"
                                        :title="t('delete')"
                                        :aria-label="t('delete')"
                                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        :disabled="cannotDelete(role)"
                                        @click="emit('delete', role)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!roles.data?.length" :colspan="5">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view — empty -->
    <div v-else-if="!roles.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <!-- Grid view -->
    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="roles">
        <div
            v-for="role in roles.data"
            :key="role.id"
            v-highlight="role.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: checkbox + users count -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox :model-value="selectedIds" :value="role.id" :aria-label="role.name" @update:model-value="onSelectionChange" />
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                    {{ role.users_count }}
                </span>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate text-xs font-medium tracking-wider text-foreground uppercase">{{ role.name }}</h3>
                <p class="text-sm text-muted-foreground">{{ t('users_count') }}: {{ role.users_count }}</p>
            </div>

            <!-- Status + actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="role.is_active"
                    :aria-label="`${t('status')} — ${role.name}`"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    :class="role.is_active ? 'bg-primary' : 'bg-border'"
                    :disabled="isLocked(role)"
                    @click="toggleStatus(role)"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                        :class="role.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                    />
                </button>

                <div class="flex items-center gap-2">
                    <Button
                        size="icon-sm"
                        variant="outline"
                        :title="t('edit')"
                        :aria-label="t('edit')"
                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                        :disabled="isLocked(role)"
                        @click="emit('edit', role)"
                    >
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                        size="icon-sm"
                        variant="outline"
                        :title="t('delete')"
                        :aria-label="t('delete')"
                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                        :disabled="cannotDelete(role)"
                        @click="emit('delete', role)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
