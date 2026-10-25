import type { SessionCardData } from '@/types';

/** One page of the Library, as the server sends it. */
export interface LibraryPage {
    sessions: SessionCardData[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
}

/**
 * What the Library remembers while the learner opens a session: every card
 * already loaded, where the next page starts, and how far down the page they
 * had scrolled.
 */
export interface LibrarySnapshot {
    sessions: SessionCardData[];
    nextCursor: string | null;
    hasMore: boolean;
    total: number;
    scrollY: number;
    savedAt: number;
}

const SNAPSHOT_KEY = 'library:snapshot';
const RESTORE_KEY = 'library:restore';

/** A snapshot older than this is stale enough to start the Library fresh. */
const MAX_AGE_MS = 30 * 60 * 1000;

function storage(): Storage | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
}

/**
 * Adds newly loaded cards to the list without ever showing one twice. A card
 * that comes back with fresh counts replaces the older copy in place, so its
 * position does not jump.
 */
export function mergeSessionCards(
    existing: SessionCardData[],
    incoming: SessionCardData[],
): SessionCardData[] {
    const byId = new Map<number, SessionCardData>();

    for (const session of existing) {
        byId.set(session.id, session);
    }

    for (const session of incoming) {
        byId.set(session.id, session);
    }

    return [...byId.values()];
}

/** Whether a value looks like a page the Library can use. */
export function isLibraryPage(value: unknown): value is LibraryPage {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const page = value as Record<string, unknown>;

    return (
        Array.isArray(page.sessions) &&
        (typeof page.next_cursor === 'string' || page.next_cursor === null) &&
        typeof page.has_more === 'boolean' &&
        typeof page.total === 'number'
    );
}

/** Keeps the loaded list for the trip back to the Library. */
export function saveLibrarySnapshot(snapshot: LibrarySnapshot): void {
    try {
        storage()?.setItem(SNAPSHOT_KEY, JSON.stringify(snapshot));
    } catch {
        // A full or blocked storage should never stop the learner browsing.
    }
}

/** Reads the last loaded list, dropping it when it is stale or unreadable. */
export function readLibrarySnapshot(): LibrarySnapshot | null {
    const store = storage();

    if (store === null) {
        return null;
    }

    try {
        const raw = store.getItem(SNAPSHOT_KEY);

        if (raw === null) {
            return null;
        }

        const snapshot: unknown = JSON.parse(raw);

        if (!isSnapshot(snapshot)) {
            store.removeItem(SNAPSHOT_KEY);

            return null;
        }

        if (Date.now() - snapshot.savedAt > MAX_AGE_MS) {
            store.removeItem(SNAPSHOT_KEY);

            return null;
        }

        return snapshot;
    } catch {
        store.removeItem(SNAPSHOT_KEY);

        return null;
    }
}

/** Forgets the loaded list, so the next visit starts at the top. */
export function clearLibrarySnapshot(): void {
    storage()?.removeItem(SNAPSHOT_KEY);
}

/** Marks that the next visit to the Library should bring the list back. */
export function markLibraryRestore(): void {
    storage()?.setItem(RESTORE_KEY, '1');
}

/**
 * Says whether the Library should restore its list now. The marker is used
 * once, so a later visit through the main navigation starts fresh.
 */
export function consumeLibraryRestore(): boolean {
    const store = storage();

    if (store === null) {
        return false;
    }

    const wanted = store.getItem(RESTORE_KEY) === '1';

    store.removeItem(RESTORE_KEY);

    return wanted;
}

function isSnapshot(value: unknown): value is LibrarySnapshot {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const snapshot = value as Record<string, unknown>;

    return (
        Array.isArray(snapshot.sessions) &&
        (typeof snapshot.nextCursor === 'string' ||
            snapshot.nextCursor === null) &&
        typeof snapshot.hasMore === 'boolean' &&
        typeof snapshot.total === 'number' &&
        typeof snapshot.scrollY === 'number' &&
        typeof snapshot.savedAt === 'number'
    );
}
