/** Shapes shared between the Collections page and the backend props. */

import type { CustomReviewSummary } from './custom-review';

/** The four personal collections a learner can browse. */
export type CollectionTabKey =
    | 'vocabulary'
    | 'phrases'
    | 'grammar'
    | 'mistakes';

/** One tab: which collection it shows and how many items it holds. */
export interface CollectionTabData {
    key: CollectionTabKey;
    label: string;
    /** How many items the tab holds right now. */
    count: number;
}

/** One topic that currently feeds the collection on screen. */
export interface CollectionTopicOption {
    id: number;
    name: string;
    /** How many items of this collection appeared in the topic. */
    count: number;
}

/** One notebook that currently holds items of the collection on screen. */
export interface CollectionNotebookOption {
    id: number;
    name: string;
    /** How many scoped items of this collection the notebook holds. */
    count: number;
}

/** The notebook an item was gathered from, named as its source. */
export interface CollectionNotebookSource {
    id: number;
    name: string;
    topic: string;
}

/** Which sources feed the list. */
export type CollectionSource = 'all' | 'sessions' | 'notebooks';

/** One saved row of a notebook item, in the learner's editor order. */
export interface CollectionRowContent {
    /** The row's id in the editor; null for entries saved before ids existed. */
    entry_id: string | null;
    entry_position: number;
    meaning: string;
    example: string;
    /** The example's words, with only its safe bold and italic kept. */
    example_html: string;
}

/** The orders a collection can be shown in. */
export type CollectionSort = 'newest' | 'az' | 'most_seen';

/** The learner's own status, as a filter. */
export type CollectionStatusFilter =
    | 'all'
    | 'none'
    | 'learned'
    | 'still_difficult';

/** Everything that searches, narrows or orders one collection. */
export interface CollectionFilters {
    q: string;
    starred: boolean;
    status: CollectionStatusFilter;
    attention: boolean;
    topics: number[];
    source: CollectionSource;
    /** The one notebook the list is narrowed to, when one is chosen. */
    notebook: number | null;
    sort: CollectionSort;
}

/** One piece of a search hint: plain words, or a piece that matched. */
export interface CollectionMatchSegment {
    text: string;
    match: boolean;
}

/**
 * The server-computed hint saying where a search found an item. Its words are
 * always plain text cut from what the learner saved, never saved markup.
 */
export interface CollectionMatch {
    label: string;
    segments: CollectionMatchSegment[];
}

/** One place something was saved, with the row it points back to. */
export interface CollectionAppearance {
    session_id: number;
    session_date: string | null;
    topic: string;
    question_position: number;
    /** The row's id in the editor; null for entries saved before ids existed. */
    entry_id: string | null;
    /** The row's place in its box, used when there is no id. */
    entry_position: number;
    meaning: string;
    example: string;
    /** The example's words, with only its safe bold and italic kept. */
    example_html: string;
}

/**
 * One row a collection can jump back to: enough to build the reading page
 * address that opens the session at the exact row.
 */
export interface CollectionRow {
    session_id: number;
    question_position: number;
    entry_id: string | null;
    entry_position: number;
}

/** One word or phrase in a collection, gathered from its source scope. */
export interface CollectionItem {
    /** The normalised text, used in the address and to find the item again. */
    key: string;
    title: string;
    meaning: string;
    example: string;
    /** The example's words, with only its safe bold and italic kept. */
    example_html: string;
    /** The latest session date, or the moment a notebook row was added. */
    latest_date: string;
    /**
     * How many different questions the word or phrase appeared in. Null for a
     * notebook item, which has no appearances to count.
     */
    seen_count: number | null;
    /** True when one of its appearances asks for the learner's attention. */
    needs_attention: boolean;
    /** Why it asks for attention, in the wording shared with the app. */
    attention_reason: string | null;
    /** The session the newest appearance came from; null for a notebook. */
    session_id: number | null;
    session_date: string | null;
    /** The source's topic: the session's or the notebook's. */
    topic: string;
    question_position: number | null;
    /** Which source the item was gathered from. */
    source: CollectionSourceValue;
    /** The notebook it belongs to, when it came from one. */
    notebook: CollectionNotebookSource | null;
    /** Every saved row of a notebook item, in the learner's editor order. */
    rows: CollectionRowContent[];
    /** The learner's own marks on this word. */
    starred: boolean;
    status: CollectionItemStatusValue | null;
    has_notes: boolean;
    /** The open panel receives these; the list does not need them. */
    notes_html?: string;
    notes_version?: number;
    /**
     * Whether the review being built already holds this item. A list restored
     * from an older snapshot may not carry it, so absent means not added.
     */
    in_review?: boolean;
    /** Where the search found it, when a search is narrowing the list. */
    match?: CollectionMatch | null;
    /** The open panel receives every appearance; a list carries only the newest. */
    appearances: CollectionAppearance[];
}

/** Which source one item came from. */
export type CollectionSourceValue = 'session' | 'notebook';

/**
 * One grammar note, priced as its own item: identical notes stay separate
 * items, so each keeps the key of the row it was saved in.
 */
export interface CollectionNoteItem {
    key: string;
    text: string;
    /** The first lines, with the formatting removed, cut on the server. */
    preview: string;
    /** The full note, reduced again on the server to its safe formatting. */
    html: string;
    /** The session date, or the moment a notebook row was added. */
    latest_date: string;
    session_id: number | null;
    session_date: string | null;
    /** The source's topic: the session's or the notebook's. */
    topic: string;
    question_position: number | null;
    entry_id: string | null;
    entry_position: number;
    source: CollectionSourceValue;
    notebook: CollectionNotebookSource | null;
    /** The note's own marks; its identity is the note itself. */
    starred: boolean;
    status: CollectionItemStatusValue | null;
    has_notes: boolean;
    notes_html?: string;
    notes_version?: number;
    /** Whether the review being built already holds this note. */
    in_review?: boolean;
    /** Where the search found it, when a search is narrowing the list. */
    match?: CollectionMatch | null;
}

/**
 * One place a mistake was saved, with the row it points back to and the
 * words exactly as they were said there.
 */
export interface CollectionMistakeAppearance {
    session_id: number;
    session_date: string | null;
    topic: string;
    question_position: number;
    /** The row's id in the editor; null for entries saved before ids existed. */
    entry_id: string | null;
    /** The row's place in its box, used when there is no id. */
    entry_position: number;
    said: string;
    correction: string;
    /** The correction's words, with only its safe bold and italic kept. */
    correction_html: string;
    reason: string;
}

/**
 * One mistake, grouped across every finished session by the pair of what was
 * said and the correction. The same mistake with a different reason stays one
 * group; each appearance keeps the words that were written there.
 */
export interface CollectionMistakeItem {
    /** The key derived from the normalised pair, used in the address. */
    key: string;
    title: string;
    correction: string;
    /** The correction's words, with only its safe bold and italic kept. */
    correction_html: string;
    reason: string;
    latest_date: string;
    /** How many different questions the mistake was seen in. */
    seen_count: number;
    /** True once the mistake is worth practicing, by the shared threshold. */
    worth_practicing: boolean;
    /** True when one of its appearances asks for the learner's attention. */
    needs_attention: boolean;
    /** Why it asks for attention, in the wording shared with the app. */
    attention_reason: string | null;
    /** Mistakes only come from sessions, so this is always 'session'. */
    source: CollectionSourceValue;
    notebook: CollectionNotebookSource | null;
    /** Notebook rows only; a mistake carries none. */
    rows: CollectionRowContent[];
    /** The learner's own marks on this mistake. */
    starred: boolean;
    status: CollectionItemStatusValue | null;
    has_notes: boolean;
    notes_html?: string;
    notes_version?: number;
    /** Whether the review being built already holds this mistake. */
    in_review?: boolean;
    /** Where the search found it, when a search is narrowing the list. */
    match?: CollectionMatch | null;
    /** The open panel receives every appearance; a list carries only the newest. */
    appearances: CollectionMistakeAppearance[];
}

/** Whatever an opened panel can show: a word, a phrase, one note or a mistake. */
export type CollectionOpenedItem =
    | CollectionItem
    | CollectionNoteItem
    | CollectionMistakeItem;
/** The page props the Collections page receives. */
export interface CollectionPage {
    tab: CollectionTabKey;
    tabs: CollectionTabData[];
    items: CollectionOpenedItem[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
    /** How many items the whole collection holds, before filters. */
    all_total?: number;
    /** How many items of the whole collection need attention. */
    attention_count?: number;
    /** The topics that currently feed this collection. */
    topic_options?: CollectionTopicOption[];
    /** The notebooks that currently feed this collection, with their counts. */
    notebook_options?: CollectionNotebookOption[];
    /** The filters the server actually honoured. */
    filters?: CollectionFilters;
    /** False when the learner has never finished a session. */
    has_finished_sessions: boolean;
    /** False when neither a finished session nor a saved notebook row exists. */
    has_sources?: boolean;
    /** The item whose panel should open, when the address names one. */
    open_item: CollectionOpenedItem | null;
    /** True when the address names an item that is no longer there. */
    open_item_missing: boolean;
    /** The review the items are marked against, or none to agree on. */
    review_target?: CustomReviewSummary | null;
    /** The review whose + Add items opened this filtered view. */
    review_context?: CollectionReviewContext | null;
}

/**
 * The review a Collections visit is topping up. Only the eligible view carries
 * one, because only then is the list narrowed on that review's behalf.
 */
export interface CollectionReviewContext {
    id: number;
    name: string;
    eligible: boolean;
}

/** One page returned by the "Load more" request. */
export interface CollectionItemsPage {
    items: CollectionOpenedItem[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
    /**
     * The counts around the list. A quiet refresh asks for these, so the tabs,
     * topic picker and attention count follow changes made elsewhere.
     */
    tabs?: CollectionTabData[];
    all_total?: number;
    attention_count?: number;
    topic_options?: CollectionTopicOption[];
    notebook_options?: CollectionNotebookOption[];
    filters?: CollectionFilters;
    has_finished_sessions?: boolean;
    has_sources?: boolean;
    review_target?: CustomReviewSummary | null;
    review_context?: CollectionReviewContext | null;
}

/**
 * The learner's own label for an item. The string values match the backend
 * enum; there is no stored "none" case, only the request value used to clear.
 */
export type CollectionItemStatusValue = 'learned' | 'still_difficult';

/**
 * The kind of item a mark belongs to, as the backend names the feedback
 * types that make up Collections.
 */
export type CollectionKindValue =
    | 'vocabulary'
    | 'phrase'
    | 'grammar_note'
    | 'mistake';

/**
 * The star, status and personal note the learner keeps for one item. These
 * fields ride along on every item, so one shape serves the list and the
 * answer the server returns after a change.
 */
export interface ItemMarkData {
    /** Whether the star is on right now. */
    starred: boolean;
    /** The chosen label, or null while none is set. */
    status: CollectionItemStatusValue | null;
    /** Whether a personal note holds anything. */
    has_notes: boolean;
    /** Which version of the note the screen holds, for conflict checks. */
    notes_version: number;
    /** The saved note as cleaned formatted text. */
    notes_html: string;
}
