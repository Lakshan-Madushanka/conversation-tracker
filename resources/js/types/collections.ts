/** Shapes shared between the Collections page and the backend props. */

/** The four personal collections a learner can browse. */
export type CollectionTabKey =
    | 'vocabulary'
    | 'phrases'
    | 'grammar'
    | 'mistakes';

/** One tab: its collection, how many items it holds, and whether it is built. */
export interface CollectionTabData {
    key: CollectionTabKey;
    label: string;
    /** How many items the tab holds; null while the collection is on its way. */
    count: number | null;
    built: boolean;
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

/** One word or phrase in a collection, gathered from every finished session. */
export interface CollectionItem {
    /** The normalised text, used in the address and to find the item again. */
    key: string;
    title: string;
    meaning: string;
    example: string;
    /** The example's words, with only its safe bold and italic kept. */
    example_html: string;
    latest_session_date: string | null;
    /** How many different questions the word or phrase appeared in. */
    seen_count: number;
    /** True when one of its appearances asks for the learner's attention. */
    needs_attention: boolean;
    /** Why it asks for attention, in the wording shared with the app. */
    attention_reason: string | null;
    appearances: CollectionAppearance[];
}

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
    latest_session_date: string | null;
    session_id: number;
    session_date: string | null;
    topic: string;
    question_position: number;
    entry_id: string | null;
    entry_position: number;
}

/** Whatever an opened panel can show: a word, a phrase or one note. */
export type CollectionOpenedItem = CollectionItem | CollectionNoteItem;

/** The page props the Collections page receives. */
export interface CollectionPage {
    tab: CollectionTabKey;
    tabs: CollectionTabData[];
    items: (CollectionItem | CollectionNoteItem)[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
    /** False when the learner has never finished a session. */
    has_finished_sessions: boolean;
    /** The item whose panel should open, when the address names one. */
    open_item: CollectionOpenedItem | null;
    /** True when the address names an item that is no longer there. */
    open_item_missing: boolean;
}

/** One page returned by the "Load more" request. */
export interface CollectionItemsPage {
    items: (CollectionItem | CollectionNoteItem)[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
}
