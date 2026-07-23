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
    groups: Array,
    subGroups: Array,
});

const search = ref(props.filters?.search || '');
const group = ref(props.filters?.group || 'all');
const subGroup = ref(props.filters?.sub_group || 'all');

const getGroupLabel = (value) => {
    const labels = {
        all: t('all_groups'),
        app: t('app_group'),
        web: t('web_group'),
    };
    return labels[value] || value;
};

const getSubGroupLabel = (value) => (value === 'all' ? t('all_sub_groups') : value);

const searchFunc = () => {
    router.get(
        route('media'),
        {
            search: search.value || undefined,
            group: group.value !== 'all' ? group.value : undefined,
            sub_group: subGroup.value !== 'all' ? subGroup.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            reset: ['items', 'success', 'error', 'filters'],
        },
    );
};

const onGroupChange = (value) => {
    group.value = value;
    searchFunc();
};

const onSubGroupChange = (value) => {
    subGroup.value = value;
    searchFunc();
};

const clearFilters = () => {
    search.value = '';
    group.value = 'all';
    subGroup.value = 'all';
    searchFunc();
};

const clearFilter = (key) => {
    if (key === 'search') search.value = '';
    if (key === 'group') group.value = 'all';
    if (key === 'sub_group') subGroup.value = 'all';
    searchFunc();
};

const getFilterLabel = (key, value) => {
    if (key === 'group') return getGroupLabel(value);
    return value;
};

const getFilterKeyLabel = (key) => {
    if (key === 'search') return t('search');
    if (key === 'group') return t('group');
    if (key === 'sub_group') return t('sub_group');
    return t(key);
};

const hasActiveFilters = () => {
    return (
        props.filters?.search ||
        (props.filters?.group && props.filters?.group !== 'all') ||
        (props.filters?.sub_group && props.filters?.sub_group !== 'all')
    );
};
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6 pb-7">
        <h1 class="text-xl font-bold tracking-tight text-foreground">
            {{ t('dynamic_storage') }}
        </h1>

        <form @submit.prevent="searchFunc" class="flex flex-wrap items-center gap-4">
            <div class="relative min-w-[200px] flex-1">
                <Input v-model="search" :placeholder="t('search')" class="w-full bg-primary/2 shadow-none!" />
            </div>

            <Select :model-value="group" @update:model-value="onGroupChange">
                <SelectTrigger class="w-[180px]">
                    <SelectValue :placeholder="t('all_groups')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="g in groups" :key="g" :value="g">
                        {{ getGroupLabel(g) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select :model-value="subGroup" @update:model-value="onSubGroupChange">
                <SelectTrigger class="w-[180px]">
                    <SelectValue :placeholder="t('all_sub_groups')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="s in subGroups" :key="s" :value="s">
                        {{ getSubGroupLabel(s) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button type="submit">
                <SearchIcon class="me-2 h-4 w-4" />
                {{ t('search') }}
            </Button>
        </form>

        <div v-if="hasActiveFilters()" class="mt-2 flex flex-wrap gap-2">
            <Button
                v-if="filters.search"
                variant="secondary"
                size="sm"
                class="group h-8 rounded-full bg-primary/10 px-3 text-xs text-primary hover:bg-primary/20"
                @click="clearFilter('search')"
            >
                <span class="me-1 font-medium">{{ getFilterKeyLabel('search') }}:</span>
                {{ getFilterLabel('search', filters.search) }}
                <XIcon class="ms-1 h-3 w-3 transition-transform group-hover:scale-110" />
            </Button>

            <Button
                v-if="filters.group && filters.group !== 'all'"
                variant="secondary"
                size="sm"
                class="group h-8 rounded-full bg-primary/10 px-3 text-xs text-primary hover:bg-primary/20"
                @click="clearFilter('group')"
            >
                <span class="me-1 font-medium">{{ getFilterKeyLabel('group') }}:</span>
                {{ getFilterLabel('group', filters.group) }}
                <XIcon class="ms-1 h-3 w-3 transition-transform group-hover:scale-110" />
            </Button>

            <Button
                v-if="filters.sub_group && filters.sub_group !== 'all'"
                variant="secondary"
                size="sm"
                class="group h-8 rounded-full bg-primary/10 px-3 text-xs text-primary hover:bg-primary/20"
                @click="clearFilter('sub_group')"
            >
                <span class="me-1 font-medium">{{ getFilterKeyLabel('sub_group') }}:</span>
                {{ getFilterLabel('sub_group', filters.sub_group) }}
                <XIcon class="ms-1 h-3 w-3 transition-transform group-hover:scale-110" />
            </Button>

            <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground hover:text-red-500" @click="clearFilters">
                {{ t('clear_filters') }}
            </Button>
        </div>
    </div>
</template>
