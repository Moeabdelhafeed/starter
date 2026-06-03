<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { SearchIcon, XIcon } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';

const { t } = useI18n();

const props = defineProps({
    filters: Object,
});

const search = ref(props.filters?.search || '');

const searchFunc = () => {
    router.get(
        route('notification_templates'),
        { search: search.value || undefined },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            reset: ['templates', 'success', 'error', 'filters'],
        },
    );
};

const clearFilter = (key) => {
    if (key === 'search') search.value = '';
    searchFunc();
};

const clearFilters = () => {
    search.value = '';
    searchFunc();
};
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6 pb-7">
        <div class="flex w-full flex-col gap-10">
            <div class="flex w-full items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-foreground">{{ t('notification_templates') }}</h1>
            </div>
        </div>

        <div class="flex flex-col items-start justify-between gap-4">
            <form @submit.prevent="searchFunc" class="flex w-full flex-wrap items-center gap-4">
                <div class="relative min-w-[200px] flex-1">
                    <Input v-model="search" :placeholder="t('search')" class="w-full bg-primary/2 shadow-none!" />
                </div>
                <Button type="submit">
                    <SearchIcon class="me-2 h-4 w-4" />
                    {{ t('search') }}
                </Button>
            </form>

            <div
                v-if="Object.keys(filters).some((k) => filters[k] && filters[k] !== 'all' && k !== 'page')"
                class="mt-2 flex flex-wrap gap-2"
            >
                <template v-for="(value, key) in filters" :key="key">
                    <Button
                        v-if="value && value !== 'all' && key !== 'page'"
                        variant="secondary"
                        size="sm"
                        class="group h-8 rounded-full bg-primary/10 px-3 text-xs text-primary hover:bg-primary/20"
                        @click="clearFilter(key)"
                    >
                        <span class="me-1 font-medium">{{ t(key) }}:</span>
                        {{ value }}
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
