/** Shapes shared between the Collections page and the backend props. */

/** The four personal collections a learner can browse. */
export type CollectionTabKey =
    | 'vocabulary'
    | 'phrases'
    | 'grammar'
    | 'mistakes';

/** One tab, and whether its collection has been built yet. */
export interface CollectionTabData {
    key: CollectionTabKey;
    label: string;
    built: boolean;
}

/** One place a word was saved, with the row it points back to. */
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
}

/** One word in a collection, gathered from every finished session. */
export interface CollectionItem {
    /** The normalised word, used in the address and to find the item again. */
    key: string;
    word: string;
    meaning: string;
    example: string;
    latest_session_date: string | null;
    /** How many different questions the word appeared in. */
    seen_count: number;
    /** True when no appearance has a meaning yet. */
    needs_attention: boolean;
    appearances: CollectionAppearance[];
}

/** The page props the Collections page receives. */
export interface CollectionPage {
    tab: CollectionTabKey;
    tabs: CollectionTabData[];
    items: CollectionItem[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
    /** False when the learner has never finished a session. */
    has_finished_sessions: boolean;
    /** The word whose panel should open, when the address names one. */
    open_word: CollectionItem | null;
    /** True when the address names a word that is no longer there. */
    open_word_missing: boolean;
}

/** One page returned by the "Load more" request. */
export interface CollectionItemsPage {
    items: CollectionItem[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
}
