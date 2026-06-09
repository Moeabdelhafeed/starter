<script setup>
import { useI18n } from 'vue-i18n';
import { LayoutGrid, List } from 'lucide-vue-next';

const { t } = useI18n();

defineProps({
    modelValue: {
        type: String,
        default: 'table',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="inline-flex items-center rounded-lg border bg-card p-1">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
            :class="modelValue === 'table' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="modelValue === 'table'"
            :title="t('table_view')"
            @click="emit('update:modelValue', 'table')"
        >
            <List class="size-4" />
            <span class="hidden sm:inline">{{ t('table_view') }}</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
            :class="modelValue === 'grid' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="modelValue === 'grid'"
            :title="t('grid_view')"
            @click="emit('update:modelValue', 'grid')"
        >
            <LayoutGrid class="size-4" />
            <span class="hidden sm:inline">{{ t('grid_view') }}</span>
        </button>
    </div>
</template>
