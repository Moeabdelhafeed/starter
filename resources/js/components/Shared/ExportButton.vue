<script setup lang="ts">
import { computed } from 'vue';
import { Download } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    routeName: string;
    filters?: Record<string, unknown>;
    show?: boolean;
}>();

const { t } = useI18n();

const visible = computed(() => props.show !== false);

const url = computed(() => {
    const cleanFilters: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(props.filters ?? {})) {
        if (value === null || value === undefined || value === '') continue;
        cleanFilters[key] = value as string;
    }
    return route(props.routeName, cleanFilters);
});
</script>

<template>
    <a v-if="visible" :href="url">
        <Button type="button" variant="outline" class="gap-2">
            <Download class="size-4" />
            {{ t('export_csv') }}
        </Button>
    </a>
</template>
