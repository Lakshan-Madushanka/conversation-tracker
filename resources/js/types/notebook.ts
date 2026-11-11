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
}

export interface NotebookSnapshot {
    name: string;
    topic: string;
    description: string;
    feedback: NotebookFeedback;
}
