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
    actions: Array,
    subjectTypes: Array,
    causers: Array,
});

const search = ref(props.filters?.search || '');
const action = ref(props.filters?.action || '');
const subjectType = ref(props.filters?.subject_type || '');
const causer = ref(props.filters?.causer || '');

const searchFunc = () => {
    router.get(
        route('activity_logs'),
        {
            search: search.value || undefined,
            action: action.value && action.value !== 'all' ? action.value : undefined,
            subject_type: subjectType.value && subjectType.value !== 'all' ? subjectType.value : undefined,
            causer: causer.value && causer.value !== 'all' ? causer.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            reset: ['logs', 'success', 'error', 'filters'],
        },
    );
};

const clearFilters = () => {
    search.value = '';
    action.value = '';
    subjectType.value = '';
    causer.value = '';
    searchFunc();
};

const clearFilter = (key) => {
    if (key === 'search') search.value = '';
    if (key === 'action') action.value = '';
    if (key === 'subject_type') subjectType.value = '';
    if (key === 'causer') causer.value = '';
    searchFunc();
};

const getFilterLabel = (key, value) => {
    if (key === 'action') return t(value);
    if (key === 'subject_type') {
        const match = props.subjectTypes?.find(s => s.value === value);
        return match ? match.label : value;
    }
    if (key === 'causer') {
        const match = props.causers?.find(c => c.value === value);
        return match ? match.label : value;
    }
    return value;
};

const getFilterKeyLabel = (key) => {
    if (key === 'search') return t('search');
    if (key === 'action') return t('action');
    if (key === 'subject_type') return t('target');
    if (key === 'causer') return t('user');
    return t(key);
};
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6 pb-7">
        <div class="flex w-full flex-col gap-10">
            <div class="flex w-full items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ t('activity_logs') }}
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

            <!-- Advanced Filters -->
            <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                <div>
                    <Select v-model="action" @update:modelValue="searchFunc">
                        <SelectTrigger class="bg-primary/3">
                            <SelectValue :placeholder="t('action')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_actions') }}</SelectItem>
                            <SelectItem v-for="a in actions" :key="a" :value="a">{{ t(a) }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Select v-model="subjectType" @update:modelValue="searchFunc">
                        <SelectTrigger class="bg-primary/3">
                            <SelectValue :placeholder="t('target')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_targets') }}</SelectItem>
                            <SelectItem v-for="st in subjectTypes" :key="st.value" :value="st.value">{{ st.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Select v-model="causer" @update:modelValue="searchFunc">
                        <SelectTrigger class="bg-primary/3">
                            <SelectValue :placeholder="t('user')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('all_users') }}</SelectItem>
                            <SelectItem v-for="c in causers" :key="c.value" :value="c.value">{{ c.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Active Filters Chips -->
            <div
                v-if="
                    Object.keys(filters || {}).some(
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
