<script setup lang="ts">
import { ref, watch } from 'vue';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';

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

const activeTab = ref(props.languages[0]?.code || 'en');

const updateValue = (locale: string, value: string) => {
    emit('update:modelValue', {
        ...props.modelValue,
        [locale]: value,
    });
};

const getLanguageDirection = (code: string): 'ltr' | 'rtl' => {
    const lang = props.languages.find((l: Language) => l.code === code);
    return lang?.direction || 'ltr';
};

watch(() => props.languages, (newLanguages) => {
    if (newLanguages.length > 0 && !newLanguages.find(l => l.code === activeTab.value)) {
        activeTab.value = newLanguages[0].code;
    }
}, { immediate: true });
</script>

<template>
    <div class="space-y-3">
        <label v-if="label" class="block text-sm font-medium text-foreground">
            {{ label }} <span v-if="required" class="text-red-500">*</span>
        </label>

        <!-- Language Tabs -->
        <div class="flex gap-1 border-b border-border">
            <button
                v-for="lang in languages"
                :key="lang.code"
                type="button"
                @click="activeTab = lang.code"
                :class="[
                    'px-4 py-2 text-sm font-medium transition-colors rounded-t-md -mb-px',
                    activeTab === lang.code
                        ? 'bg-card border border-b-card border-border text-foreground'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
                ]"
            >
                {{ lang.code.toUpperCase() }} - {{ lang.native_name }}
            </button>
        </div>

        <!-- Editor for active language -->
        <div v-for="lang in languages" :key="lang.code" v-show="activeTab === lang.code">
            <RichTextEditor
                :model-value="modelValue[lang.code] || ''"
                @update:model-value="updateValue(lang.code, $event)"
                :placeholder="placeholder"
                :direction="getLanguageDirection(lang.code)"
            />
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
