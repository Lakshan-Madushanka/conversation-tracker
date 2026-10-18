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
 * (a single line that keeps only bold and italic).
 */
export type VocabularyEntry = {
    id: string;
    text: string;
    meaning: string;
    example_html: string;
};

/**
 * One row of the phrases box: the phrase, its meaning or use, and an optional
 * example (a single line that keeps only bold and italic). A type alias, not
 * an interface, so a row can stand in for the generic shape the feedback table
 * works with.
 */
export type PhraseEntry = {
    id: string;
    text: string;
    meaning: string;
    example_html: string;
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

/** The most recent unfinished session, shown on the Home page. */
export interface ContinueCardData {
    id: number;
    topic: string;
    /** ISO 8601 timestamp of the last change. */
    updated_at: string;
}
