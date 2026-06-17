<script setup>
import { router, usePage, InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { UserIcon } from 'lucide-vue-next';
import { useHighlight } from '@/composables/useHighlight';

const { isHighlighted } = useHighlight();

const { t } = useI18n();
const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const props = defineProps({
    users: Object,
    selectedIds: {
        type: Array,
        default: () => [],
    },
    hasSoftDeletes: {
        type: Boolean,
        default: false,
    },
    view: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['edit', 'delete', 'update:selectedIds', 'restore', 'forceDelete']);

const isAllSelected = computed({
    get: () => props.users.data.length > 0 && props.selectedIds.length === props.users.data.length,
    set: (value) => {
        if (value) {
            emit('update:selectedIds', props.users.data.map((u) => u.id));
        } else {
            emit('update:selectedIds', []);
        }
    },
});

const toggleStatus = (user) => {
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
                    <TableHead class="py-4 w-10">
                        <Checkbox v-model="isAllSelected" />
                    </TableHead>
                    <TableHead class="py-4 font-bold">{{ t('name') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('email') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('roles') }}</TableHead>
                    <TableHead class="py-4 font-bold sticky-actions">{{ t('actions') }}</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <InfiniteScroll class="contents" preserve-url data="users">
                    <TableRow v-for="user in users.data" :key="user.id"
                        :class="isHighlighted(user.id) ? 'animate-pulse ring-2 ring-primary/70 bg-primary/10' : ''">
                        <TableCell class="py-4">
                            <Checkbox :modelValue="selectedIds" @update:modelValue="emit('update:selectedIds', $event)" :value="user.id" />
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
                                {{ user.name }}
                            </div>
                        </TableCell>
                        <TableCell>{{ user.email }}</TableCell>
                        <TableCell>
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                :class="user.is_active ? 'bg-primary' : 'bg-border'"
                                @click="toggleStatus(user)"
                                :disabled="user.id === currentUser.id"
                                :style="user.id === currentUser.id ? 'opacity: 0.5; cursor: not-allowed;' : ''"
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
                                        variant="outline"
                                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                                        @click="emit('edit', user)"
                                    >
                                        {{ t('edit') }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                                        @click="emit('delete', user)"
                                        :disabled="user.id === currentUser.id"
                                    >
                                        {{ t('delete') }}
                                    </Button>
                                </template>
                                <!-- Trashed actions -->
                                <template v-else-if="hasSoftDeletes">
                                    <Button
                                        variant="outline"
                                        class="border-blue-500 text-blue-500 shadow-none! hover:bg-blue-500 hover:text-white"
                                        @click="emit('restore', user)"
                                    >
                                        {{ t('restore') }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="border-red-700 text-red-700 shadow-none! hover:bg-red-700 hover:text-white"
                                        @click="emit('forceDelete', user)"
                                    >
                                        {{ t('force_delete') }}
                                    </Button>
                                </template>
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
        data="users"
    >
        <div
            v-for="user in users.data"
            :key="user.id"
            class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"
            :class="isHighlighted(user.id) ? 'animate-pulse ring-2 ring-primary/70 bg-primary/10' : ''"
        >
            <!-- Top: checkbox + avatar + deleted badge -->
            <div class="flex items-start justify-between gap-3">
                <Checkbox
                    :modelValue="selectedIds"
                    @update:modelValue="emit('update:selectedIds', $event)"
                    :value="user.id"
                />
                <div class="flex items-center gap-2">
                    <span v-if="user.deleted_at" class="rounded-md bg-red-500/10 px-2 py-1 text-xs font-medium text-red-500">
                        {{ t('trashed') }}
                    </span>
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-muted">
                        <img
                            v-if="user.image?.image_api"
                            :src="user.image.image_api"
                            :alt="user.name"
                            class="h-full w-full object-cover"
                        />
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
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                    :class="user.is_active ? 'bg-primary' : 'bg-border'"
                    @click="toggleStatus(user)"
                    :disabled="user.id === currentUser.id"
                    :style="user.id === currentUser.id ? 'opacity: 0.5; cursor: not-allowed;' : ''"
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
                            variant="outline"
                            class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                            @click="emit('edit', user)"
                        >
                            {{ t('edit') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                            @click="emit('delete', user)"
                            :disabled="user.id === currentUser.id"
                        >
                            {{ t('delete') }}
                        </Button>
                    </template>
                    <!-- Trashed actions -->
                    <template v-else-if="hasSoftDeletes">
                        <Button
                            variant="outline"
                            class="border-blue-500 text-blue-500 shadow-none! hover:bg-blue-500 hover:text-white"
                            @click="emit('restore', user)"
                        >
                            {{ t('restore') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="border-red-700 text-red-700 shadow-none! hover:bg-red-700 hover:text-white"
                            @click="emit('forceDelete', user)"
                        >
                            {{ t('force_delete') }}
                        </Button>
                    </template>
                </div>
            </div>
        </div>
    </InfiniteScroll>
</template>
