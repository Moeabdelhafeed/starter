<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        /**
         * Search anchor. Every card carries one and it must match its entry in
         * `settings-index.ts` — that file is what the panel's search box searches, and a
         * result whose anchor is absent from the DOM scrolls nowhere.
         */
        anchor?: string;
    }>(),
    {
        title: '',
        description: '',
        anchor: '',
    },
);
</script>

<template>
    <div :id="anchor ? `setting-${anchor}` : undefined" class="scroll-mt-24 rounded-3xl border bg-card p-6 transition-shadow">
        <div v-if="title || $slots.icon" class="mb-6 flex items-center gap-3">
            <slot name="icon" />
            <div>
                <h2 class="text-lg font-semibold text-foreground">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <div class="ms-auto">
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </div>
</template>
