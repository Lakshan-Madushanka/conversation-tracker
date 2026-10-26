/** Shapes shared between the practice session page and the backend props. */

export interface TopicSummary {
    id: number;
    name: string;
}

/** The five feedback boxes every question can have. */
export type FeedbackType =
    | 'mistake'
    | 'vocabulary'
    | 'phrase'
    | 'grammar_note'
    | 'natural_version';

/** One entry of a list box. `id` is stable across saves, so an edit updates it. */
export interface FeedbackEntry {
    id: string;
    text: string;
    /** The same words as markup, once the entry has been saved. */
    html?: string;
}

/**
 * One row of the mistakes box: what was said, the correction (a single line
 * that keeps only bold and italic) and an optional why.
 *
 * A type alias, not an interface, so a row can stand in for the generic shape
 * the feedback table works with.
 */
export type MistakeEntry = {
    id: string;
    text: string;
    correction_html: string;
    reason: string;
};

/**
 * One row of the vocabulary box: the word, its meaning, and an optional example
 * (one piece of markup, or a list of entries the editor added).
 */
export type VocabularyEntry = {
    id: string;
    text: string;
    meaning: string;
    example_html: string | FeedbackEntry[];
};

/**
 * One row of the phrases box: the phrase, its meaning or use, and an optional
 * example (one piece of markup, or a list of entries the editor added). A type
 * alias, not an interface, so a row can stand in for the generic shape the
 * feedback table works with.
 */
export type PhraseEntry = {
    id: string;
    text: string;
    meaning: string;
    example_html: string | FeedbackEntry[];
};

/** One grammar note, kept as formatted text and never split by line. */
export interface GrammarNote {
    id: string;
    html: string;
}

/**
 * One value per feedback box. The mistakes, vocabulary and phrases boxes hold
 * table rows, the grammar notes hold entries, and the natural version stays
 * one piece of formatted text.
 */
export interface FeedbackValues {
    mistake: MistakeEntry[];
    vocabulary: VocabularyEntry[];
    phrase: PhraseEntry[];
    grammar_note: GrammarNote[];
    natural_version: string;
}

/** The value of any one box, as the feedback components pass it around. */
export type FeedbackValue = FeedbackValues[FeedbackType];

/**
 * A question as it comes back from the backend. Saved boxes are present in
 * `feedback`; boxes that were left empty are simply absent.
 */
export interface PracticeQuestionData {
    id: number;
    position: number;
    question_html: string;
    answer_html: string;
    feedback: Partial<FeedbackValues>;
}

/**
 * A question as it is edited on the page. `uid` is a client-only key that keeps
 * component state stable while questions are added and removed; the backend
 * ignores it.
 */
export interface PracticeQuestionForm {
    uid: string;
    question_html: string;
    answer_html: string;
    feedback: FeedbackValues;
}

export interface PracticeSessionData {
    id: number;
    status: string;
    /**
     * When the session was finished, or null while it is still unfinished.
     * It is set once and never moved, so re-finishing keeps the same moment.
     */
    finished_at: string | null;
    /**
     * When the content was last edited. Opening a session does not change it;
     * only the learner's edits do. Used for ordering on Home and the Library.
     */
    last_changed_at: string | null;
    /**
     * When the session was archived, or null while it is still in the Library.
     * An archived session is read-only until it is restored.
     */
    archived_at: string | null;
    /**
     * Bumped on every save. The editor sends the version it last read so the
     * server can refuse a save that would overwrite newer work from another tab.
     */
    version: number;
    topic: TopicSummary;
    questions: PracticeQuestionData[];
}

/** The payload sent when saving a session. */
export interface PracticeSessionForm {
    topic: string;
    questions: PracticeQuestionForm[];
}

/**
 * How much a session holds, counted from what was saved. Blank entries and
 * empty questions are left out; entries flagged as duplicates still count.
 */
export interface SessionCounts {
    questions: number;
    words: number;
    phrases: number;
    mistakes: number;
    grammar_notes: number;
}

/** The totals and practice numbers the Progress screen shows. */
export interface ProgressNumbers {
    words: number;
    phrases: number;
    mistakes: number;
    grammar_notes: number;
    practice_days: number;
    current_streak: number;
}

/** The session a delete warning names, before anything is removed. */
export interface SessionDeletionSession {
    id: number;
    topic: string;
    session_date: string | null;
    finished: boolean;
    archived: boolean;
}

/**
 * Everything the delete warning needs: what the session holds, progress before
 * and after removing it, and the signed token to confirm that exact state.
 */
export interface SessionDeletionPreview {
    session: SessionDeletionSession;
    counts: SessionCounts;
    progress: {
        before: ProgressNumbers;
        after: ProgressNumbers;
    };
    token: string;
}

/** The result of a permanent delete. Repeating it is reported as already deleted. */
export interface SessionDeletionResult {
    deleted: true;
    already_deleted: boolean;
    session: {
        id: number;
        topic: string;
    };
    counts: SessionCounts;
}

/** The result of finishing a session: either a summary, or an empty warning. */
export type FinishSessionResult =
    | {
          empty: false;
          session: PracticeSessionData;
          counts: SessionCounts;
          needs_attention: number;
          empty_questions_removed: number;
      }
    | { empty: true };

/** A finished session's counts, as opposed to the empty-session warning. */
export type FinishSummary = Extract<FinishSessionResult, { empty: false }>;

/** The most recent unfinished session, shown on the Home page. */
export interface ContinueCardData {
    id: number;
    topic: string;
    /** ISO 8601 timestamp of the last change. */
    updated_at: string;
}

/** One piece of a search snippet: plain words, or words that matched. */
export interface SearchSegment {
    text: string;
    match: boolean;
}

/** What kind of item a search match sits in. */
export type SearchMatchKind = 'topic' | 'question' | 'answer' | 'entry';

/** One matching item inside a session, as the Library lists it. */
export interface SearchMatch {
    /** Stable key for the list, unique inside one session. */
    key: string;
    kind: SearchMatchKind;
    /** The feedback box for an entry match, or null for topic, question and answer. */
    type: FeedbackType | null;
    question_position: number | null;
    entry_id: string | null;
    /** The entry's place in its box, used when its id has changed. */
    entry_position: number | null;
    segments: SearchSegment[];
}

/** A session as listed on Home and in the Library. */
export interface SessionCardData {
    id: number;
    topic: string;
    /** ISO 8601 timestamp of the day the session happened. */
    session_date: string | null;
    /** ISO 8601 timestamp of the last content change, or null if never edited. */
    last_edited_at: string | null;
    status: 'finished' | 'unfinished';
    /** When the session was archived, or null while it is in the Library. */
    archived_at: string | null;
    counts: SessionCounts;
    /** The topic split into plain and matched pieces while a search is active. */
    topic_segments?: SearchSegment[];
    /** How many items in the session matched, before the list is capped. */
    match_count?: number;
    /** The matching items shown beneath the card, capped by the server. */
    matches?: SearchMatch[];
}
