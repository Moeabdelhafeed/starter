<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, CheckCircle, Info, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useToast, type Toast, type ToastVariant } from '@/composables/useToast';

const page = usePage();
const { t } = useI18n();
const { toasts, dismiss, toast } = useToast();

const icons = {
    success: CheckCircle,
    error: AlertCircle,
    warning: AlertTriangle,
    info: Info,
} satisfies Record<ToastVariant, unknown>;

/** Solid coloured bars, matching the toast this project has always used. */
const accents: Record<ToastVariant, string> = {
    success: 'bg-emerald-600 text-white',
    error: 'bg-red-600 text-white',
    warning: 'bg-amber-600 text-white',
    info: 'bg-sky-600 text-white',
};

// --- auto-dismiss, pausable on hover/focus -------------------------------
const timers = new Map<number, { handle: number; expiresAt: number; remaining: number }>();

const start = (item: Toast, ms = item.duration) => {
    if (!item.duration) return;
    const handle = window.setTimeout(() => {
        timers.delete(item.id);
        dismiss(item.id);
    }, ms);
    timers.set(item.id, { handle, expiresAt: Date.now() + ms, remaining: ms });
};

const pause = (id: number) => {
    const timer = timers.get(id);
    if (!timer) return;
    window.clearTimeout(timer.handle);
    timer.remaining = Math.max(0, timer.expiresAt - Date.now());
};

const resume = (id: number) => {
    const timer = timers.get(id);
    const item = toasts.value.find((entry) => entry.id === id);
    if (!timer || !item) return;
    start(item, timer.remaining);
};

const close = (id: number) => {
    const timer = timers.get(id);
    if (timer) window.clearTimeout(timer.handle);
    timers.delete(id);
    dismiss(id);
};

watch(
    toasts,
    (list) => {
        list.forEach((item) => {
            if (!timers.has(item.id)) start(item);
        });
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(() => {
    timers.forEach((timer) => window.clearTimeout(timer.handle));
    timers.clear();
});

// --- Inertia flash messages ---------------------------------------------
watch(
    () => page.props.success as string | null,
    (message) => {
        if (message) toast(message, 'success');
    },
    { immediate: true },
);

watch(
    () => page.props.error as string | null,
    (message) => {
        if (message) toast(message, 'error');
    },
    { immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed end-6 top-6 z-[100] flex flex-col items-end gap-2"
            aria-live="polite"
            aria-atomic="false"
        >
            <TransitionGroup
                enter-active-class="transition ease-out duration-300 motion-reduce:transition-none"
                enter-from-class="opacity-0 translate-y-2 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition ease-in duration-200 motion-reduce:transition-none"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
                move-class="transition-transform duration-200 motion-reduce:transition-none"
            >
                <div
                    v-for="item in toasts"
                    :key="item.id"
                    role="status"
                    class="pointer-events-auto flex w-[min(24rem,calc(100vw-3rem))] items-center justify-between gap-3 rounded-lg px-4 py-3"
                    :class="accents[item.variant]"
                    @mouseenter="pause(item.id)"
                    @mouseleave="resume(item.id)"
                    @focusin="pause(item.id)"
                    @focusout="resume(item.id)"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <component :is="icons[item.variant]" class="size-5 shrink-0" aria-hidden="true" />
                        <p class="min-w-0 text-sm font-medium break-words">{{ item.message }}</p>
                    </span>
                    <button
                        type="button"
                        :aria-label="t('close')"
                        class="shrink-0 rounded-full p-1 text-white/80 transition-colors hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none"
                        @click="close(item.id)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
