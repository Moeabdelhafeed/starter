<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import { useResizeObserver } from '@vueuse/core';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    /** Accessible name for the scroll region (falls back to a generic label). */
    scrollLabel?: string;
}>();

const { t } = useI18n();

const container = ref<HTMLElement | null>(null);
const table = ref<HTMLElement | null>(null);
const isOverflowing = ref(false);

// A scrollable region needs to be reachable by keyboard, but only while it
// actually scrolls — otherwise it is a dead tab stop on every table.
useResizeObserver([container, table], () => {
    const el = container.value;
    isOverflowing.value = !!el && el.scrollWidth > el.clientWidth + 1;
});
</script>

<template>
    <div
        ref="container"
        data-slot="table-container"
        class="relative w-full overflow-auto rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        :tabindex="isOverflowing ? 0 : undefined"
        :role="isOverflowing ? 'region' : undefined"
        :aria-label="isOverflowing ? (props.scrollLabel ?? t('scrollable_table')) : undefined"
    >
        <table ref="table" data-slot="table" :class="cn('w-full caption-bottom text-sm', props.class)">
            <slot />
        </table>
    </div>
</template>
