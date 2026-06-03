<script setup>
import { router, usePage, InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CheckCircle, XCircle, Globe, Smartphone } from 'lucide-vue-next';
import { useHighlight } from '@/composables/useHighlight';

const { isHighlighted } = useHighlight();

const { t } = useI18n();
const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const authIdentifiers = computed(() => page.props.auth_identifiers);
const authFields = computed(() => page.props.auth_fields);

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
    const newStatus = !user.is_active;
    user.is_active = newStatus;

    const data = { name: user.name, is_active: newStatus };
    if (authFields.value.email) data.email = user.email;
    if (authFields.value.phone) data.phone = user.phone;
    if (authFields.value.username) data.username = user.username;

    router.put(
        route('app_users.update', user.id),
        data,
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['users', 'success', 'error', 'filters'],
            onError: () => {
                user.is_active = !newStatus;
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
                    <TableHead class="py-4 font-bold">{{ t('name') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('user_type') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('platform') }}</TableHead>
                    <TableHead v-if="authFields.email" class="py-4 font-bold">{{ t('email') }}</TableHead>
                    <TableHead v-if="authFields.phone" class="py-4 font-bold">{{ t('phone') }}</TableHead>
                    <TableHead v-if="authFields.username" class="py-4 font-bold">{{ t('username') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('status') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('verification') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('last_seen') }}</TableHead>
                    <TableHead class="py-4 font-bold">{{ t('actions') }}</TableHead>
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
                            <div class="flex flex-col">
                                <span>{{ user.name }}</span>
                                <span v-if="user.is_guest && user.guest_id" class="text-xs text-muted-foreground" :title="user.guest_id">
                                    {{ user.guest_id.substring(0, 12) }}…
                                </span>
                            </div>
                        </TableCell>
                        <TableCell>
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
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600"
                            >
                                {{ t('registered_user') }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="user.platform"
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
                            >
                                <Globe v-if="user.platform === 'web'" class="size-3" />
                                <Smartphone v-else class="size-3" />
                                {{ t(user.platform) }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell v-if="authFields.email">{{ user.email || '—' }}</TableCell>
                        <TableCell v-if="authFields.phone">{{ user.phone || '—' }}</TableCell>
                        <TableCell v-if="authFields.username">{{ user.username || '—' }}</TableCell>
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
                            <div class="flex flex-wrap items-center gap-1.5">
                                <div
                                    v-if="user.verified_at"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600"
                                >
                                    <CheckCircle class="size-3" />
                                    {{ t('verified') }}
                                </div>
                                <div
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-0.5 text-xs font-medium text-red-600"
                                >
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
                        </TableCell>

                        <TableCell class="text-xs text-muted-foreground">
                            <span v-if="user.last_seen_at">{{ new Date(user.last_seen_at).toLocaleString() }}</span>
                            <span v-else>—</span>
                        </TableCell>

                        <TableCell>
                            <div class="flex items-center gap-2">
                                <!-- Normal actions (not trashed) -->
                                <template v-if="!user.deleted_at">
                                    <Button
                                        v-if="!user.is_guest"
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
</template>
