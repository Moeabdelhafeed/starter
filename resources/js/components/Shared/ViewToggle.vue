<script setup lang="ts">
import { LayoutGrid, List } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

withDefaults(defineProps<{ modelValue?: string }>(), { modelValue: 'table' });

const emit = defineEmits<{ (e: 'update:modelValue', value: 'table' | 'grid'): void }>();
</script>

<template>
    <div class="inline-flex items-center rounded-lg border bg-card p-1" role="group" :aria-label="t('view_mode')">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="modelValue === 'table' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="modelValue === 'table'"
            :title="t('table_view')"
            :aria-label="t('table_view')"
            @click="emit('update:modelValue', 'table')"
        >
            <List class="size-4" />
            <span class="hidden sm:inline">{{ t('table_view') }}</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="modelValue === 'grid' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="modelValue === 'grid'"
            :title="t('grid_view')"
            :aria-label="t('grid_view')"
            @click="emit('update:modelValue', 'grid')"
        >
            <LayoutGrid class="size-4" />
            <span class="hidden sm:inline">{{ t('grid_view') }}</span>
        </button>
    </div>
</template>
