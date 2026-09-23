<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

/**
 * The DevSettings search field. Defined once and placed inside both navs — the sidebar
 * on `lg+` and the bottom pill below it — so searching starts from the same place the
 * sections are listed, rather than from a separate bar above the content.
 */
const { t } = useI18n();

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <div class="relative">
        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
            v-model="model"
            type="search"
            :placeholder="t('search_settings')"
            :aria-label="t('search_settings')"
            class="h-9 w-full rounded-lg border bg-background ps-9 pe-9 text-sm text-foreground placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            autocomplete="off"
            @keydown.esc="model = ''"
            @keydown.enter="emit('submit')"
        />
        <button
            v-if="model.trim() !== ''"
            type="button"
            :aria-label="t('clear')"
            class="absolute end-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            @click="model = ''"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
