import type { SessionCardData, TopicSummary } from '@/types';
import {
    browserTimezone,
    normalizeLibraryFilters,
    type LibraryFilters,
} from '@/lib/library-filters';

/** One page of the Library, as the server sends it. */
export interface LibraryPage {
    sessions: SessionCardData[];
    next_cursor: string | null;
    has_more: boolean;
    /** How many sessions match the chosen filters. */
    total: number;
    /** How many sessions the Library holds in total. */
    all_total: number;
    filters: LibraryFilters;
    topics: TopicSummary[];
    timezone: string;
}

/**
 * What the Library remembers while the learner opens a session: every card
 * already loaded, where the next page starts, the filters and sort in force,
 * and how far down the page they had scrolled.
 */
export interface LibrarySnapshot {
    sessions: SessionCardData[];
    nextCursor: string | null;
    hasMore: boolean;
    total: number;
    allTotal: number;
    filters: LibraryFilters;
    timezone: string;
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
        typeof page.total === 'number' &&
        typeof page.all_total === 'number' &&
        typeof page.timezone === 'string' &&
        isLibraryFilters(page.filters) &&
        Array.isArray(page.topics)
    );
}

/** Whether a value looks like a filter state this app can use. */
export function isLibraryFilters(value: unknown): value is LibraryFilters {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const filters = value as Record<string, unknown>;

    return (
        Array.isArray(filters.topics) &&
        Array.isArray(filters.types) &&
        typeof filters.date === 'string' &&
        (typeof filters.from === 'string' || filters.from === null) &&
        (typeof filters.to === 'string' || filters.to === null) &&
        typeof filters.status === 'string' &&
        typeof filters.sort === 'string' &&
        (filters.q === undefined || typeof filters.q === 'string')
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

        const stored: unknown = JSON.parse(raw);

        if (!isStoredSnapshot(stored)) {
            store.removeItem(SNAPSHOT_KEY);

            return null;
        }

        if (Date.now() - stored.savedAt > MAX_AGE_MS) {
            store.removeItem(SNAPSHOT_KEY);

            return null;
        }

        // Lists saved before filters existed keep working: the missing pieces
        // simply start from their defaults.
        return {
            sessions: stored.sessions,
            nextCursor: stored.nextCursor,
            hasMore: stored.hasMore,
            total: stored.total,
            allTotal: stored.allTotal ?? stored.total,
            filters: normalizeLibraryFilters(stored.filters),
            timezone: stored.timezone ?? browserTimezone(),
            scrollY: stored.scrollY,
            savedAt: stored.savedAt,
        };
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

/** The stored shape, with the pieces older snapshots did not keep yet. */
interface StoredLibrarySnapshot {
    sessions: SessionCardData[];
    nextCursor: string | null;
    hasMore: boolean;
    total: number;
    scrollY: number;
    savedAt: number;
    allTotal?: number;
    filters?: LibraryFilters;
    timezone?: string;
}

function isStoredSnapshot(value: unknown): value is StoredLibrarySnapshot {
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
        typeof snapshot.savedAt === 'number' &&
        (snapshot.allTotal === undefined ||
            typeof snapshot.allTotal === 'number') &&
        (snapshot.filters === undefined ||
            isLibraryFilters(snapshot.filters)) &&
        (snapshot.timezone === undefined ||
            typeof snapshot.timezone === 'string')
    );
}
