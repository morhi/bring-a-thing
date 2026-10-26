type ConfirmOptions = {
    header?: string;
    message?: string;
    accept?: () => void;
    reject?: () => void;
    [key: string]: unknown;
};

/**
 * Confirm with the native browser dialog until the pages move to AlertDialog.
 */
export function useConfirm() {
    return {
        require({ header, message, accept, reject }: ConfirmOptions) {
            const text = [header, message].filter(Boolean).join('\n\n');

            if (window.confirm(text)) {
                accept?.();

                return;
            }

            reject?.();
        },
    };
}
