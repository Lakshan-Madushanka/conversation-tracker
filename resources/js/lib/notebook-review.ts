/**
 * Create review and practise, from one notebook.
 *
 * The server decides everything: which items the notebook offers right now,
 * whether they fit the maximum, whether the recommended size needs agreeing to,
 * and whether a selection is still valid. This file only sends those questions
 * and reads the answers, so the screen never invents a candidate list, a size
 * or an outcome of its own.
 *
 * The creation key makes a press repeatable. It is made once when the dialog
 * opens and kept through retries, so a double press, a retry or a second tab
 * cannot save a second review or start a second attempt.
 */

import { jsonRequest } from '@/lib/http';
import { reviewCandidates } from '@/routes/notebooks';
import { store as reviewStore } from '@/routes/notebooks/review';
import type {
    NotebookReviewAnswer,
    NotebookReviewCandidatesData,
    NotebookReviewKind,
} from '@/types';

/** Every kind a notebook can hold, in the order the dialog lists them. */
export const NOTEBOOK_REVIEW_KINDS: NotebookReviewKind[] = [
    'vocabulary',
    'phrase',
    'grammar_note',
];

/** The dialog's own words for each kind. */
export const NOTEBOOK_REVIEW_KIND_LABELS: Record<NotebookReviewKind, string> = {
    vocabulary: 'Words',
    phrase: 'Phrases',
    grammar_note: 'Grammar notes',
};

/** A key that makes one creation repeatable, even across a reload. */
export function newNotebookReviewKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

/**
 * The items the notebook offers for the chosen kinds. Read-only: nothing is
 * saved, so opening and closing the dialog leaves the notebook untouched.
 */
export async function loadNotebookReviewCandidates(
    notebookId: number,
    kinds: NotebookReviewKind[],
): Promise<NotebookReviewCandidatesData> {
    const response = await jsonRequest(
        reviewCandidates.url(notebookId, { query: { kinds } }),
    );

    if (!response.ok) {
        throw new Error(
            `Reading the notebook's items failed (${response.status}).`,
        );
    }

    return (await response.json()) as NotebookReviewCandidatesData;
}

/** What one creation request carries. */
export interface NotebookReviewRequest {
    creationKey: string;
    kinds: NotebookReviewKind[];
    /** Only when the learner chose a subset above the maximum. */
    identities?: string[];
    /** The learner's answer to the size warning. */
    confirmed?: boolean;
}

/**
 * Sends one creation and hands back the server's decision. A retry of the same
 * creation key returns the review the first one saved rather than a second one.
 */
export async function sendNotebookReview(
    notebookId: number,
    request: NotebookReviewRequest,
): Promise<NotebookReviewAnswer> {
    const response = await jsonRequest(reviewStore.url(notebookId), {
        method: 'POST',
        body: JSON.stringify({
            creation_key: request.creationKey,
            kinds: request.kinds,
            identities: request.identities ?? null,
            confirmed: request.confirmed === true,
        }),
    });

    if (!response.ok) {
        throw new Error(`Creating the review failed (${response.status}).`);
    }

    return (await response.json()) as NotebookReviewAnswer;
}
