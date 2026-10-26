import { toast } from 'vue-sonner';

type ToastMessage = {
    severity?: 'success' | 'info' | 'warn' | 'error' | string;
    summary?: string;
    detail?: string;
    life?: number;
};

/**
 * Map the old toast call shape onto vue-sonner.
 */
export function useToast() {
    return {
        add({ severity, summary, detail, life }: ToastMessage) {
            const options = { description: detail, duration: life };

            if (severity === 'error') {
                toast.error(summary ?? '', options);

                return;
            }

            if (severity === 'success') {
                toast.success(summary ?? '', options);

                return;
            }

            toast(summary ?? '', options);
        },
    };
}
