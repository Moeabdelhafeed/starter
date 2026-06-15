<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { SearchIcon, XIcon } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const { t } = useI18n();

const props = defineProps({
    filters: Object,
});

const search = ref(props.filters?.search || '');
const isActive = ref(props.filters?.is_active || '');

const searchFunc = () => {
    router.get(
        route('languages'),
        {
            search: search.value || undefined,
            is_active: isActive.value !== '' && isActive.value !== 'all' ? isActive.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            reset: ['languages', 'success', 'error', 'filters'],
        },
    );
};

const clearFilters = () => {
    search.value = '';
    isActive.value = '';
    searchFunc();
};

const clearFilter = (key) => {
    if (key === 'search') search.value = '';
    if (key === 'is_active') isActive.value = '';
    searchFunc();
};

const getFilterLabel = (key, value) => {
    if (key === 'search') return value;
    if (key === 'is_active') return value === '1' ? t('active') : t('inactive');
    return value;
};

const getFilterKeyLabel = (key) => {
    if (key === 'is_active') return t('status');
    if (key === 'search') return t('search');
    return t(key);
};
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6 pb-7">
        <div class="flex w-full flex-col gap-10">
            <div class="flex w-full items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ t('languages') }}
                </h1>
            </div>
        </div>

        <div class="flex flex-col items-start justify-between gap-4">
            <form @submit.prevent="searchFunc" class="flex w-full flex-wrap items-center gap-4">
                <div class="relative min-w-[200px] flex-1">
                    <Input v-model="search" :placeholder="t('search')" class="ps-10 w-full bg-primary/3 shadow-none!" />
                    <SearchIcon class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                </div>
                <Button type="submit">
                    {{ t('search') }}
                </Button>
            </form>

            <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                <div>
                    <Select v-model="isActive" @update:modelValue="searchFunc">
                        <SelectTrigger class="bg-primary/3">
                            <SelectValue :placeholder="t('status')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_statuses') }}</SelectItem>
                            <SelectItem value="1">{{ t('active') }}</SelectItem>
                            <SelectItem value="0">{{ t('inactive') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div
                v-if="
                    Object.keys(filters).some(
                        (k) => filters[k] !== undefined && filters[k] !== null && filters[k] !== '' && filters[k] !== 'all' && k !== 'page',
                    )
                "
                class="mt-2 flex flex-wrap gap-2"
            >
                <template v-for="(value, key) in filters" :key="key">
                    <Button
                        v-if="value !== undefined && value !== null && value !== '' && value !== 'all' && key !== 'page'"
                        variant="secondary"
                        size="sm"
                        class="group h-8 rounded-full bg-primary/10 px-3 text-xs text-primary hover:bg-primary/20"
                        @click="clearFilter(key)"
                    >
                        <span class="me-1 font-medium">{{ getFilterKeyLabel(key) }}:</span>
                        {{ getFilterLabel(key, value) }}
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
