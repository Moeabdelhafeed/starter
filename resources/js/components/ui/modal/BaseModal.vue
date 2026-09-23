<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';

/**
 * The shared modal shell. Teleport + custom transitions on purpose — this
 * project never uses Reka/Radix Dialog. Handles focus trapping, focus restore,
 * Escape, body scroll lock and the mobile bottom-sheet treatment so no caller
 * has to reimplement any of it.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        closeOnBackdrop?: boolean;
        closeOnEscape?: boolean;
        hideClose?: boolean;
        /**
         * Where the panel sits. 'top' is for surfaces the user types into
         * immediately (the command palette): anchored near the top on a desktop
         * viewport, and a **full-screen sheet below `sm`**.
         *
         * The sheet is not decoration. A floating card 12vh down the screen
         * loses most of its height to the software keyboard the moment the
         * field takes focus, so a phone showed the input and about two results.
         * Filling the screen puts the field at the top and gives every
         * remaining pixel to the list, which is what a phone's own search does.
         */
        align?: 'center' | 'top';
        /** Blocks Escape/backdrop/close-button while a request is in flight. */
        busy?: boolean;
        class?: string;
    }>(),
    {
        size: 'sm',
        closeOnBackdrop: true,
        closeOnEscape: true,
        hideClose: false,
        align: 'center',
        busy: false,
    },
);

const emit = defineEmits<{ (e: 'close'): void }>();

const { t } = useI18n();

const uid = useId();
const titleId = computed(() => `modal-title-${uid}`);
const panel = ref<HTMLElement | null>(null);

const sizeClass = {
    sm: 'sm:max-w-md',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
} as const;

const FOCUSABLE =
    'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';

const focusables = (): HTMLElement[] =>
    panel.value ? Array.from(panel.value.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((el) => el.offsetParent !== null) : [];

const requestClose = () => {
    if (props.busy) return;
    emit('close');
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.closeOnEscape) {
        event.stopPropagation();
        requestClose();
        return;
    }

    if (event.key !== 'Tab') return;

    const items = focusables();
    if (items.length === 0) {
        event.preventDefault();
        panel.value?.focus();
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || active === panel.value)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
};

// Module-level count so stacked modals don't unlock the body too early.
let locked = false;

const lockScroll = () => {
    if (locked) return;
    openModals += 1;
    locked = true;
    document.body.style.overflow = 'hidden';
};

const unlockScroll = () => {
    if (!locked) return;
    locked = false;
    openModals = Math.max(0, openModals - 1);
    if (openModals === 0) document.body.style.overflow = '';
};

let restoreFocusTo: HTMLElement | null = null;

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            restoreFocusTo = document.activeElement as HTMLElement | null;
            lockScroll();
            await nextTick();
            (focusables()[0] ?? panel.value)?.focus();
        } else {
            unlockScroll();
            restoreFocusTo?.focus?.();
            restoreFocusTo = null;
        }
    },
    { immediate: true },
);

onBeforeUnmount(unlockScroll);
</script>

<script lang="ts">
let openModals = 0;
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                :class="[
                    'fixed inset-0 z-50 flex justify-center overflow-y-auto',
                    align === 'top' ? 'items-stretch sm:items-start sm:p-4 sm:pt-[12vh]' : 'items-center p-4',
                ]"
                @keydown="onKeydown"
            >
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                    @click="closeOnBackdrop && requestClose()"
                ></div>

                <div
                    ref="panel"
                    data-slot="modal-panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="title ? titleId : undefined"
                    tabindex="-1"
                    :class="
                        cn(
                            'relative flex w-full flex-col overflow-hidden rounded-2xl bg-card p-6 text-start text-card-foreground shadow-xl outline-none',
                            align === 'top'
                                ? 'h-[100dvh] rounded-none sm:h-auto sm:max-h-[calc(100dvh-12vh-2rem)] sm:rounded-2xl'
                                : 'max-h-[calc(100dvh-2rem)]',
                            sizeClass[size],
                            props.class,
                        )
                    "
                >
                    <div v-if="title || $slots.header || !hideClose" class="mb-4 flex shrink-0 items-center gap-2">
                        <slot name="icon" />
                        <div class="min-w-0 flex-1">
                            <slot name="header">
                                <h3 v-if="title" :id="titleId" class="text-lg font-semibold text-foreground">{{ title }}</h3>
                            </slot>
                        </div>
                        <button
                            v-if="!hideClose"
                            type="button"
                            :aria-label="t('close')"
                            :disabled="busy"
                            class="shrink-0 rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                            @click="requestClose"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <slot />
                    </div>

                    <div v-if="$slots.footer" class="mt-6 flex shrink-0 justify-end gap-3 empty:hidden">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
