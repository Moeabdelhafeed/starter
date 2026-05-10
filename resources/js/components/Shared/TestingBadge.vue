<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { TestTube } from 'lucide-vue-next';

const page = usePage();
const isAuthed = computed(() => Boolean(page.props.auth?.user));
const isTesting = computed(() => Boolean(page.props.is_testing) && isAuthed.value);
</script>

<template>
    <Teleport to="body">
        <div v-if="isTesting"
            class="pointer-events-none fixed bottom-4 end-4 z-[9999] flex items-center gap-2 rounded-lg border border-yellow-400 bg-yellow-400/10 px-3 py-1.5 text-xs font-semibold text-yellow-500 shadow-lg backdrop-blur"
            role="status" aria-label="Testing environment">
            <TestTube class="size-3.5" />
            <span>{{ $t('testing_environment') }}</span>
        </div>
    </Teleport>
</template>
