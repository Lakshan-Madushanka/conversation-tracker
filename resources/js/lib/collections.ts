import { collectionUrl } from '@/lib/collection-query';
import { show as sessionsShow } from '@/routes/sessions';
import type {
    CollectionAppearance,
    CollectionItem,
    CollectionItemsPage,
    CollectionMistakeAppearance,
    CollectionMistakeItem,
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
    items: CollectionOpenedItem[];
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
    existing: CollectionOpenedItem[],
    incoming: CollectionOpenedItem[],
): CollectionOpenedItem[] {
    const byKey = new Map<string, CollectionOpenedItem>();

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

export function isCollectionMistakeItem(
    value: unknown,
): value is CollectionMistakeItem {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const item = value as Record<string, unknown>;

    return (
        typeof item.key === 'string' &&
        typeof item.title === 'string' &&
        typeof item.correction === 'string' &&
        typeof item.correction_html === 'string' &&
        typeof item.reason === 'string' &&
        (typeof item.latest_session_date === 'string' ||
            item.latest_session_date === null) &&
        typeof item.seen_count === 'number' &&
        typeof item.worth_practicing === 'boolean' &&
        typeof item.needs_attention === 'boolean' &&
        (item.attention_reason === null ||
            typeof item.attention_reason === 'string') &&
        Array.isArray(item.appearances) &&
        item.appearances.every(isMistakeAppearance)
    );
}

export function isCollectionOpenedItem(
    value: unknown,
): value is CollectionOpenedItem {
    return (
        isCollectionItem(value) ||
        isCollectionNoteItem(value) ||
        isCollectionMistakeItem(value)
    );
}

/** Whether the tabs data the page needs looks right. */
function isCollectionTabData(tabs: unknown[]): boolean {
    return tabs.every((tab) => {
        if (typeof tab !== 'object' || tab === null) {
            return false;
        }

        const candidate = tab as Record<string, unknown>;

        return (
            typeof candidate.key === 'string' &&
            typeof candidate.label === 'string' &&
            typeof candidate.count === 'number'
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

/** Whether one place a mistake was saved looks right. */
function isMistakeAppearance(
    value: unknown,
): value is CollectionMistakeAppearance {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const appearance = value as Record<string, unknown>;

    return (
        typeof appearance.session_id === 'number' &&
        typeof appearance.topic === 'string' &&
        typeof appearance.question_position === 'number' &&
        typeof appearance.entry_position === 'number' &&
        typeof appearance.said === 'string' &&
        typeof appearance.correction === 'string' &&
        typeof appearance.correction_html === 'string' &&
        typeof appearance.reason === 'string' &&
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
    return sightingsIn(item.appearances);
}

/**
 * The places a mistake was seen, once per question, the same way words and
 * phrases are counted: a repeated row in the same box counts once.
 */
export function mistakeSeenIn(
    item: CollectionMistakeItem,
): CollectionMistakeAppearance[] {
    return sightingsIn(item.appearances);
}

/**
 * One sighting per question of a list of appearances, whatever their shape.
 */
function sightingsIn<
    T extends { session_id: number; question_position: number },
>(appearances: T[]): T[] {
    const seen = new Set<string>();
    const result: T[] = [];

    for (const appearance of appearances) {
        const key = `${appearance.session_id}:${appearance.question_position}`;

        if (seen.has(key)) {
            continue;
        }

        seen.add(key);
        result.push(appearance);
    }

    return result;
}

/**
 * Every different reason a mistake carried, newest first. The same reason
 * written with different capitals is shown once, and each reason keeps the
 * appearances it came from, so the panel can name its sessions.
 */
export function distinctReasons(
    item: CollectionMistakeItem,
): { reason: string; appearances: CollectionMistakeAppearance[] }[] {
    const groups = new Map<string, CollectionMistakeAppearance[]>();

    for (const appearance of item.appearances) {
        const reason = appearance.reason.trim();

        if (reason === '') {
            continue;
        }

        const key = reason.toLocaleLowerCase();
        const known = groups.get(key);

        if (known === undefined) {
            groups.set(key, [appearance]);
        } else {
            known.push(appearance);
        }
    }

    // The appearances arrive newest first, so each group's first reason is
    // the most recent spelling of it.
    return [...groups.values()].map((appearances) => ({
        reason: appearances[0].reason,
        appearances,
    }));
}

/** How a mistake's seen count reads, with the singular kept correct. */
export function seenLabel(count: number): string {
    return count === 1 ? 'Seen 1 time' : `Seen ${count} times`;
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
