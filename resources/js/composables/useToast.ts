import { ref } from 'vue';

export type ToastVariant = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: number;
    message: string;
    variant: ToastVariant;
    /** ms before auto-dismiss. 0 keeps the toast until dismissed. */
    duration: number;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

/**
 * Global toast queue. `<Toaster />` (mounted once in the layout) renders it.
 * Any component can push: `const { success } = useToast(); success('Saved')`.
 */
export function useToast() {
    const push = (message: string, variant: ToastVariant = 'info', duration = 4000): number => {
        const id = ++nextId;
        toasts.value.push({ id, message, variant, duration });
        return id;
    };

    const dismiss = (id: number) => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    };

    return {
        toasts,
        toast: push,
        dismiss,
        clear: () => {
            toasts.value = [];
        },
        success: (message: string, duration?: number) => push(message, 'success', duration),
        error: (message: string, duration?: number) => push(message, 'error', duration),
        warning: (message: string, duration?: number) => push(message, 'warning', duration),
        info: (message: string, duration?: number) => push(message, 'info', duration),
    };
}
