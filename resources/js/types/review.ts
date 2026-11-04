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
    /** The mix description, e.g. "8 flashcards", or null when there is none. */
    mix: string | null;
    /** Whether more worth revisiting remains (the "done" state only). */
    canReviewMore: boolean | null;
}

/** How many words and phrases are waiting for a meaning before they can be practiced. */
export interface WaitingForFixData {
    vocabulary: number;
    phrases: number;
}
