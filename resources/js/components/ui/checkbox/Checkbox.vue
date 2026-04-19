<script setup>
import { Check } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [Boolean, Array],
        default: false,
    },
    value: {
        type: [String, Number, Object],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const toggle = () => {
    if (props.disabled) return;

    if (Array.isArray(props.modelValue)) {
        const newValue = [...props.modelValue];
        if (newValue.includes(props.value)) {
            newValue.splice(newValue.indexOf(props.value), 1);
        } else {
            newValue.push(props.value);
        }
        emit('update:modelValue', newValue);
    } else {
        emit('update:modelValue', !props.modelValue);
    }
};

const isChecked = () => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.includes(props.value);
    }
    return props.modelValue;
};
</script>

<template>
    <div
        @click="toggle"
        class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-md border transition-all duration-200"
        :class="[
            isChecked()
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-card hover:border-primary',
            disabled ? 'opacity-60 cursor-not-allowed' : '',
        ]"
    >
        <Check v-if="isChecked()" class="h-3.5 w-3.5 stroke-[3]" />
    </div>
</template>
