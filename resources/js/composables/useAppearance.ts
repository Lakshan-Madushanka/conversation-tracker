import { ref } from 'vue';

export type Appearance = 'light' | 'dark';

const STORAGE_KEY = 'appearance';

function readStoredAppearance(): Appearance | null {
    const stored = window.localStorage.getItem(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' ? stored : null;
}

function systemAppearance(): Appearance {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyAppearance(appearance: Appearance): void {
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
        window.localStorage.setItem(STORAGE_KEY, value);
        applyAppearance(value);
    }

    function toggleAppearance(): void {
        setAppearance(appearance.value === 'dark' ? 'light' : 'dark');
    }

    return { appearance, setAppearance, toggleAppearance };
}
