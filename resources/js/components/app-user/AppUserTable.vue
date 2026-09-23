<script lang="ts">
/** The columns this table actually reads. Kept local: the shared `User` type's index signature widens everything to `unknown`. */
export interface AppUserRow {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    username?: string | null;
    is_active: boolean;
    is_guest?: boolean;
    is_reviewer?: boolean;
    guest_id?: string | null;
    platform?: string | null;
    verified_at?: string | null;
    account_deleted_at?: string | null;
    last_seen_at?: string | null;
    deleted_at?: string | null;
}
</script>

<script setup lang="ts">
import { InfiniteScroll, router, usePage } from '@inertiajs/vue3';
import { CheckCircle, Globe, Pencil, RotateCcw, Smartphone, Trash, Trash2, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import type { Paginated } from '@/types';

const { formatDate } = useDateFormat();

const { t } = useI18n();
const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const authFields = computed(() => page.props.auth_fields as { email?: boolean; phone?: boolean; username?: boolean });

const props = withDefaults(
    defineProps<{
        users: Paginated<AppUserRow>;
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
    (e: 'edit' | 'delete' | 'restore' | 'forceDelete', user: AppUserRow): void;
    (e: 'update:selectedIds', ids: number[]): void;
}>();

/** Checkbox emits `boolean | unknown[]`; in multi-select mode it is always the id array. */
const onSelectionChange = (value: unknown) => emit('update:selectedIds', value as number[]);

/** checkbox, user (name + identifiers + type/platform), status (+ verification), last_seen, actions */
const columnCount = 5;

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

const toggleStatus = (user: AppUserRow) => {
    const newStatus = !user.is_active;
    user.is_active = newStatus;

    const data: Record<string, string | number | boolean | null | undefined> = { name: user.name, is_active: newStatus, _method: 'PUT' };
    if (authFields.value.email) data.email = user.email;
    if (authFields.value.phone) data.phone = user.phone;
    if (authFields.value.username) data.username = user.username;

    router.post(route('app_users.update', user.id), data, {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error', 'filters'],
        onError: () => {
            user.is_active = !newStatus;
        },
    });
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
                        <TableHead class="py-4 font-bold">{{ t('last_seen') }}</TableHead>
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
                            <TableCell class="py-4">
                                <div class="flex max-w-[260px] min-w-0 flex-col gap-0.5">
                                    <span class="truncate font-medium" :title="user.name">{{ user.name }}</span>
                                    <span v-if="user.is_guest && user.guest_id" class="truncate text-xs text-muted-foreground" :title="user.guest_id">
                                        {{ user.guest_id.substring(0, 12) }}…
                                    </span>
                                    <span v-if="authFields.email" class="truncate text-xs text-muted-foreground" :title="user.email || ''">
                                        {{ user.email || '—' }}
                                    </span>
                                    <span v-if="authFields.phone" class="truncate text-xs text-muted-foreground" dir="ltr" :title="user.phone || ''">
                                        {{ user.phone || '—' }}
                                    </span>
                                    <span v-if="authFields.username" class="truncate text-xs text-muted-foreground" :title="user.username || ''">
                                        {{ user.username || '—' }}
                                    </span>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <span
                                            v-if="user.is_reviewer"
                                            class="inline-flex items-center gap-1 rounded-full bg-purple-500/10 px-2 py-0.5 text-[10px] font-medium text-purple-600"
                                        >
                                            {{ t('reviewer') }}
                                        </span>
                                        <span
                                            v-else-if="user.is_guest"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-600"
                                        >
                                            {{ t('guest') }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-medium text-blue-600"
                                        >
                                            {{ t('registered_user') }}
                                        </span>
                                        <span
                                            v-if="user.platform"
                                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium"
                                        >
                                            <Globe v-if="user.platform === 'web'" class="size-3" />
                                            <Smartphone v-else class="size-3" />
                                            {{ t(user.platform) }}
                                        </span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col items-start gap-1.5">
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
                                    <div
                                        v-if="user.verified_at"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600"
                                    >
                                        <CheckCircle class="size-3" />
                                        {{ t('verified') }}
                                    </div>
                                    <div
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-[10px] font-medium text-red-600"
                                    >
                                        <XCircle class="size-3" />
                                        {{ t('not_verified') }}
                                    </div>
                                    <div
                                        v-if="user.account_deleted_at"
                                        class="inline-flex items-center gap-1 rounded-full bg-orange-500/10 px-2 py-0.5 text-[10px] font-medium text-orange-600"
                                        :title="t('pending_deletion_hint')"
                                    >
                                        <XCircle class="size-3" />
                                        {{ t('pending_deletion') }}
                                    </div>
                                </div>
                            </TableCell>

                            <TableCell class="text-xs whitespace-nowrap text-muted-foreground">
                                <span v-if="user.last_seen_at">{{ formatDate(user.last_seen_at) }}</span>
                                <span v-else>—</span>
                            </TableCell>

                            <TableCell class="sticky-actions">
                                <div class="flex items-center gap-2">
                                    <!-- Normal actions (not trashed) -->
                                    <template v-if="!user.deleted_at">
                                        <Button
                                            v-if="!user.is_guest"
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
                    <TableEmpty v-if="!users.data?.length" :colspan="columnCount">
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
            <!-- Top: checkbox + badges -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox :model-value="selectedIds" :value="user.id" :aria-label="user.name" @update:model-value="onSelectionChange" />
                <div class="flex flex-wrap items-center justify-end gap-1.5">
                    <span v-if="user.deleted_at" class="inline-flex items-center rounded-md bg-red-500/10 px-2 py-1 text-xs font-medium text-red-500">
                        {{ t('trashed') }}
                    </span>
                    <div
                        v-if="user.verified_at"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600"
                    >
                        <CheckCircle class="size-3" />
                        {{ t('verified') }}
                    </div>
                    <div v-else class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-0.5 text-xs font-medium text-red-600">
                        <XCircle class="size-3" />
                        {{ t('not_verified') }}
                    </div>
                    <div
                        v-if="user.account_deleted_at"
                        class="inline-flex items-center gap-1 rounded-full bg-orange-500/10 px-2.5 py-0.5 text-xs font-medium text-orange-600"
                        :title="t('pending_deletion_hint')"
                    >
                        <XCircle class="size-3" />
                        {{ t('pending_deletion') }}
                    </div>
                </div>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate font-bold text-foreground">{{ user.name }}</h3>
                <span v-if="user.is_guest && user.guest_id" class="truncate text-xs text-muted-foreground" :title="user.guest_id">
                    {{ user.guest_id.substring(0, 12) }}…
                </span>
                <p v-if="authFields.email" class="truncate text-sm text-muted-foreground">{{ user.email || '—' }}</p>
                <p v-if="authFields.phone" class="truncate text-sm text-muted-foreground">{{ user.phone || '—' }}</p>
                <p v-if="authFields.username" class="truncate text-sm text-muted-foreground">{{ user.username || '—' }}</p>
            </div>

            <!-- User type + platform -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span
                    v-if="user.is_reviewer"
                    class="inline-flex items-center gap-1 rounded-full bg-purple-500/10 px-2.5 py-0.5 text-xs font-medium text-purple-600"
                >
                    {{ t('reviewer') }}
                </span>
                <span
                    v-else-if="user.is_guest"
                    class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-600"
                >
                    {{ t('guest') }}
                </span>
                <span v-else class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600">
                    {{ t('registered_user') }}
                </span>
                <span v-if="user.platform" class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                    <Globe v-if="user.platform === 'web'" class="size-3" />
                    <Smartphone v-else class="size-3" />
                    {{ t(user.platform) }}
                </span>
            </div>

            <!-- Last seen -->
            <div class="text-xs text-muted-foreground">
                <span v-if="user.last_seen_at">{{ t('last_seen') }}: {{ formatDate(user.last_seen_at) }}</span>
                <span v-else>{{ t('last_seen') }}: —</span>
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
                            v-if="!user.is_guest"
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
