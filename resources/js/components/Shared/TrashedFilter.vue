<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Archive } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const options = [
    { value: 'none', label: 'active_only' },
    { value: 'with', label: 'with_trashed' },
    { value: 'only', label: 'trashed_only' },
];

// Convert empty string to 'none' for the Select component
const selectValue = computed(() => props.modelValue || 'none');

const handleChange = (value) => {
    // Convert 'none' back to empty string for the parent
    emit('update:modelValue', value === 'none' ? '' : value);
};
</script>

<template>
    <Select :modelValue="selectValue" @update:modelValue="handleChange">
        <SelectTrigger class="w-[180px]">
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
