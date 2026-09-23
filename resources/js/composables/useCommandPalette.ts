import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Open/close state for the global command palette, plus the keyboard shortcut
 * that summons it.
 *
 * Module-level state, so the trigger in the top bar and the palette mounted in
 * the layout share one source without prop drilling.
 */
const isOpen = ref(false);

/** Cmd on Apple hardware, Ctrl everywhere else — decided at press time. */
const isShortcut = (event: KeyboardEvent): boolean => event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey);

export function useCommandPalette() {
    const open = () => {
        isOpen.value = true;
    };

    const close = () => {
        isOpen.value = false;
    };

    return { isOpen, open, close };
}

/**
 * Binds the global shortcut. Call once, from the layout — calling it per
 * component would stack one listener per mounted instance.
 */
export function useCommandPaletteShortcut() {
    const { isOpen, open } = useCommandPalette();

    const onKeydown = (event: KeyboardEvent) => {
        if (!isShortcut(event)) return;

        // Deliberately fires from inside inputs too: Cmd+K is how every palette
        // is reached mid-typing. Once it is open, its own input owns the keys.
        if (isOpen.value) return;

        event.preventDefault();
        open();
    };

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}
