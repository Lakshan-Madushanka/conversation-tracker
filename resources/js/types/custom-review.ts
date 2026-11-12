/** Shapes shared between the custom review pages and the backend props. */

import type { CollectionKindValue } from './collections';

/** Every kind a custom review can hold, by the backend's names. */
export type CustomReviewKind = CollectionKindValue | 'question';

/** The three statuses a review is ever in. */
export type CustomReviewStatusValue = 'empty' | 'draft' | 'finished';

/** A review by what tells it apart: its name, its status, its size. */
export interface CustomReviewSummary {
    id: number;
    name: string;
    description: string | null;
    status: CustomReviewStatusValue;
    status_label: string;
    /** The kinds chosen, or null when the review allows every kind. */
    allowed_kinds: CustomReviewKind[] | null;
    /** "All kinds", "Words only", or "Words + Phrases". */
    allowed_kinds_label: string;
    item_count: number;
    item_count_label: string;
    /** True past the recommended size; advice only, never a limit. */
    too_many_items: boolean;
    /** "Too Many Items · 26 items" when the review is above the size. */
    too_many_label: string | null;
}

/** One item in a review, read live from the session it came from. */
export interface CustomReviewItemData {
    id: string;
    kind: CustomReviewKind;
    /** The collection identity the review stores. */
    identity: string;
    title: string;
    correction: string;
    /** The correction's words, with only its safe bold and italic kept. */
    correction_html: string;
    /** False while every place it came from is archived. */
    available: boolean;
    /** Whether the review can use it in an attempt right now. */
    eligible: boolean;
    /** Why it cannot be used yet, in the review's own words. */
    reason: string | null;
    topic: string;
    /** Whether the words come from a finished session or a notebook. */
    source_kind: 'session' | 'notebook';
    /** The notebook the words were saved in, when they came from one. */
    notebook: { id: number; name: string; topic: string } | null;
    session_date: string | null;
    /** The reading page address that opens the item in a new tab. */
    href: string;
}

/** One collection's items, in the order the review's page shows them. */
export interface CustomReviewGroupData {
    key: CustomReviewKind;
    label: string;
    count: number;
    items: CustomReviewItemData[];
}

/** The orders the Custom reviews listing can be read in. */
export type CustomReviewSortValue = 'recently_attempted' | 'name' | 'created';

/** What the listing is narrowed and ordered by, as the address keeps it. */
export interface CustomReviewFiltersState {
    /** The search as typed, or an empty string. */
    q: string;
    /** The statuses chosen; any of them matches. */
    statuses: CustomReviewStatusValue[];
    sort: CustomReviewSortValue;
}

/** One custom review as a card on the listing. */
export interface CustomReviewCardData extends CustomReviewSummary {
    /** The description only as far as a card should show it. */
    description_excerpt: string | null;
    created_at: string | null;
    attempt_count: number;
    has_attempts: boolean;
    /** "No attempts yet", or the latest attempt in words. */
    attempt_number_label: string;
    previous_attempt_status: string | null;
    previous_attempt_label: string | null;
    /** The attempt history line, such as "3 attempts · last on 12 Oct". */
    attempt_summary_label: string;
    /** Where View history goes, only when there is history to see. */
    view_history_url: string | null;
    /** Finished with at least one item that can be used right now. */
    can_start: boolean;
    /** How many of its items an attempt would hold right now. */
    eligible_count: number;
    /** "Start practice · 26 items", only when no attempt is running. */
    start_label: string | null;
    start_url: string | null;
    /** An attempt is running and should be resumed instead of started. */
    in_progress: boolean;
    resume_url: string | null;
    /** What still stands between the review and practice, or null. */
    practice_note: string | null;
    /** Where the card's Delete sends the review for good. */
    delete_url: string;
}

/** Whether this review can be practised now, and what the button says. */
export interface CustomReviewPracticeData {
    can_start: boolean;
    eligible_count: number;
    /** "Start practice · 26 items", or null while an attempt runs. */
    start_label: string | null;
    start_url: string | null;
    in_progress: boolean;
    resume_url: string | null;
    /** What is still missing before practice, or null. */
    note: string | null;
}

/** One item inside an attempt's history, read only from its snapshot. */
export interface CustomReviewAttemptItemData {
    id: number;
    kind: CustomReviewKind;
    /** "Words", "Phrases", "Grammar notes", "Mistakes" or "Questions". */
    kind_label: string;
    exercise: string;
    /** The snapshot's words, exactly as the attempt showed them. */
    text: string;
    rating: 'hard' | 'okay' | 'easy' | null;
    rating_label: string | null;
    state: 'rated' | 'skipped' | 'not_rated';
    topic: string;
    session_date: string | null;
    /** The reading page at the item, opened in a new tab. */
    href: string | null;
    /** True when the item was deleted; its snapshot stays, its link does not. */
    deleted: boolean;
}

/** An attempt's counts, read from its own ratings every time. */
export interface CustomReviewAttemptCounts {
    hard: number;
    okay: number;
    easy: number;
    skipped: number;
    rated: number;
    unrated: number;
}

/** One attempt as the review's history lists it. */
export interface CustomReviewAttemptHistoryData {
    id: number;
    number: number | null;
    /** "3rd attempt", or "Not started" for a shell that never began. */
    number_label: string;
    status: 'not_started' | 'in_progress' | 'completed' | 'finished_early';
    status_label: string;
    started_at: string | null;
    finished_at: string | null;
    counts: CustomReviewAttemptCounts;
    items: CustomReviewAttemptItemData[];
}

/** The running attempt, as its finish summary names it. */
export interface CustomReviewAttemptData {
    id: number;
    number: number | null;
    number_label: string;
    status: string;
    status_label: string;
    total: number;
    rated: number;
    skipped: number;
    encouragement: string;
}

/** The addresses the attempt screen saves to. */
export interface CustomReviewAttemptUrls {
    rate: string;
    undo: string;
    finish_early: string;
    exit: string;
    done: string;
    start_another: string | null;
}

/** The page props the Custom reviews listing receives. */
export interface CustomReviewsPageData {
    reviews: CustomReviewCardData[];
    next_cursor: string | null;
    has_more: boolean;
    /** How many reviews match the search and statuses. */
    total: number;
    /** How many reviews exist in all. */
    all_total: number;
    per_page: number;
    filters: CustomReviewFiltersState;
    /** The name the creation dialog offers, filled in as a new review would be. */
    next_review_name: string;
    /** What a deletion just answered with, said once and then forgotten. */
    notice: string | null;
}

/** The page props a review's page receives. */
export interface CustomReviewPageData {
    review: CustomReviewSummary;
    groups: CustomReviewGroupData[];
    /** Where "Back" goes. */
    back_url: string;
    /** Collections narrowed to the items this review can use. */
    add_items_url: string;
    /** Start practice, or resume the attempt already running. */
    practice: CustomReviewPracticeData;
    /** Every attempt, newest first, read-only. */
    attempts: CustomReviewAttemptHistoryData[];
    /** How many attempts exist in all, however many are on screen. */
    attempt_total: number;
    /** The page after the loaded ones, or null once the history is whole. */
    attempts_next_page: number | null;
    /** The attempt the address has open, or null when every row is closed. */
    expanded_attempt: number | null;
    /** Why a start was refused, in the review's calm words, or null. */
    attempt_error: string | null;
    /** Where the page's Delete sends the review for good. */
    delete_url: string;
}

/** One page of a review's attempt history, for Load more. */
export interface CustomReviewAttemptPageData {
    attempts: CustomReviewAttemptHistoryData[];
    /** The page after this one, or null once the history is whole. */
    next_page: number | null;
    /** How many attempts exist in all. */
    total: number;
}

/** An add that went through, with the review it went into. */
export interface AddedReviewAnswer {
    outcome: 'added';
    review: CustomReviewSummary;
    added_count: number;
    already_count: number;
    added_identities: string[];
    already_identities: string[];
    total: number;
}

/** The server's answer to an add, before the learner has been asked anything. */
export type AddReviewAnswer =
    | AddedReviewAnswer
    | {
          outcome: 'needs_review_choice';
          reviews: CustomReviewSummary[];
      }
    | {
          outcome: 'needs_confirmation';
          review: null;
          resulting_count: number;
      }
    | {
          /** The add would cross the maximum; nothing was written. */
          outcome: 'refused_maximum';
          review: CustomReviewSummary | null;
          /** The refusal in the server's words, naming the room left. */
          message: string;
      }
    | {
          /** The review has narrowed its kinds; nothing was written. */
          outcome: 'refused_kind';
          review: CustomReviewSummary | null;
          /** The refusal in the server's words, naming the kinds allowed. */
          message: string;
      };

/** What the learner's add came to once any question was answered. */
export type AddReviewOutcome =
    | { status: 'added'; answer: AddedReviewAnswer }
    | {
          /**
           * Nothing changed and no question is waiting: the learner turned a
           * question down, or the server refused the add and said why.
           */
          status: 'cancelled';
      }
    | {
          /** The request did not go through; the selection is kept. */
          status: 'failed';
      };

/** What removing a set of items came to. */
export interface RemoveReviewResult {
    removed_count: number;
    removed_identities: string[];
    total: number;
}

/** Every kind a notebook can offer a review. */
export type NotebookReviewKind = 'vocabulary' | 'phrase' | 'grammar_note';

/** One item Create review and practise can offer, as the server reads it. */
export interface NotebookReviewCandidateData {
    kind: NotebookReviewKind;
    identity: string;
    title: string;
    meaning: string;
    example: string;
    eligible: boolean;
    /** Why it cannot be practised yet, in the review's own words. */
    reason: string | null;
}

/** What one notebook currently offers a new review. */
export interface NotebookReviewCandidatesData {
    candidates: NotebookReviewCandidateData[];
    count: number;
    eligible_count: number;
    /** The size the warning is written around. */
    recommended: number;
    /** The most a review can hold. */
    maximum: number;
}

/** What Create review and practise came to, in the server's own words. */
export type NotebookReviewAnswer =
    | {
          outcome: 'created';
          review_id: number;
          review_url: string;
          attempt_url: string;
          count: number;
          maximum: number;
          message: null;
      }
    | {
          /** The review was saved; its first attempt could not start. */
          outcome: 'created_start_failed';
          review_id: number;
          review_url: string;
          attempt_url: null;
          count: number;
          maximum: number;
          message: string;
      }
    | {
          /** More items than the maximum: the learner must choose. */
          outcome: 'needs_selection';
          review_id: null;
          review_url: null;
          attempt_url: null;
          count: number;
          maximum: number;
          message: null;
      }
    | {
          /** Past the recommended size: the learner must agree. */
          outcome: 'needs_confirmation';
          review_id: null;
          review_url: null;
          attempt_url: null;
          count: number;
          maximum: number;
          message: null;
      }
    | {
          /** Nothing was written, and the server said why. */
          outcome:
              | 'too_many_selected'
              | 'no_candidates'
              | 'no_eligible'
              | 'stale_selection';
          review_id: null;
          review_url: null;
          attempt_url: null;
          count: number;
          maximum: number;
          message: string;
      };
