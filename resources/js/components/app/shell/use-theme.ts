import { onMounted, readonly, ref } from 'vue';

export type Theme = 'system' | 'light' | 'dark';

export const themes: Theme[] = ['system', 'light', 'dark'];

const STORAGE_KEY = 'bat.theme';

const theme = ref<Theme>('system');
let media: MediaQueryList | null = null;

function readStored(): Theme {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

function writeStored(value: Theme): void {
    try {
        if (value === 'system') {
            window.localStorage.removeItem(STORAGE_KEY);

            return;
        }

        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Blocked storage (private mode, strict settings): the choice then lasts until the next full load.
    }
}

function apply(): void {
    const prefersDark = media?.matches ?? false;
    const isDark =
        theme.value === 'dark' || (theme.value === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', isDark);
}

function initialize(): void {
    if (media) {
        return;
    }

    media = window.matchMedia('(prefers-color-scheme: dark)');
    media.addEventListener('change', apply);
    theme.value = readStored();
    apply();
}

/**
 * Read and switch the color theme: light, dark or following the system.
 *
 * Shared across components, so the header toggle and the mobile menu stay in step.
 */
export function useTheme() {
    onMounted(initialize);

    function setTheme(value: Theme): void {
        theme.value = value;
        writeStored(value);
        apply();
    }

    return { theme: readonly(theme), setTheme };
}
