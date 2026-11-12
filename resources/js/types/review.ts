import type { CollectionItemStatusValue } from './collections';

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
    /** Whether the words come from a finished session or a notebook. */
    source_kind: 'session' | 'notebook';
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
    source_kind: 'session' | 'notebook';
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
    source_kind: 'session' | 'notebook';
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
    source_kind: 'session' | 'notebook';
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

/**
 * The review rules, as the numbers the Review Home explanation is written
 * around. They come from the settings and the schedule the app actually uses.
 */
export interface ReviewRulesData {
    /** How many items a day's sitting holds. */
    dailySize: number;
    /** How few of those may be items never reviewed before. */
    newItems: number;
    /** Days until an item rated Hard comes back. */
    hardDays: number;
    /** Days until an item rated Okay comes back. */
    okayDays: number;
    /** Days until an item rated Easy comes back, further out each time. */
    easyGaps: number[];
    /** Easy ratings in a row that can offer Learned. */
    learnedRun: number;
    /** Hard ratings in a row that can offer Still difficult. */
    difficultRun: number;
    /** How many offers one finish screen ever shows. */
    maxSuggestions: number;
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

/**
 * One sitting the learner recently ended, as Review Home lists it. The counts
 * come from the saved ratings, so they always match what was actually rated.
 */
export interface RecentReviewData {
    id: number;
    type: 'daily' | 'custom';
    /** The same fact in words, so nothing is conveyed by colour alone. */
    type_label: string;
    /** The instant the sitting ended, shown as the learner's own local date. */
    finished_at: string;
    rated: number;
    counts: ReviewCounts;
    url: string;
}

/** What the custom review page was asked for, as the server understood it. */
export interface CustomReviewChoices {
    topics: number[];
    collections: string[];
    starred: boolean;
    learned: boolean;
    size: number;
}

/** One collection a custom review can be restricted to. */
export interface CustomReviewCollectionOption {
    value: string;
    label: string;
}

/** What a custom review with the current choices would hold. */
export interface CustomReviewPreview {
    /** How many items match — exactly what a started sitting would contain. */
    count: number;
    size: number;
    minutes: number;
    mix: string;
    /** Whether the match is a comfortable sitting for the size asked. */
    comfortable: boolean;
    /** How many exercise kinds would take part. */
    kinds: number;
}

/**
 * A status the finish screen offers, worked out from the run of ratings that
 * just ended. Confirming it changes only the item's status.
 */
export interface StatusSuggestionData {
    kind: string;
    item_identity: string;
    status: CollectionItemStatusValue;
    status_label: string;
    /** The rating the run was made of, in words: "Easy" or "Hard". */
    rating_label: string;
    /** How many times in a row it was given. */
    run: number;
    text: string;
    /** The rating that began the run, so a "Not now" is tied to that run alone. */
    run_started_id: number;
}

/** One past sitting, as its own read-only results page shows it. */
export interface ReviewResultsData {
    id: number;
    type: 'daily' | 'custom';
    type_label: string;
    finished_at: string | null;
    rated: number;
    total: number;
    counts: ReviewCounts;
}

/** One item of a past sitting, with the rating it earned. */
export interface ReviewResultsItem {
    kind: string;
    identity: string;
    card: ReviewCard;
    rating: ReviewRating | null;
}

/** One exercise kind of a past sitting. */
export interface ReviewResultsSection {
    key: string;
    label: string;
    icon: string;
    number: number;
    total: number;
    rated: number;
    counts: ReviewCounts;
    items: ReviewResultsItem[];
}
