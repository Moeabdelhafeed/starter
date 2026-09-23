<script lang="ts">
/** Minimal shapes this table actually reads — the shared `User` type has an index signature that widens `roles` to `unknown`. */
export interface RoleChip {
    id: number;
    name: string;
}

export interface AdminUserRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    deleted_at?: string | null;
    image?: { image_api?: string | null } | null;
    roles: RoleChip[];
}
</script>

<script setup lang="ts">
import { InfiniteScroll, router, usePage } from '@inertiajs/vue3';
import { Pencil, RotateCcw, Trash, Trash2, UserIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Paginated } from '@/types';

const { t } = useI18n();
const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const props = withDefaults(
    defineProps<{
        users: Paginated<AdminUserRow>;
        selectedIds?: number[];
        hasSoftDeletes?: boolean;
        view?: 'table' | 'grid';
    }>(),
    {
        selectedIds: () => [],
        hasSoftDeletes: false,
        view: 'table',
    },
);

const emit = defineEmits<{
    (e: 'edit' | 'delete' | 'restore' | 'forceDelete', user: AdminUserRow): void;
    (e: 'update:selectedIds', ids: number[]): void;
}>();

/** Checkbox emits `boolean | unknown[]`; in multi-select mode it is always the id array. */
const onSelectionChange = (value: unknown) => emit('update:selectedIds', value as number[]);

const isAllSelected = computed({
    get: () => props.users.data.length > 0 && props.selectedIds.length === props.users.data.length,
    set: (value: boolean) => {
        if (value) {
            emit(
                'update:selectedIds',
                props.users.data.map((u) => u.id),
            );
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const toggleStatus = (user: AdminUserRow) => {
    // Optimistic update
    const newStatus = !user.is_active;
    user.is_active = newStatus;

    const userRole = user.roles.length > 0 ? user.roles[0].name : '';
    router.post(
        route('users.update', user.id),
        {
            name: user.name,
            email: user.email,
            role: userRole,
            is_active: newStatus,
            _method: 'PUT',
        },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['users', 'success', 'error', 'filters'],
            onError: () => {
                // Revert on error
                user.is_active = !newStatus;
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
                        <TableHead class="py-4 font-bold">{{ t('roles') }}</TableHead>
                        <TableHead class="sticky-actions py-4 font-bold">{{ t('actions') }}</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <InfiniteScroll class="contents" preserve-url data="users">
                        <TableRow v-for="user in users.data" :key="user.id" v-highlight="user.id">
                            <TableCell class="py-4">
                                <Checkbox
                                    :model-value="selectedIds"
                                    :value="user.id"
                                    :aria-label="user.name"
                                    @update:model-value="onSelectionChange"
                                />
                            </TableCell>
                            <TableCell class="py-4 font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 shrink-0 overflow-hidden rounded-full bg-muted">
                                        <img
                                            v-if="user.image?.image_api"
                                            :src="user.image.image_api"
                                            :alt="user.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full w-full items-center justify-center">
                                            <UserIcon class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    </div>
                                    <div class="flex max-w-[240px] min-w-0 flex-col">
                                        <span class="truncate font-medium" :title="user.name">{{ user.name }}</span>
                                        <span class="truncate text-xs font-normal text-muted-foreground" :title="user.email">{{ user.email }}</span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="user.is_active"
                                    :aria-label="`${t('status')} — ${user.name}`"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="user.is_active ? 'bg-primary' : 'bg-border'"
                                    :disabled="user.id === currentUser?.id"
                                    @click="toggleStatus(user)"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="user.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                                    />
                                </button>
                            </TableCell>

                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary ring-1 ring-primary/20 ring-inset"
                                    >
                                        {{ role.name }}
                                    </span>
                                </div>
                            </TableCell>

                            <TableCell class="sticky-actions">
                                <div class="flex items-center gap-2">
                                    <!-- Normal actions (not trashed) -->
                                    <template v-if="!user.deleted_at">
                                        <Button
                                            size="icon-sm"
                                            variant="outline"
                                            :title="t('edit')"
                                            :aria-label="t('edit')"
                                            class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                            @click="emit('edit', user)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="icon-sm"
                                            variant="outline"
                                            :title="t('delete')"
                                            :aria-label="t('delete')"
                                            class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                            :disabled="user.id === currentUser?.id"
                                            @click="emit('delete', user)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </template>
                                    <!-- Trashed actions -->
                                    <template v-else-if="hasSoftDeletes">
                                        <Button
                                            size="icon-sm"
                                            variant="outline"
                                            :title="t('restore')"
                                            :aria-label="t('restore')"
                                            class="border-blue-500 text-blue-500 shadow-none! hover:bg-blue-500 hover:text-white"
                                            @click="emit('restore', user)"
                                        >
                                            <RotateCcw class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="icon-sm"
                                            variant="outline"
                                            :title="t('force_delete')"
                                            :aria-label="t('force_delete')"
                                            class="border-red-700 text-red-700 shadow-none! hover:bg-red-700 hover:text-white"
                                            @click="emit('forceDelete', user)"
                                        >
                                            <Trash class="h-4 w-4" />
                                        </Button>
                                    </template>
                                </div>
                            </TableCell>
                        </TableRow>
                    </InfiniteScroll>
                    <TableEmpty v-if="!users.data?.length" :colspan="5">
                        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Grid view — empty -->
    <div v-else-if="!users.data?.length" class="flex flex-col items-center gap-3 rounded-3xl border bg-card p-10 text-center">
        <p class="text-sm text-muted-foreground">{{ t('no_results_found') }}</p>
    </div>

    <!-- Grid view -->
    <InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="users">
        <div
            v-for="user in users.data"
            :key="user.id"
            v-highlight="user.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: checkbox + avatar + deleted badge -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox :model-value="selectedIds" :value="user.id" :aria-label="user.name" @update:model-value="onSelectionChange" />
                <div class="flex items-center gap-2">
                    <span v-if="user.deleted_at" class="inline-flex items-center rounded-md bg-red-500/10 px-2 py-1 text-xs font-medium text-red-500">
                        {{ t('trashed') }}
                    </span>
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-muted">
                        <img v-if="user.image?.image_api" :src="user.image.image_api" :alt="user.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <UserIcon class="h-5 w-5 text-muted-foreground" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate font-bold text-foreground">{{ user.name }}</h3>
                <p class="truncate text-sm text-muted-foreground">{{ user.email }}</p>
            </div>

            <!-- Roles -->
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="role in user.roles"
                    :key="role.id"
                    class="inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary ring-1 ring-primary/20 ring-inset"
                >
                    {{ role.name }}
                </span>
            </div>

            <!-- Status + actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="user.is_active"
                    :aria-label="`${t('status')} — ${user.name}`"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    :class="user.is_active ? 'bg-primary' : 'bg-border'"
                    :disabled="user.id === currentUser?.id"
                    @click="toggleStatus(user)"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                        :class="user.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                    />
                </button>

                <div class="flex items-center gap-2">
                    <!-- Normal actions (not trashed) -->
                    <template v-if="!user.deleted_at">
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :title="t('edit')"
                            :aria-label="t('edit')"
                            class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                            @click="emit('edit', user)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :title="t('delete')"
                            :aria-label="t('delete')"
                            class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                            :disabled="user.id === currentUser?.id"
                            @click="emit('delete', user)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </template>
                    <!-- Trashed actions -->
                    <template v-else-if="hasSoftDeletes">
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :title="t('restore')"
                            :aria-label="t('restore')"
                            class="border-blue-500 text-blue-500 shadow-none! hover:bg-blue-500 hover:text-white"
                            @click="emit('restore', user)"
                        >
                            <RotateCcw class="h-4 w-4" />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :title="t('force_delete')"
                            :aria-label="t('force_delete')"
                            class="border-red-700 text-red-700 shadow-none! hover:bg-red-700 hover:text-white"
                            @click="emit('forceDelete', user)"
                        >
                            <Trash class="h-4 w-4" />
                        </Button>
                    </template>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
