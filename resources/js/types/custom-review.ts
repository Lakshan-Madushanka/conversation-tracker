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

/** The page props a review's page receives. */
export interface CustomReviewPageData {
    review: CustomReviewSummary;
    groups: CustomReviewGroupData[];
    /** Where "Back" goes. */
    back_url: string;
    /** Collections narrowed to the items this review can use. */
    add_items_url: string;
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
      };

/** What the learner's add came to once any question was answered. */
export type AddReviewOutcome =
    | { status: 'added'; answer: AddedReviewAnswer }
    | {
          /** The learner turned a question down; nothing changed. */
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
