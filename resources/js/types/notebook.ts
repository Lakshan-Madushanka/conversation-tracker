import type { CollectionMatch } from '@/types/collections';
import type {
    GrammarNote,
    PhraseEntry,
    TopicSummary,
    VocabularyEntry,
} from '@/types/practice';

export interface NotebookFeedback {
    vocabulary: VocabularyEntry[];
    phrase: PhraseEntry[];
    grammar_note: GrammarNote[];
}

export interface NotebookData {
    id: number;
    name: string;
    topic: TopicSummary;
    description_html: string;
    version: number;
    last_changed_at: string | null;
    feedback: NotebookFeedback;
}

/**
 * A search match found in a saved row rather than in the card's own words.
 * The pieces are plain text cut by the server; the coordinates open the board
 * at that row.
 */
export interface NotebookCardMatch extends CollectionMatch {
    type: 'vocabulary' | 'phrase' | 'grammar_note';
    entry: string | null;
    position: number;
}

export interface NotebookCardData {
    id: number;
    name: string;
    topic: string;
    description: string;
    last_updated_at: string | null;
    counts: {
        words: number;
        phrases: number;
        grammar_notes: number;
    };
    match: NotebookCardMatch | null;
}

export interface NotebookSnapshot {
    name: string;
    topic: string;
    description: string;
    feedback: NotebookFeedback;
}

/** Saved entries per kind, and the distinct study items they add up to. */
export interface NotebookDeletionCounts {
    words: number;
    phrases: number;
    grammar_notes: number;
}

/** A saved custom review that loses items when the notebook goes. */
export interface NotebookDeletionReview {
    id: number;
    name: string;
    removed: number;
    remaining: number;
    becomes_empty: boolean;
}

export interface NotebookDeletionPreview {
    notebook: {
        id: number;
        name: string;
        topic: string;
    };
    counts: NotebookDeletionCounts;
    items: NotebookDeletionCounts;
    reviews: NotebookDeletionReview[];
    token: string;
}

export interface NotebookDeletionResult {
    deleted: true;
    already_deleted: boolean;
    notebook: {
        id: number;
        name: string;
    };
    counts: NotebookDeletionCounts;
}
