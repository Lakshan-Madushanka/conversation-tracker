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

/** One value per feedback box, keyed by type. */
export type FeedbackValues = Record<FeedbackType, string>;

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
