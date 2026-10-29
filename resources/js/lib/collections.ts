import { collectionUrl } from '@/lib/collection-query';
import { show as sessionsShow } from '@/routes/sessions';
import type {
    CollectionAppearance,
    CollectionItem,
    CollectionItemsPage,
    CollectionNoteItem,
    CollectionOpenedItem,
    CollectionPage,
    CollectionRow,
    CollectionTabKey,
} from '@/types';

/**
 * The loaded list and the place the learner had reached, kept while they open
 * the session an item came from. Returning restores the same tab, the items
 * already loaded, the scroll position and the opened item.
 */
export interface CollectionSnapshot {
    tab: CollectionTabKey;
    items: (CollectionItem | CollectionNoteItem)[];
    nextCursor: string | null;
    hasMore: boolean;
    total: number;
    openItemKey: string | null;
    scrollY: number;
    savedAt: number;
}

const SNAPSHOT_KEY = 'collections:snapshot';
const RESTORE_KEY = 'collections:restore';

/** A snapshot older than this is stale enough to start the page fresh. */
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
 * Adds newly loaded items without showing one twice. An item that comes back
 * with fresh appearances replaces the older copy in place, so its position
 * does not jump.
 */
export function mergeCollectionItems(
    existing: (CollectionItem | CollectionNoteItem)[],
    incoming: (CollectionItem | CollectionNoteItem)[],
): (CollectionItem | CollectionNoteItem)[] {
    const byKey = new Map<string, CollectionItem | CollectionNoteItem>();

    for (const item of existing) {
        byKey.set(item.key, item);
    }

    for (const item of incoming) {
        byKey.set(item.key, item);
    }

    return [...byKey.values()];
}

/** Whether a value looks like a page the Collections page can use. */
export function isCollectionPage(value: unknown): value is CollectionPage {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const page = value as Record<string, unknown>;

    return (
        Array.isArray(page.items) &&
        page.items.every(isCollectionOpenedItem) &&
        Array.isArray(page.tabs) &&
        typeof page.tab === 'string' &&
        (typeof page.next_cursor === 'string' || page.next_cursor === null) &&
        typeof page.has_more === 'boolean' &&
        typeof page.total === 'number' &&
        typeof page.has_finished_sessions === 'boolean' &&
        isCollectionTabData(page.tabs) &&
        (page.open_item === null || isCollectionOpenedItem(page.open_item)) &&
        typeof page.open_item_missing === 'boolean'
    );
}

/** Whether a value looks like a "Load more" response. */
export function isCollectionItemsPage(
    value: unknown,
): value is CollectionItemsPage {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const page = value as Record<string, unknown>;

    return (
        Array.isArray(page.items) &&
        page.items.every(isCollectionOpenedItem) &&
        (typeof page.next_cursor === 'string' || page.next_cursor === null) &&
        typeof page.has_more === 'boolean' &&
        typeof page.total === 'number'
    );
}

export function isCollectionItem(value: unknown): value is CollectionItem {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const item = value as Record<string, unknown>;

    return (
        typeof item.key === 'string' &&
        typeof item.title === 'string' &&
        typeof item.meaning === 'string' &&
        typeof item.example === 'string' &&
        typeof item.example_html === 'string' &&
        (typeof item.latest_session_date === 'string' ||
            item.latest_session_date === null) &&
        typeof item.seen_count === 'number' &&
        typeof item.needs_attention === 'boolean' &&
        (item.attention_reason === null ||
            typeof item.attention_reason === 'string') &&
        Array.isArray(item.appearances) &&
        item.appearances.every(isAppearance)
    );
}

export function isCollectionNoteItem(
    value: unknown,
): value is CollectionNoteItem {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const item = value as Record<string, unknown>;

    return (
        typeof item.key === 'string' &&
        typeof item.text === 'string' &&
        typeof item.preview === 'string' &&
        typeof item.html === 'string' &&
        (typeof item.latest_session_date === 'string' ||
            item.latest_session_date === null) &&
        typeof item.session_id === 'number' &&
        typeof item.topic === 'string' &&
        (typeof item.session_date === 'string' || item.session_date === null) &&
        typeof item.question_position === 'number' &&
        typeof item.entry_position === 'number' &&
        (item.entry_id === null || typeof item.entry_id === 'string')
    );
}

export function isCollectionOpenedItem(
    value: unknown,
): value is CollectionOpenedItem {
    return isCollectionItem(value) || isCollectionNoteItem(value);
}

/** Whether a value looks like the tabs data the page needs. */
function isCollectionTabData(tabs: unknown[]): boolean {
    return tabs.every((tab) => {
        if (typeof tab !== 'object' || tab === null) {
            return false;
        }

        const candidate = tab as Record<string, unknown>;

        return (
            typeof candidate.key === 'string' &&
            typeof candidate.label === 'string' &&
            (candidate.count === null || typeof candidate.count === 'number') &&
            typeof candidate.built === 'boolean'
        );
    });
}

function isAppearance(value: unknown): value is CollectionAppearance {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const appearance = value as Record<string, unknown>;

    return (
        typeof appearance.session_id === 'number' &&
        typeof appearance.topic === 'string' &&
        typeof appearance.question_position === 'number' &&
        typeof appearance.entry_position === 'number' &&
        typeof appearance.meaning === 'string' &&
        typeof appearance.example === 'string' &&
        typeof appearance.example_html === 'string' &&
        (appearance.session_date === null ||
            typeof appearance.session_date === 'string') &&
        (appearance.entry_id === null ||
            typeof appearance.entry_id === 'string')
    );
}

/** Keeps the loaded list for the trip to the original session. */
export function saveCollectionSnapshot(snapshot: CollectionSnapshot): void {
    try {
        storage()?.setItem(SNAPSHOT_KEY, JSON.stringify(snapshot));
    } catch {
        // A full or blocked storage should never stop the learner browsing.
    }
}

/** Reads the last loaded list, dropping it when it is stale or unreadable. */
export function readCollectionSnapshot(): CollectionSnapshot | null {
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

        return stored;
    } catch {
        store.removeItem(SNAPSHOT_KEY);

        return null;
    }
}

/** Forgets the loaded list, so the next visit starts at the top. */
export function clearCollectionSnapshot(): void {
    storage()?.removeItem(SNAPSHOT_KEY);
}

/** Marks that the next visit to Collections should bring the list back. */
export function markCollectionRestore(): void {
    storage()?.setItem(RESTORE_KEY, '1');
}

/**
 * Says whether Collections should restore its list now. The marker is used
 * once, so a later visit through the main navigation starts fresh.
 */
export function consumeCollectionRestore(): boolean {
    const store = storage();

    if (store === null) {
        return false;
    }

    const wanted = store.getItem(RESTORE_KEY) === '1';

    store.removeItem(RESTORE_KEY);

    return wanted;
}

/**
 * The address that brings the learner back to the collection they came from,
 * including the tab and the panel that was open. Used by the reading page's
 * "Back to Collections" link.
 */
export function collectionReturnUrl(): string {
    const snapshot = readCollectionSnapshot();

    return snapshot === null
        ? collectionUrl('vocabulary')
        : collectionUrl(snapshot.tab, snapshot.openItemKey);
}

/** Whether a session address came from a collection item. */
export function cameFromCollections(url: string): boolean {
    return (
        new URL(url, 'http://localhost').searchParams.get('from') ===
        'collections'
    );
}

/**
 * The address of the original session, opened at the exact row. It reuses the
 * reading page's search-target behaviour, so the row is highlighted, scrolled
 * to and focused, and a deleted or edited row opens the session calmly at the
 * top with an explanation.
 */
export function collectionItemHref(
    row: CollectionRow,
    type: CollectionTabKey = 'vocabulary',
): string {
    const query: Record<string, string> = {
        from: 'collections',
        question: String(row.question_position),
        field: 'entry',
        type: feedbackTypeFor(type),
        position: String(row.entry_position),
    };

    if (row.entry_id !== null && row.entry_id !== '') {
        query.entry = row.entry_id;
    }

    return sessionsShow.url(row.session_id, { query });
}

/** The link back to a note as a row, so one helper serves both panels. */
export function collectionNoteHref(note: CollectionNoteItem): string {
    return collectionItemHref(
        {
            session_id: note.session_id,
            question_position: note.question_position,
            entry_id: note.entry_id,
            entry_position: note.entry_position,
        },
        'grammar',
    );
}

/** The feedback box a collection gathers, as the reading page names it. */
export function feedbackTypeFor(
    tab: CollectionTabKey,
): 'vocabulary' | 'phrase' | 'grammar_note' | 'mistake' {
    return tab === 'phrases'
        ? 'phrase'
        : tab === 'grammar'
          ? 'grammar_note'
          : tab === 'mistakes'
            ? 'mistake'
            : 'vocabulary';
}

/**
 * Every different meaning across an item's appearances, newest first. The same
 * meaning saved in two sessions is shown once.
 */
export function distinctMeanings(item: CollectionItem): CollectionAppearance[] {
    return uniqueBy(item.appearances, (appearance) =>
        appearance.meaning.trim().toLocaleLowerCase(),
    );
}

/** Every different example across an item's appearances, newest first. */
export function distinctExamples(item: CollectionItem): CollectionAppearance[] {
    return uniqueBy(item.appearances, (appearance) => exampleKey(appearance));
}

/** Two identical examples are one example, however they were saved. */
function exampleKey(appearance: CollectionAppearance): string {
    return (
        appearance.example.trim().toLocaleLowerCase() ||
        appearance.example_html.trim().toLocaleLowerCase()
    );
}

/**
 * The sessions the item was seen in, once per question: two rows in the same
 * box are one sighting, while another question or session is another.
 */
export function seenIn(item: CollectionItem): CollectionAppearance[] {
    return uniqueBy(
        item.appearances,
        (appearance) =>
            `${appearance.session_id}:${appearance.question_position}`,
    );
}

function uniqueBy(
    appearances: CollectionAppearance[],
    keyOf: (appearance: CollectionAppearance) => string,
): CollectionAppearance[] {
    const seen = new Set<string>();
    const result: CollectionAppearance[] = [];

    for (const appearance of appearances) {
        const key = keyOf(appearance);

        // An empty meaning or example is not something to show.
        if (key === '') {
            continue;
        }

        if (seen.has(key)) {
            continue;
        }

        seen.add(key);
        result.push(appearance);
    }

    return result;
}

/** The stored shape, validated before anything trusts it. */
function isStoredSnapshot(value: unknown): value is CollectionSnapshot {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const snapshot = value as Record<string, unknown>;

    return (
        typeof snapshot.tab === 'string' &&
        Array.isArray(snapshot.items) &&
        snapshot.items.every(isCollectionOpenedItem) &&
        (typeof snapshot.nextCursor === 'string' ||
            snapshot.nextCursor === null) &&
        typeof snapshot.hasMore === 'boolean' &&
        typeof snapshot.total === 'number' &&
        (typeof snapshot.openItemKey === 'string' ||
            snapshot.openItemKey === null) &&
        typeof snapshot.scrollY === 'number' &&
        typeof snapshot.savedAt === 'number'
    );
}
