<script setup>
import { router, usePage, InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const { t } = useI18n();
const page = usePage();
const currentUserRoles = computed(() => page.props.currentUserRoles || []);

const props = defineProps({
    roles: Object,
    selectedIds: {
        type: Array,
        default: () => [],
    },
    view: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['edit', 'delete', 'update:selectedIds']);

const isAllSelected = computed({
    get: () => props.roles.data.length > 0 && props.selectedIds.length === props.roles.data.length,
    set: (value) => {
        if (value) {
            emit('update:selectedIds', props.roles.data.map((r) => r.id));
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const toggleStatus = (role) => {
    // Optimistic update
    const newStatus = !role.is_active;
    role.is_active = newStatus;

    router.put(
        route('roles.update', role.id),
        {
            is_active: newStatus,
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
                    <TableHead class="py-4 w-10">
                        <Checkbox v-model="isAllSelected" />
                    </TableHead>
                    <TableHead class="py-4 font-bold">{{ t('name') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                    <TableHead class="py-4 font-bold text-center">{{ t('users_count') }}</TableHead>
                    <TableHead class="py-4 font-bold text-end sticky-actions">{{ t('actions') }}</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <InfiniteScroll class="contents" preserve-url data="roles">
                    <TableRow v-for="role in roles.data" :key="role.id">
                        <TableCell class="py-4">
                            <Checkbox :modelValue="selectedIds" @update:modelValue="emit('update:selectedIds', $event)" :value="role.id" />
                        </TableCell>
                        <TableCell class="py-4 font-medium uppercase tracking-wider text-xs">{{ role.name }}</TableCell>
                        <TableCell>
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                :class="role.is_active ? 'bg-primary' : 'bg-border'"
                                @click="toggleStatus(role)"
                                :disabled="role.name === 'super_admin' || role.name === 'fallback'"
                                :style="role.name === 'super_admin' || role.name === 'fallback' ? 'opacity: 0.5; cursor: not-allowed;' : ''"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="role.is_active ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                                />
                            </button>
                        </TableCell>
                        <TableCell class="text-center">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-muted text-xs font-semibold">
                                {{ role.users_count }}
                            </span>
                        </TableCell>

                        <TableCell class="sticky-actions">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-yellow-500/50 text-yellow-600 shadow-none! hover:bg-yellow-500 hover:text-white"
                                    @click="emit('edit', role)"
                                    :disabled="role.name === 'super_admin' || role.name === 'fallback'"
                                >
                                    {{ t('edit') }}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-red-500/50 text-red-600 shadow-none! hover:bg-red-500 hover:text-white"
                                    @click="emit('delete', role)"
                                    :disabled="
                                        currentUserRoles.includes(role.name) || role.name === 'super_admin' || role.name === 'fallback'
                                    "
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
        data="roles"
    >
        <div
            v-for="role in roles.data"
            :key="role.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
        >
            <!-- Top: checkbox + users count -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox
                    :modelValue="selectedIds"
                    @update:modelValue="emit('update:selectedIds', $event)"
                    :value="role.id"
                />
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                    {{ role.users_count }}
                </span>
            </div>

            <!-- Identity -->
            <div class="flex flex-col gap-1">
                <h3 class="truncate text-xs font-medium uppercase tracking-wider text-foreground">{{ role.name }}</h3>
                <p class="text-sm text-muted-foreground">{{ t('users_count') }}: {{ role.users_count }}</p>
            </div>

            <!-- Status + actions -->
            <div class="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <button
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                    :class="role.is_active ? 'bg-primary' : 'bg-border'"
                    @click="toggleStatus(role)"
                    :disabled="role.name === 'super_admin' || role.name === 'fallback'"
                    :style="role.name === 'super_admin' || role.name === 'fallback' ? 'opacity: 0.5; cursor: not-allowed;' : ''"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                        :class="role.is_active ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                    />
                </button>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="border-yellow-500/50 text-yellow-600 shadow-none! hover:bg-yellow-500 hover:text-white"
                        @click="emit('edit', role)"
                        :disabled="role.name === 'super_admin' || role.name === 'fallback'"
                    >
                        {{ t('edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="border-red-500/50 text-red-600 shadow-none! hover:bg-red-500 hover:text-white"
                        @click="emit('delete', role)"
                        :disabled="
                            currentUserRoles.includes(role.name) || role.name === 'super_admin' || role.name === 'fallback'
                        "
                    >
                        {{ t('delete') }}
                    </Button>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
