<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';
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
    <!-- as-child: an <a> wrapping a <button> is invalid HTML and a double tab stop. -->
    <Button v-if="visible" as-child variant="outline" class="gap-2">
        <a :href="url">
            <Download class="size-4" />
            {{ t('export_csv') }}
        </a>
    </Button>
</template>
