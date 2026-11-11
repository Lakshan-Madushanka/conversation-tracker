import { collectionUrl } from '@/lib/collection-query';
import { show as notebooksShow } from '@/routes/notebooks';
import { show as sessionsShow } from '@/routes/sessions';
import type {
    CollectionAppearance,
    CollectionFilters,
    CollectionItem,
    CollectionItemsPage,
    CollectionMistakeAppearance,
    CollectionMistakeItem,
    CollectionNoteItem,
    CollectionNotebookOption,
    CollectionNotebookSource,
    CollectionOpenedItem,
    CollectionPage,
    CollectionReviewContext,
    CollectionRow,
    CollectionRowContent,
    CollectionTabKey,
    CollectionTopicOption,
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
    /** The search, filters and order the saved list was showing. */
    filters?: CollectionFilters;
    /** The topic and notebook pickers, counts and empty-state flag. */
    topicOptions?: CollectionTopicOption[];
    notebookOptions?: CollectionNotebookOption[];
    hasSources?: boolean;
    allTotal?: number;
    attentionCount?: number;
    /** The review whose + Add items narrowed the list, when one did. */
    reviewContext?: CollectionReviewContext | null;
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
        typeof page.total === 'number' &&
        (page.tabs === undefined ||
            (Array.isArray(page.tabs) && isCollectionTabData(page.tabs))) &&
        (page.all_total === undefined || typeof page.all_total === 'number') &&
        (page.attention_count === undefined ||
            typeof page.attention_count === 'number') &&
        (page.topic_options === undefined ||
            isCollectionTopicOptions(page.topic_options)) &&
        (page.notebook_options === undefined ||
            isCollectionNotebookOptions(page.notebook_options)) &&
        (page.filters === undefined || isCollectionFilters(page.filters)) &&
        (page.has_finished_sessions === undefined ||
            typeof page.has_finished_sessions === 'boolean') &&
        (page.has_sources === undefined ||
            typeof page.has_sources === 'boolean')
    );
}

/** Whether the notebook options a refresh sends back look right. */
function isCollectionNotebookOptions(value: unknown): boolean {
    return (
        Array.isArray(value) &&
        value.every((notebook) => {
            if (typeof notebook !== 'object' || notebook === null) {
                return false;
            }

            const candidate = notebook as Record<string, unknown>;

            return (
                typeof candidate.id === 'number' &&
                typeof candidate.name === 'string' &&
                typeof candidate.count === 'number'
            );
        })
    );
}

/** Whether the topic options a refresh sends back look right. */
function isCollectionTopicOptions(value: unknown): boolean {
    return (
        Array.isArray(value) &&
        value.every((topic) => {
            if (typeof topic !== 'object' || topic === null) {
                return false;
            }

            const candidate = topic as Record<string, unknown>;

            return (
                typeof candidate.id === 'number' &&
                typeof candidate.name === 'string' &&
                typeof candidate.count === 'number'
            );
        })
    );
}

/** Whether the filters a refresh sends back look right. */
function isCollectionFilters(value: unknown): boolean {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const filters = value as Record<string, unknown>;

    return (
        typeof filters.q === 'string' &&
        typeof filters.starred === 'boolean' &&
        typeof filters.status === 'string' &&
        typeof filters.attention === 'boolean' &&
        Array.isArray(filters.topics) &&
        filters.topics.every((topic) => typeof topic === 'number') &&
        isSource(filters.source) &&
        (filters.notebook === null || typeof filters.notebook === 'number') &&
        typeof filters.sort === 'string'
    );
}

/** Whether a value names one of the sources a collection gathers from. */
function isSource(value: unknown): boolean {
    return value === 'all' || value === 'sessions' || value === 'notebooks';
}

/** Whether a value names the source one item came from. */
function isSourceValue(value: unknown): boolean {
    return value === 'session' || value === 'notebook';
}

/** Whether a notebook source looks right, or is absent for a session item. */
function isNotebookSource(value: unknown): boolean {
    if (value === null) {
        return true;
    }

    if (typeof value !== 'object') {
        return false;
    }

    const notebook = value as Record<string, unknown>;

    return (
        typeof notebook.id === 'number' &&
        typeof notebook.name === 'string' &&
        typeof notebook.topic === 'string'
    );
}

/** Whether one saved notebook row looks right. */
function isRowContent(value: unknown): boolean {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const row = value as Record<string, unknown>;

    return (
        (row.entry_id === null || typeof row.entry_id === 'string') &&
        typeof row.entry_position === 'number' &&
        typeof row.meaning === 'string' &&
        typeof row.example === 'string' &&
        typeof row.example_html === 'string'
    );
}

/** Whether a list of saved notebook rows looks right. */
function isRowContents(value: unknown): boolean {
    return Array.isArray(value) && value.every(isRowContent);
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
        typeof item.latest_date === 'string' &&
        (item.seen_count === null || typeof item.seen_count === 'number') &&
        typeof item.needs_attention === 'boolean' &&
        (item.attention_reason === null ||
            typeof item.attention_reason === 'string') &&
        (item.session_id === null || typeof item.session_id === 'number') &&
        (item.session_date === null || typeof item.session_date === 'string') &&
        typeof item.topic === 'string' &&
        (item.question_position === null ||
            typeof item.question_position === 'number') &&
        isSourceValue(item.source) &&
        isNotebookSource(item.notebook) &&
        isRowContents(item.rows) &&
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
        typeof item.latest_date === 'string' &&
        (item.session_id === null || typeof item.session_id === 'number') &&
        typeof item.topic === 'string' &&
        (typeof item.session_date === 'string' || item.session_date === null) &&
        (item.question_position === null ||
            typeof item.question_position === 'number') &&
        typeof item.entry_position === 'number' &&
        (item.entry_id === null || typeof item.entry_id === 'string') &&
        isSourceValue(item.source) &&
        isNotebookSource(item.notebook)
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
        typeof item.latest_date === 'string' &&
        typeof item.seen_count === 'number' &&
        typeof item.worth_practicing === 'boolean' &&
        typeof item.needs_attention === 'boolean' &&
        (item.attention_reason === null ||
            typeof item.attention_reason === 'string') &&
        isSourceValue(item.source) &&
        isNotebookSource(item.notebook) &&
        isRowContents(item.rows) &&
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

    if (snapshot === null) {
        return collectionUrl('vocabulary');
    }

    return collectionUrl(
        snapshot.tab,
        snapshot.openItemKey,
        snapshot.filters ?? null,
        snapshot.reviewContext === undefined || snapshot.reviewContext === null
            ? null
            : {
                  review: snapshot.reviewContext.id,
                  eligible: snapshot.reviewContext.eligible,
              },
    );
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

/**
 * The address of a notebook, opened at the exact row the learner came from.
 * The board uses the same search-target behaviour, so the row is highlighted,
 * scrolled to and focused; a row edited or deleted since opens the board calmly
 * at the top with an explanation.
 */
export function collectionNotebookHref(
    notebook: CollectionNotebookSource,
    row: CollectionRowContent | null,
    type: CollectionTabKey = 'vocabulary',
): string {
    if (row === null) {
        return notebooksShow.url(notebook.id, {
            query: { from: 'collections' },
        });
    }

    const query: Record<string, string> = {
        from: 'collections',
        field: 'entry',
        type: feedbackTypeFor(type),
        position: String(row.entry_position),
    };

    if (row.entry_id !== null && row.entry_id !== '') {
        query.entry = row.entry_id;
    }

    return notebooksShow.url(notebook.id, { query });
}

/**
 * The link back to a note, to its session or to its notebook. A session note
 * whose row can no longer be named has nowhere to go and returns null, so the
 * panel never offers a broken link.
 */
export function collectionNoteHref(note: CollectionNoteItem): string | null {
    if (note.source === 'notebook') {
        return note.notebook === null
            ? null
            : collectionNotebookHref(
                  note.notebook,
                  {
                      entry_id: note.entry_id,
                      entry_position: note.entry_position,
                      meaning: '',
                      example: '',
                      example_html: '',
                  },
                  'grammar',
              );
    }

    if (note.session_id === null || note.question_position === null) {
        return null;
    }

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
function exampleKey(appearance: {
    example: string;
    example_html: string;
}): string {
    return (
        appearance.example.trim().toLocaleLowerCase() ||
        appearance.example_html.trim().toLocaleLowerCase()
    );
}

/**
 * Every different meaning of a notebook item, in the learner's saved row
 * order. The same meaning saved in two rows is shown once, and an empty meaning
 * is not something to show.
 */
export function distinctRowMeanings(
    item: CollectionItem,
): CollectionRowContent[] {
    return uniqueRows(item.rows, (row) =>
        row.meaning.trim().toLocaleLowerCase(),
    );
}

/** Every different example of a notebook item, in the saved row order. */
export function distinctRowExamples(
    item: CollectionItem,
): CollectionRowContent[] {
    return uniqueRows(item.rows, (row) => exampleKey(row));
}

function uniqueRows(
    rows: CollectionRowContent[],
    keyOf: (row: CollectionRowContent) => string,
): CollectionRowContent[] {
    const seen = new Set<string>();
    const result: CollectionRowContent[] = [];

    for (const row of rows) {
        const key = keyOf(row);

        if (key === '' || seen.has(key)) {
            continue;
        }

        seen.add(key);
        result.push(row);
    }

    return result;
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
        typeof snapshot.savedAt === 'number' &&
        (snapshot.filters === undefined ||
            (typeof snapshot.filters === 'object' &&
                snapshot.filters !== null)) &&
        (snapshot.topicOptions === undefined ||
            Array.isArray(snapshot.topicOptions)) &&
        (snapshot.notebookOptions === undefined ||
            Array.isArray(snapshot.notebookOptions)) &&
        (snapshot.hasSources === undefined ||
            typeof snapshot.hasSources === 'boolean') &&
        (snapshot.allTotal === undefined ||
            typeof snapshot.allTotal === 'number') &&
        (snapshot.attentionCount === undefined ||
            typeof snapshot.attentionCount === 'number') &&
        (snapshot.reviewContext === undefined ||
            snapshot.reviewContext === null ||
            (typeof snapshot.reviewContext === 'object' &&
                typeof (snapshot.reviewContext as Record<string, unknown>)
                    .id === 'number' &&
                typeof (snapshot.reviewContext as Record<string, unknown>)
                    .name === 'string' &&
                typeof (snapshot.reviewContext as Record<string, unknown>)
                    .eligible === 'boolean'))
    );
}
