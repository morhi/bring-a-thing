import { useConnectionStatus } from '@laravel/echo-vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { t } from '@/lib/i18n';

/**
 * Track whether live updates are lost (F-21) and confirm when they are back.
 *
 * Echo reports "disconnected" before the first connect, so only a failed
 * connect or a drop after a working connection counts as lost.
 */
export function useConnectionLost() {
    const status = useConnectionStatus();
    const lost = ref(false);
    let hasConnected = false;

    watch(
        status,
        (value) => {
            if (value === 'connected') {
                if (lost.value) {
                    toast.success(t('conn.restored'));
                }

                lost.value = false;
                hasConnected = true;

                return;
            }

            if (value === 'failed' || hasConnected) {
                lost.value = true;
            }
        },
        { immediate: true },
    );

    return { lost };
}
