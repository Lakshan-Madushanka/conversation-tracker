import { ref } from 'vue';

export type Appearance = 'light' | 'dark';

const STORAGE_KEY = 'appearance';

function readStoredAppearance(): Appearance | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : null;
    } catch {
        // Storage can be blocked (private windows, embedded pages, strict
        // settings). The system preference is a fine fallback.
        return null;
    }
}

function storeAppearance(appearance: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, appearance);
    } catch {
        // The choice still applies to this page even when it cannot be kept.
    }
}

function systemAppearance(): Appearance {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyAppearance(appearance: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;

    root.classList.toggle('dark', appearance === 'dark');
    root.style.colorScheme = appearance;
}

const appearance = ref<Appearance>(
    readStoredAppearance() ?? systemAppearance(),
);

/**
 * Single source of truth for the app's light / dark appearance.
 *
 * The value is shared across every component so the sidebar toggle, the
 * bottom-sheet menu and the settings page always stay in sync.
 */
export function useAppearance() {
    function setAppearance(value: Appearance): void {
        appearance.value = value;
        storeAppearance(value);
        applyAppearance(value);
    }

    function toggleAppearance(): void {
        setAppearance(appearance.value === 'dark' ? 'light' : 'dark');
    }

    return { appearance, setAppearance, toggleAppearance };
}
