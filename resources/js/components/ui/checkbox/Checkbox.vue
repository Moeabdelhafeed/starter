<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

export type CheckboxValue = string | number | boolean | Record<string, unknown> | null;

/**
 * Accessible checkbox.
 *
 * `modelValue` is either a boolean (single) or an array (multi-select, paired
 * with `value`) — both call sites are used across the admin tables, so the API
 * is kept exactly as it was.
 *
 * Rendered as a div with `role="checkbox"` rather than a native input so the
 * checked state can be styled without a pseudo-element; keyboard behaviour
 * (Tab, Space, Enter) and the ARIA state are supplied explicitly.
 */
const props = withDefaults(
    defineProps<{
        modelValue?: boolean | CheckboxValue[];
        value?: CheckboxValue;
        disabled?: boolean;
        /** Accessible name. Required unless an ancestor <label> or aria-labelledby names it. */
        ariaLabel?: string;
    }>(),
    {
        modelValue: false,
        value: null,
        disabled: false,
        ariaLabel: undefined,
    },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: boolean | CheckboxValue[]): void }>();

const isChecked = computed<boolean>(() =>
    Array.isArray(props.modelValue) ? props.modelValue.includes(props.value) : Boolean(props.modelValue),
);

const toggle = (): void => {
    if (props.disabled) return;

    if (Array.isArray(props.modelValue)) {
        const next = [...props.modelValue];
        const index = next.indexOf(props.value);

        if (index === -1) {
            next.push(props.value);
        } else {
            next.splice(index, 1);
        }

        emit('update:modelValue', next);
        return;
    }

    emit('update:modelValue', !props.modelValue);
};
</script>

<template>
    <div
        role="checkbox"
        :tabindex="disabled ? -1 : 0"
        :aria-checked="isChecked"
        :aria-disabled="disabled || undefined"
        :aria-label="ariaLabel"
        class="flex size-5 shrink-0 items-center justify-center rounded-md border transition-all duration-200 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        :class="[
            isChecked ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card',
            disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-primary',
        ]"
        @click="toggle"
        @keydown.space.prevent="toggle"
        @keydown.enter.prevent="toggle"
    >
        <Check v-if="isChecked" class="size-3.5 stroke-[3]" />
    </div>
</template>
