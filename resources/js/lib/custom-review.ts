/**
 * The custom reviews the learner is building, as every page shares them.
 *
 * A review is a saved record from the first item onward, so the only state a
 * page needs is which review its items are marked against — the one being
 * built, or none when several are. That is read from the server with the page
 * and kept here, so a list, an open panel and a session's reading page all
 * say the same thing.
 *
 * Every add carries the whole set the learner chose, never a toggle, so a
 * double press, a retry or a second tab cannot add the same item twice.
 *
 * Where an add goes, and whether it deserves a warning first, is decided by
 * the server. This file only asks the two questions the server sends back —
 * which review, and whether that many items is what they meant — and never
 * decides either of them itself.
 */

import { ref } from 'vue';
import type {
    AutoSaveFunction,
    AutoSaveMarker,
    AutoSaveOutcome,
} from '@/composables/useAutoSave';
import { jsonRequest } from '@/lib/http';
import {
    clear as reviewClear,
    show as reviewShow,
    status as reviewStatus,
    update as reviewUpdate,
} from '@/routes/customReviews';
import {
    destroy as reviewItemsDestroy,
    store as reviewItemsStore,
} from '@/routes/customReviews/items';
import type {
    AddReviewAnswer,
    AddReviewOutcome,
    CustomReviewKind,
    CustomReviewStatusValue,
    CustomReviewSummary,
    RemoveReviewResult,
} from '@/types';

/**
 * The size a review is warned about going past. Mirrors
 * `App\Support\CustomReviewRules::RECOMMENDED_ITEMS`; the server decides in
 * the end, so this only names the number the wording is written around.
 */
export const RECOMMENDED_ITEMS = 20;

/** The review a page marks its items against, or none to agree on. */
export const reviewTarget = ref<CustomReviewSummary | null>(null);

/** Which question is on screen: the picker, the warning, or neither. */
export const asking = ref<'review' | 'size' | ''>('');

/** The reviews the picker offers, most recently created first. */
export const reviewChoices = ref<CustomReviewSummary[]>([]);

/** The size the warning names. */
export const resultingCount = ref(0);

/**
 * An add waiting on an answer. The whole request is kept, because either
 * answer is the same request with one thing settled: the review chosen, or
 * the size accepted.
 */
type Waiting = {
    kind: CustomReviewKind;
    identities: string[];
    reviewId: number | null;
    settle: ((outcome: AddReviewOutcome) => void) | null;
};

/**
 * The add the question on screen belongs to. Only one question can be open,
 * so an add started while another still waits takes it over — the earlier add
 * has written nothing, and is treated as one the learner turned down.
 */
let waiting: Waiting | null = null;

/** Takes what the server sent with the page, so the lists start in step. */
export function hydrateReviewTarget(
    target: CustomReviewSummary | null | undefined,
): void {
    reviewTarget.value = target ?? null;
}

/**
 * The address of a review's own page. `from` is where the learner came from,
 * so the review's Back button returns them there.
 */
export function reviewPageUrl(reviewId: number, from?: string): string {
    return reviewShow.url(reviewId, {
        query: from === undefined || from === '' ? undefined : { from },
    });
}

/**
 * Where the learner is standing, for the trip back from a review's page. Only
 * the path and its query travel; the server ignores anything that is not a
 * page of this app.
 */
export function currentReviewReturn(): string {
    if (typeof window === 'undefined') {
        return '/collections';
    }

    return `${window.location.pathname}${window.location.search}`;
}

/** A review item's accessible name for the controls that act on it. */
export function reviewItemName(kind: CustomReviewKind, title: string): string {
    const fallback: Record<CustomReviewKind, string> = {
        vocabulary: 'word',
        phrase: 'phrase',
        grammar_note: 'grammar note',
        mistake: 'mistake',
        question: 'question',
    };

    return title.trim() === '' ? fallback[kind] : title;
}

/** The count in words: "1 item", "5 items". */
export function reviewItemCountLabel(count: number): string {
    return `${count} ${count === 1 ? 'item' : 'items'}`;
}

/** The warning's line, naming the size the review would reach. */
export function reviewSizeMessage(count: number): string {
    return `This review will hold ${count} items. Long reviews take a while to practise.`;
}

/**
 * Whether a review holding this many items carries the size tag. The server
 * asks the same question in `CustomReviewRules::isTooMany`, so the number and
 * the boundary are the same on both sides.
 */
export function reviewIsTooMany(count: number): boolean {
    return count > RECOMMENDED_ITEMS;
}

/** The size tag's words, written the way the server writes them. */
export function reviewTooManyLabel(count: number): string {
    return `Too Many Items · ${reviewItemCountLabel(count)}`;
}

/** The status as the learner sees it, capitalised once. */
export function reviewStatusLabel(status: CustomReviewStatusValue): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

/** What a status means, in the words the page shows under the control. */
export function reviewStatusExplanation(
    status: CustomReviewStatusValue,
): string {
    switch (status) {
        case 'empty':
            return 'Empty: nothing in it yet.';
        case 'draft':
            return 'Draft: still being built.';
        case 'finished':
            return 'Finished: ready to practise.';
    }
}

/** Why an Empty review cannot be marked finished, said once for both sides. */
export const REVIEW_EMPTY_REASON =
    'Add items before marking this review finished.';

/** What identifies a review after one of its fields is saved. */
export interface ReviewMarker extends AutoSaveMarker {
    review: CustomReviewSummary;
}

/** The name on screen, as it travels to the server. */
export interface ReviewNameSnapshot {
    name: string;
}

/** The description on screen, as it travels to the server. */
export interface ReviewDescriptionSnapshot {
    description: string;
}

/** Laravel lists validation mistakes per field as one or more messages. */
function firstError(values: Record<string, unknown>): Record<string, string> {
    const errors: Record<string, string> = {};

    for (const [field, value] of Object.entries(values)) {
        if (typeof value === 'string' && value !== '') {
            errors[field] = value;
        } else if (
            Array.isArray(value) &&
            typeof value[0] === 'string' &&
            value[0] !== ''
        ) {
            errors[field] = value[0];
        }
    }

    return errors;
}

/** Sends one field, and reads the review as the server saved it. */
async function saveReviewField(
    reviewId: number,
    field: { name: string } | { description: string },
    keepalive: boolean,
): Promise<AutoSaveOutcome<ReviewMarker>> {
    const response = await jsonRequest(reviewUpdate.url(reviewId), {
        method: 'PUT',
        body: JSON.stringify(field),
        keepalive,
    });

    if (response.status === 200) {
        const body = (await response.json()) as ReviewMarker;

        return {
            outcome: 'saved',
            saved: body,
            savedAt: new Date().toISOString(),
        };
    }

    if (response.status === 422) {
        const body = (await response.json()) as {
            errors?: Record<string, unknown>;
        };

        return { outcome: 'invalid', errors: firstError(body.errors ?? {}) };
    }

    return { outcome: 'failed' };
}

/**
 * The name save the page's autosave rules run. The field is sent on its own,
 * so a refused name never holds back a description that can be saved.
 */
export function createReviewNameSaver(
    reviewId: number,
): AutoSaveFunction<ReviewNameSnapshot, ReviewMarker> {
    return (request, options) =>
        saveReviewField(
            reviewId,
            { name: request.name },
            options?.keepalive ?? false,
        );
}

/** The description save, sent apart from the name for the same reason. */
export function createReviewDescriptionSaver(
    reviewId: number,
): AutoSaveFunction<ReviewDescriptionSnapshot, ReviewMarker> {
    return (request, options) =>
        saveReviewField(
            reviewId,
            { description: request.description },
            options?.keepalive ?? false,
        );
}

/**
 * The learner's own Draft-or-Finished move. Only the review's items decide
 * whether it can be made, so a refusal arrives with the server's own words.
 */
export async function updateReviewStatus(
    reviewId: number,
    status: 'draft' | 'finished',
): Promise<CustomReviewSummary> {
    const response = await jsonRequest(reviewStatus.url(reviewId), {
        method: 'PUT',
        body: JSON.stringify({ status }),
    });

    if (response.status === 200) {
        const body = (await response.json()) as {
            review: CustomReviewSummary;
        };

        return body.review;
    }

    if (response.status === 422) {
        const body = (await response.json()) as {
            errors?: Record<string, unknown>;
        };
        const errors = firstError(body.errors ?? {});

        throw new Error(
            errors.status ?? 'This review could not be moved just now.',
        );
    }

    throw new Error('This review could not be moved just now.');
}

/**
 * One add request, answered by the server's own decision. Used on its own
 * when a page has already settled where the items go — the Undo of a removal
 * puts them back in the review it just named.
 */
export async function sendAddToReview(
    kind: CustomReviewKind,
    identities: string[],
    options: { review?: number; confirmed?: boolean } = {},
): Promise<AddReviewAnswer> {
    const response = await jsonRequest(reviewItemsStore.url(), {
        method: 'POST',
        body: JSON.stringify({
            kind,
            identities,
            review: options.review ?? null,
            confirmed: options.confirmed === true,
        }),
    });

    if (!response.ok) {
        throw new Error(`Adding to a review failed (${response.status}).`);
    }

    return (await response.json()) as AddReviewAnswer;
}

/**
 * Adds the chosen items where they belong, asking only when the server asks.
 *
 * Never throws: the caller gets `added` with what changed, `cancelled` when
 * the learner turned a question down, and `failed` when the request did not
 * go through — the case a list answers by keeping the selection and offering
 * a way to try again.
 */
export async function addItemsToReview(
    kind: CustomReviewKind,
    identities: string[],
): Promise<AddReviewOutcome> {
    const add: Waiting = { kind, identities, reviewId: null, settle: null };

    settleCancelled();
    waiting = add;

    return send(add, () =>
        sendAddToReview(kind, identities, {
            review: reviewTarget.value?.id,
        }),
    );
}

/** The picker's answer: the review the learner named, asked again in full. */
export function chooseReview(reviewId: number): void {
    const add = waiting;

    if (add === null) {
        return;
    }

    asking.value = '';
    add.reviewId = reviewId;

    void reply(add, () =>
        sendAddToReview(add.kind, add.identities, { review: reviewId }),
    );
}

/** The warning's Continue: the very same add, now confirmed. */
export function confirmAddSize(): void {
    const add = waiting;

    if (add === null) {
        return;
    }

    asking.value = '';

    void reply(add, () =>
        sendAddToReview(add.kind, add.identities, {
            review: add.reviewId ?? undefined,
            confirmed: true,
        }),
    );
}

/** Either question turned down: nothing is added, and nothing is said. */
export function cancelAdd(): void {
    settleCancelled();
    close();
}

/** Takes the chosen items out of a review again. */
export async function removeItemsFromReview(
    reviewId: number,
    kind: CustomReviewKind,
    identities: string[],
): Promise<RemoveReviewResult> {
    const response = await jsonRequest(reviewItemsDestroy.url(reviewId), {
        method: 'DELETE',
        body: JSON.stringify({ kind, identities }),
    });

    if (!response.ok) {
        throw new Error(
            `Removing from the review failed (${response.status}).`,
        );
    }

    return (await response.json()) as RemoveReviewResult;
}

/** Empties a review. The page asks for confirmation before calling this. */
export async function clearReview(reviewId: number): Promise<number> {
    const response = await jsonRequest(reviewClear.url(reviewId), {
        method: 'POST',
    });

    if (!response.ok) {
        throw new Error(`Clearing the review failed (${response.status}).`);
    }

    const result = (await response.json()) as { cleared_count: number };

    return result.cleared_count;
}

/** Sends an add and hands back whatever it came to. */
async function send(
    add: Waiting,
    request: () => Promise<AddReviewAnswer>,
): Promise<AddReviewOutcome> {
    let answer: AddReviewAnswer;

    try {
        answer = await request();
    } catch {
        finish(add);

        return { status: 'failed' };
    }

    if (answer.outcome === 'added') {
        // The markers on the page follow the review the items went into.
        reviewTarget.value = answer.review;
        finish(add);

        return { status: 'added', answer };
    }

    // Another add has taken the question over since this one was sent, so
    // nothing is asked about this one: its items simply were not added.
    if (waiting !== add) {
        return { status: 'cancelled' };
    }

    if (answer.outcome === 'needs_review_choice') {
        reviewChoices.value = answer.reviews;
        asking.value = 'review';
    } else {
        resultingCount.value = answer.resulting_count;
        asking.value = 'size';
    }

    return new Promise<AddReviewOutcome>((settle) => {
        add.settle = settle;
    });
}

/** Carries on with an add the learner has just answered for. */
async function reply(
    add: Waiting,
    request: () => Promise<AddReviewAnswer>,
): Promise<void> {
    const settle = add.settle;
    add.settle = null;

    const outcome = await send(add, request);

    settle?.(outcome);
}

/** Clears both questions, so nothing is left half-asked. */
function close(): void {
    asking.value = '';
    reviewChoices.value = [];
    resultingCount.value = 0;
}

/** The add on screen is over without its answer, so its caller is told. */
function settleCancelled(): void {
    const pending = waiting;

    if (pending === null) {
        return;
    }

    waiting = null;
    pending.settle?.({ status: 'cancelled' });
}

/** An add that has been answered, or that nothing more can be asked about. */
function finish(add: Waiting): void {
    if (waiting !== add) {
        return;
    }

    waiting = null;
    close();
}
