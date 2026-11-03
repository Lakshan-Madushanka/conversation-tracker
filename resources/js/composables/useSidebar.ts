import { ref } from 'vue';

const STORAGE_KEY = 'sidebarCollapsed';

function readStoredCollapsed(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(STORAGE_KEY) === 'true';
    } catch {
        // Storage can be blocked (private windows, embedded pages, strict
        // settings). The expanded sidebar is a fine fallback.
        return false;
    }
}

function storeCollapsed(collapsed: boolean): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, String(collapsed));
    } catch {
        // The choice still applies to this page even when it cannot be kept.
    }
}

const collapsed = ref(readStoredCollapsed());

/**
 * Single source of truth for the sidebar's collapsed state.
 *
 * The value is shared across every component and persisted to localStorage,
 * so the choice survives reloads and new sessions.
 */
export function useSidebar() {
    function setCollapsed(value: boolean): void {
        collapsed.value = value;
        storeCollapsed(value);
    }

    function toggleSidebar(): void {
        setCollapsed(!collapsed.value);
    }

    return { collapsed, setCollapsed, toggleSidebar };
}
