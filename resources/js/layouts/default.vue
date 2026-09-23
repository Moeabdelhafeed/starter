<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import CornerToolbar from '@/components/Shared/CornerToolbar.vue';
import Navbar from '@/components/Shared/Navbar.vue';
import CommandPalette from '@/components/ui/command-palette/CommandPalette.vue';
import Toaster from '@/components/ui/toast/Toaster.vue';
import { useCommandPaletteShortcut } from '@/composables/useCommandPalette';
import { useDeviceRevocation } from '@/composables/useDeviceRevocation';

useDeviceRevocation();

// Binds Cmd/Ctrl+K once for the whole app.
useCommandPaletteShortcut();

const page = usePage();
const { locale } = useI18n();

onMounted(() => {
    document.body.setAttribute('dir', page.props.locale.dir);
    locale.value = page.props.locale.code;
});

watch(
    () => page.props.locale,
    (value) => {
        if (!value) return;
        document.body.setAttribute('dir', value.dir);
        locale.value = value.code;
    },
);
</script>

<template>
    <div id="main_div" class="font-[Cairo]" :dir="page.props.locale.dir">
        <a
            href="#main-content"
            class="sr-only z-[100] rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus:not-sr-only focus:absolute focus:start-4 focus:top-4"
        >
            {{ $t('skip_to_content') }}
        </a>

        <Navbar v-if="page.props.auth.user" />

        <CornerToolbar v-if="page.props.auth.user" />

        <main id="main-content">
            <slot />
        </main>

        <CommandPalette />

        <Toaster />
    </div>
</template>
