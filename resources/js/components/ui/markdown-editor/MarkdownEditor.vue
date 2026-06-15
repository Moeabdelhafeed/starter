<script setup lang="ts">
import { MdEditor } from 'md-editor-v3';
import 'md-editor-v3/lib/style.css';

const props = withDefaults(defineProps<{
    modelValue: string;
    placeholder?: string;
    language?: 'en-US' | 'zh-CN';
    theme?: 'light' | 'dark';
    previewTheme?: 'default' | 'github' | 'vuepress' | 'mk-cute' | 'smart-blue' | 'cyanosis';
}>(), {
    modelValue: '',
    placeholder: '',
    language: 'en-US',
    theme: 'light',
    previewTheme: 'github',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const handleChange = (value: string) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <MdEditor
        :model-value="modelValue"
        @change="handleChange"
        :language="language"
        :theme="theme"
        :preview-theme="previewTheme"
        :placeholder="placeholder"
        class="rounded-md border border-input"
    />
</template>

<style>
.md-editor {
    --md-bk-color: var(--color-background);
    --md-border-color: var(--color-border);
}

.md-editor-dark {
    --md-bk-color: var(--color-card);
}
</style>
