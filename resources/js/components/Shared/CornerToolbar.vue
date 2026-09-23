<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import TimezonePicker from '@/components/Shared/TimezonePicker.vue';
import { useCommandPalette } from '@/composables/useCommandPalette';

/**
 * The only persistent chrome in this layout.
 *
 * The sidebar is a drawer that stays hidden until you reach for it, so the two
 * things an admin needs from anywhere — search and the display timezone — live
 * here instead. One fixed row, so they can never land on top of each other.
 */
const { t } = useI18n();
const { open: openCommandPalette } = useCommandPalette();

/**
 * The palette's shortcut, written the way each platform writes it: Apple stacks
 * the glyphs (⌘K), everyone else joins them (Ctrl+K). Resolved after mount so
 * the server and the browser render the same hint.
 */
const shortcutLabel = ref('Ctrl+K');

onMounted(() => {
    if (/Mac|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        shortcutLabel.value = '⌘K';
    }
});
</script>

<template>
    <div class="fixed end-4 top-4 z-40 flex items-center gap-2">
        <button
            type="button"
            :aria-label="t('search_everything')"
            :title="t('search_everything') + ' (' + shortcutLabel + ')'"
            class="flex h-10 items-center gap-2 rounded-full border border-border bg-card p-2 text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:gap-3 sm:ps-3 sm:pe-2"
            @click="openCommandPalette"
        >
            <Search class="size-5 shrink-0 sm:size-4" aria-hidden="true" />

            <!-- Reads as the field it opens, rather than a bare shortcut. -->
            <span class="hidden text-sm sm:inline">{{ t('search_placeholder') }}</span>

            <kbd class="hidden shrink-0 rounded-md border border-border bg-muted px-1.5 py-0.5 text-[10px] leading-none font-medium sm:inline">
                {{ shortcutLabel }}
            </kbd>
        </button>

        <TimezonePicker />
    </div>
</template>
