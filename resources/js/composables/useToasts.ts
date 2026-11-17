import { ref } from 'vue';
import { ApiError } from '@/api/client';

export interface ToastMessage {
    id: number;
    text: string;
    kind: 'success' | 'error';
}

const toasts = ref<ToastMessage[]>([]);
let nextId = 1;

/**
 * Short notifications shown at the edge of the screen.
 */
export function useToasts() {
    function push(text: string, kind: ToastMessage['kind']): void {
        toasts.value.push({ id: nextId++, text, kind });
    }

    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    /**
     * Show the message of a failed request, preferring the first field
     * message of a validation failure.
     */
    function showError(error: unknown): void {
        if (error instanceof ApiError) {
            push(Object.values(error.errors)[0]?.[0] ?? error.message, 'error');
        } else if (error instanceof Error) {
            push(error.message, 'error');
        }
    }

    return {
        toasts,
        dismiss,
        showError,
        showSuccess: (text: string) => push(text, 'success'),
    };
}
