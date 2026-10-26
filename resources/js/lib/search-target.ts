import { nextTick } from 'vue';
import { feedbackBoxes } from '@/lib/feedback';
import type { FeedbackType } from '@/types';

/**
 * The item a search result asked the session page to show. It travels in the
 * address (`question`, `field`, `type`, `entry`, `position`) so opening a
 * result in a new tab, or refreshing it, still lands on the same item.
 */
export interface SearchTarget {
    question: number | null;
    field: 'question' | 'answer' | 'entry' | null;
    type: FeedbackType | null;
    entry: string | null;
    position: number | null;
}

const FIELDS = ['question', 'answer', 'entry'] as const;

/** Read the target from an address, ignoring anything it cannot understand. */
export function readSearchTarget(url: string): SearchTarget | null {
    const params = new URL(url, 'http://localhost').searchParams;

    // Guided mode keeps its own question in the address; it is not a search
    // result and must never be mistaken for one.
    if (params.has('guided')) {
        return null;
    }

    const question = positiveInteger(params.get('question'));
    const field = fieldFrom(params.get('field'));
    const type = typeFrom(params.get('type'));
    const entry = params.get('entry');
    const position = nonNegativeInteger(params.get('position'));

    if (question === null && field === null) {
        return null;
    }

    return {
        question,
        field,
        type,
        entry: entry === null || entry === '' ? null : entry,
        position,
    };
}

/** The id of a feedback row in the editor, built the way the box builds it. */
export function editorEntryId(
    questionUid: string,
    type: FeedbackType,
    entryId: string,
): string {
    const fieldId = `feedback-${questionUid}-${type}`;

    if (type === 'grammar_note') {
        return `${fieldId}-${entryId}`;
    }

    if (type === 'natural_version') {
        return fieldId;
    }

    return `${fieldId}-row-${entryId}`;
}

/**
 * Scroll an element into view, focus it and mark it briefly. A retry gives a
 * card that has just been expanded time to reach the page. The callback says
 * whether the item was found, so the page can explain a calm change instead.
 */
export function revealSearchTarget(
    id: string,
    onResult?: (found: boolean) => void,
    attempts = 12,
): void {
    void nextTick(() => {
        requestAnimationFrame(() => {
            const element = document.getElementById(id);

            if (element !== null) {
                highlight(element);
                onResult?.(true);

                return;
            }

            if (attempts > 0) {
                revealSearchTarget(id, onResult, attempts - 1);

                return;
            }

            onResult?.(false);
        });
    });
}

function highlight(element: HTMLElement): void {
    if (typeof element.scrollIntoView === 'function') {
        element.scrollIntoView({ block: 'center' });
    }

    element.classList.add('search-target');

    if (!element.hasAttribute('tabindex')) {
        element.setAttribute('tabindex', '-1');
    }

    element.focus({ preventScroll: true });

    window.setTimeout(() => element.classList.remove('search-target'), 2400);
}

function fieldFrom(value: string | null): SearchTarget['field'] {
    return FIELDS.includes(value as (typeof FIELDS)[number])
        ? (value as SearchTarget['field'])
        : null;
}

function typeFrom(value: string | null): FeedbackType | null {
    if (value === null) {
        return null;
    }

    return feedbackBoxes.some((box) => box.type === value)
        ? (value as FeedbackType)
        : null;
}

function positiveInteger(value: string | null): number | null {
    if (value === null || !/^[1-9]\d*$/u.test(value)) {
        return null;
    }

    return Number(value);
}

/** Entry positions are zero-based, so zero is a real place in a box. */
function nonNegativeInteger(value: string | null): number | null {
    if (value === null || !/^\d+$/u.test(value)) {
        return null;
    }

    return Number(value);
}
