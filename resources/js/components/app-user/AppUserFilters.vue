<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { SearchIcon, XIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import TrashedFilter from '@/components/Shared/TrashedFilter.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { FilterValue } from '@/types';

const { t } = useI18n();
const page = usePage();
const pageTitle = computed(() => (page.props.app_users ? t('app_users') : t('guest_users')));

const props = withDefaults(
    defineProps<{
        filters: Record<string, FilterValue>;
        hasSoftDeletes?: boolean;
    }>(),
    { hasSoftDeletes: false },
);

const search = ref(String(props.filters?.search ?? ''));
const isActive = ref(String(props.filters?.is_active ?? 'all'));
const isVerified = ref(String(props.filters?.is_verified ?? 'all'));
const pendingDeletion = ref(String(props.filters?.pending_deletion ?? 'all'));
const trashed = ref(String(props.filters?.trashed ?? ''));
const userType = ref(String(props.filters?.user_type ?? 'all'));
const platform = ref(String(props.filters?.platform ?? 'all'));

const searchFunc = () => {
    router.get(
        route('app_users'),
        {
            search: search.value || undefined,
            is_active: isActive.value !== '' && isActive.value !== 'all' ? isActive.value : undefined,
            is_verified: isVerified.value !== '' && isVerified.value !== 'all' ? isVerified.value : undefined,
            pending_deletion: pendingDeletion.value !== '' && pendingDeletion.value !== 'all' ? pendingDeletion.value : undefined,
            trashed: trashed.value || undefined,
            user_type: userType.value !== '' && userType.value !== 'all' ? userType.value : undefined,
            platform: platform.value !== '' && platform.value !== 'all' ? platform.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            reset: ['users', 'success', 'error', 'filters'],
        },
    );
};

const onTrashedChange = (value: string) => {
    trashed.value = value;
    searchFunc();
};

const clearFilters = () => {
    search.value = '';
    isActive.value = 'all';
    isVerified.value = 'all';
    pendingDeletion.value = 'all';
    trashed.value = '';
    userType.value = 'all';
    platform.value = 'all';
    searchFunc();
};

const clearFilter = (key: string) => {
    if (key === 'search') search.value = '';
    if (key === 'is_active') isActive.value = 'all';
    if (key === 'is_verified') isVerified.value = 'all';
    if (key === 'pending_deletion') pendingDeletion.value = 'all';
    if (key === 'trashed') trashed.value = '';
    if (key === 'user_type') userType.value = 'all';
    if (key === 'platform') platform.value = 'all';
    searchFunc();
};

const getFilterLabel = (key: string, value: FilterValue): string => {
    if (key === 'is_active') return value === '1' ? t('active') : t('inactive');
    if (key === 'is_verified') return value === '1' ? t('verified') : t('not_verified');
    if (key === 'pending_deletion') {
        if (value === 'only') return t('pending_deletion');
        if (value === 'exclude') return t('not_pending_deletion');
    }
    if (key === 'trashed') {
        if (value === 'only') return t('trashed_only');
        if (value === 'with') return t('with_trashed');
    }
    if (key === 'user_type') {
        if (value === 'guest') return t('guest');
        if (value === 'user') return t('registered_user');
    }
    return String(value);
};

const getFilterKeyLabel = (key: string): string => {
    if (key === 'is_active') return t('status');
    if (key === 'is_verified') return t('verification');
    if (key === 'pending_deletion') return t('pending_deletion');
    if (key === 'search') return t('search');
    if (key === 'trashed') return t('trashed');
    if (key === 'user_type') return t('user_type');
    if (key === 'platform') return t('platform');
    return t(key);
};

const isActiveFilter = (value: FilterValue, key: string): boolean =>
    value !== undefined && value !== null && value !== '' && value !== 'all' && key !== 'page';
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border border-border bg-card p-6 pb-7">
        <!-- Title -->
        <div class="flex w-full flex-col gap-10">
            <div class="flex w-full items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ pageTitle }}
                </h1>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="flex flex-col items-start justify-between gap-4">
            <form class="flex w-full flex-wrap items-center gap-4" @submit.prevent="searchFunc">
                <div class="relative min-w-[200px] flex-1">
                    <Input v-model="search" :placeholder="t('search')" :aria-label="t('search')" class="w-full bg-primary/2 shadow-none!" />
                </div>

                <Button type="submit">
                    <SearchIcon class="me-2 h-4 w-4" />
                    {{ t('search') }}
                </Button>
            </form>

            <!-- Advanced Filters -->
            <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                <div>
                    <Select v-model="isActive" @update:model-value="searchFunc">
                        <SelectTrigger class="bg-primary/2" :aria-label="t('status')">
                            <SelectValue :placeholder="t('status')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_statuses') }}</SelectItem>
                            <SelectItem value="1">{{ t('active') }}</SelectItem>
                            <SelectItem value="0">{{ t('inactive') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Select v-model="isVerified" @update:model-value="searchFunc">
                        <SelectTrigger class="bg-primary/2" :aria-label="t('verification')">
                            <SelectValue :placeholder="t('verification')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_verifications') }}</SelectItem>
                            <SelectItem value="1">{{ t('verified') }}</SelectItem>
                            <SelectItem value="0">{{ t('not_verified') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Select v-model="userType" @update:model-value="searchFunc">
                        <SelectTrigger class="bg-primary/2" :aria-label="t('user_type')">
                            <SelectValue :placeholder="t('user_type')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_types') }}</SelectItem>
                            <SelectItem value="guest">{{ t('guest') }}</SelectItem>
                            <SelectItem value="user">{{ t('registered_user') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Select v-model="platform" @update:model-value="searchFunc">
                        <SelectTrigger class="bg-primary/2" :aria-label="t('platform')">
                            <SelectValue :placeholder="t('platform')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_platforms') }}</SelectItem>
                            <SelectItem value="web">{{ t('web') }}</SelectItem>
                            <SelectItem value="ios">{{ t('ios') }}</SelectItem>
                            <SelectItem value="android">{{ t('android') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Select v-model="pendingDeletion" @update:model-value="searchFunc">
                        <SelectTrigger class="bg-primary/2" :aria-label="t('pending_deletion')">
                            <SelectValue :placeholder="t('pending_deletion')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all') }}</SelectItem>
                            <SelectItem value="only">{{ t('pending_deletion') }}</SelectItem>
                            <SelectItem value="exclude">{{ t('not_pending_deletion') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="hasSoftDeletes">
                    <TrashedFilter :model-value="trashed" @update:model-value="onTrashedChange" />
                </div>
            </div>

            <!-- Active Filters Chips -->
            <div v-if="Object.keys(filters).some((k) => isActiveFilter(filters[k], k))" class="mt-2 flex flex-wrap gap-2">
                <template v-for="(value, key) in filters" :key="key">
                    <Button
                        v-if="isActiveFilter(value, String(key))"
                        variant="secondary"
                        size="sm"
                        class="group h-8 rounded-full bg-primary/10 px-3 text-xs text-primary hover:bg-primary/20"
                        @click="clearFilter(String(key))"
                    >
                        <span class="me-1 font-medium">{{ getFilterKeyLabel(String(key)) }}:</span>
                        {{ getFilterLabel(String(key), value) }}
                        <XIcon class="ms-1 h-3 w-3 transition-transform group-hover:scale-110" />
                    </Button>
                </template>
                <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground hover:text-red-500" @click="clearFilters">
                    {{ t('clear_filters') }}
                </Button>
            </div>
        </div>
    </div>
</template>
