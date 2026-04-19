<script setup lang="ts">
import Textarea from '@/components/ui/textarea/Textarea.vue';

interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
}

const props = withDefaults(defineProps<{
    modelValue: Record<string, string>;
    languages: Language[];
    label?: string;
    required?: boolean;
    placeholder?: string;
    error?: string;
    rows?: number;
}>(), {
    rows: 3,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Record<string, string>): void;
}>();

const updateValue = (locale: string, value: string) => {
    emit('update:modelValue', {
        ...props.modelValue,
        [locale]: value,
    });
};
</script>

<template>
    <div class="space-y-3">
        <label v-if="label" class="block text-sm font-medium text-foreground">
            {{ label }} <span v-if="required" class="text-red-500">*</span>
        </label>
        <div class="space-y-3">
            <div v-for="lang in languages" :key="lang.code" class="space-y-1">
                <span class="text-xs font-medium text-muted-foreground uppercase">
                    {{ lang.code }} - {{ lang.native_name }}
                </span>
                <Textarea
                    :model-value="modelValue[lang.code] || ''"
                    @update:model-value="updateValue(lang.code, $event)"
                    :placeholder="placeholder"
                    :dir="lang.direction"
                    :rows="rows"
                />
            </div>
        </div>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
