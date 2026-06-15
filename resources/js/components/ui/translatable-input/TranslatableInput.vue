<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';

interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
}

const props = defineProps<{
    modelValue: Record<string, string>;
    languages: Language[];
    label?: string;
    required?: boolean;
    placeholder?: string;
    error?: string;
}>();

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
        <div class="space-y-2">
            <div v-for="lang in languages" :key="lang.code" class="flex items-center gap-2">
                <span class="w-12 shrink-0 text-xs font-medium text-muted-foreground uppercase">
                    {{ lang.code }}
                </span>
                <Input
                    :model-value="modelValue[lang.code] || ''"
                    @update:model-value="updateValue(lang.code, $event as string)"
                    :placeholder="placeholder ? `${placeholder} (${lang.native_name})` : lang.native_name"
                    :dir="lang.direction"
                    class="flex-1"
                />
            </div>
        </div>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
