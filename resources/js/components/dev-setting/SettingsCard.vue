<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-3xl border bg-card p-6">
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
