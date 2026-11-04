/** How the learner felt about one item: the three saved ratings. */
export type ReviewRating = 'hard' | 'okay' | 'easy';

/**
 * Today's review suggestion card, shared by Home and Review Home. The data
 * comes ready from the server; the card only draws it.
 */
export interface TodayReviewData {
    /** What the card shows right now. */
    state: 'suggest' | 'done' | 'nothing' | 'empty';
    /** How many items the suggestion holds, or null when there is none. */
    count: number | null;
    /** Whole minutes the suggestion takes, or null when there is none. */
    minutes: number | null;
    /** The mix description, e.g. "5 flashcards, 3 mistakes to fix". */
    mix: string | null;
    /** Whether more worth revisiting remains (the "done" state only). */
    canReviewMore: boolean | null;
}

/**
 * How many words, phrases and mistakes are waiting for a correction before
 * they can be practiced.
 */
export interface WaitingForFixData {
    vocabulary: number;
    phrases: number;
    mistakes: number;
}

/** One flashcard, as the review screen shows it. */
export interface FlashcardCard {
    kind: 'vocabulary' | 'phrase';
    exercise: 'flashcard';
    item_identity: string;
    label: string;
    front: string;
    meaning: string;
    example: string | null;
    session_url: string | null;
}

/** One fix-the-mistake card: what was said, its correction and why. */
export interface MistakeCard {
    kind: 'mistake';
    exercise: 'mistake';
    item_identity: string;
    label: string;
    said: string;
    correction_html: string;
    reason: string | null;
    more_reasons: string[];
    session_url: string | null;
}

/** One say-it-again card: the tutor's question and its natural version. */
export interface SayItAgainCard {
    kind: 'question';
    exercise: 'say_it_again';
    item_identity: string;
    label: string;
    question_html: string;
    question_text: string;
    natural_html: string;
    natural_text: string;
    session_url: string | null;
}

/** One fill-in-the-blank card: the prompt with a gap, and its answer. */
export interface FillInTheBlankCard {
    kind: 'vocabulary' | 'phrase' | 'mistake' | 'grammar_note';
    exercise: 'fill_in_the_blank';
    item_identity: string;
    label: string;
    prompt_html: string;
    answer_html: string;
    answer_text: string;
    hint: string | null;
    hint_label: string | null;
    session_url: string | null;
}

export type ReviewCard =
    | FlashcardCard
    | MistakeCard
    | SayItAgainCard
    | FillInTheBlankCard;

/** The friendly nudge for grammar notes that cannot make a blank yet. */
export interface GrammarNotesTipData {
    count: number;
}

export interface ReviewCounts {
    hard: number;
    okay: number;
    easy: number;
}

/** One step of the review stepper: an exercise kind and its progress. */
export interface ReviewSection {
    key: string;
    label: string;
    icon: string;
    number: number;
    total: number;
    rated: number;
    completed: boolean;
    counts: ReviewCounts;
    url: string;
}

/** The card on screen, with the section it belongs to. */
export interface CurrentReviewItem {
    step: string;
    label: string;
    /** The item's place in the whole review, used in the address. */
    position: number;
    /** The item's place inside its own section, for wording. */
    stepPosition: number;
    total: number;
    card: ReviewCard;
    rating: ReviewRating | null;
}

/** A finished section opened from the stepper. */
export interface CompletedSection {
    key: string;
    label: string;
    total: number;
    counts: ReviewCounts;
    next_url: string | null;
}
