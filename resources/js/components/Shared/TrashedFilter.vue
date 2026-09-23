<script setup lang="ts">
import { Archive } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const { t } = useI18n();

const props = withDefaults(defineProps<{ modelValue?: string }>(), { modelValue: '' });

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const options = [
    { value: 'none', label: 'active_only' },
    { value: 'with', label: 'with_trashed' },
    { value: 'only', label: 'trashed_only' },
];

// Convert empty string to 'none' for the Select component
const selectValue = computed(() => props.modelValue || 'none');

// reka-ui's Select emits `AcceptableValue` (unknown-ish); every SelectItem here
// carries a string value, so narrow rather than trust the emitted type.
const handleChange = (value: unknown) => {
    const next = typeof value === 'string' ? value : '';
    // Convert 'none' back to empty string for the parent
    emit('update:modelValue', next === 'none' ? '' : next);
};
</script>

<template>
    <Select :modelValue="selectValue" @update:modelValue="handleChange">
        <SelectTrigger class="w-full sm:w-[180px]" :aria-label="t('filter_status')">
            <div class="flex items-center gap-2">
                <Archive class="size-4 text-muted-foreground" />
                <SelectValue :placeholder="t('filter_status')" />
            </div>
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="option in options" :key="option.value" :value="option.value">
                {{ t(option.label) }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
