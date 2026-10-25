/**
 * The guided "paste in order" mode: the fixed order of the seven steps, the
 * copy shown above each box, and the address that keeps the learner where they
 * are when the page is refreshed.
 *
 * Guided mode changes no data of its own — it is another view over the same
 * editor fields, so everything the normal layout saves applies here untouched.
 */

import {
    feedbackBoxFor,
    feedbackBoxes,
    type FeedbackBoxKind,
} from '@/lib/feedback';
import { isRichTextBlank } from '@/lib/rich-text';
import type { FeedbackType, FeedbackValues } from '@/types';

/** One of the seven steps a question is filled in through. */
export type GuidedStepId =
    | 'question'
    | 'answer'
    | 'mistake'
    | 'vocabulary'
    | 'phrase'
    | 'grammar_note'
    | 'natural_version';

/** Anything guided mode can show: a step, the topic, or the review. */
export type GuidedView = GuidedStepId | 'topic' | 'review';

/** Where guided mode is pointing: a 1-based question, and what it shows. */
export interface GuidedLocation {
    question: number;
    view: GuidedView;
}

/** One step of a question, with the words the learner sees above its box. */
export interface GuidedStep {
    id: GuidedStepId;
    /** Short name shown beside the question number. */
    label: string;
    heading: string;
    hint: string;
}

/** A box's own label, so a step never repeats the name it is given. */
function boxLabel(type: FeedbackType): string {
    return feedbackBoxFor(type).label;
}

/**
 * The seven steps, in the order a tutor's chat is pasted in. This is the one
 * list the mode follows: order, headings and hints all come from here.
 */
export const GUIDED_STEPS: readonly GuidedStep[] = [
    {
        id: 'question',
        label: 'Tutor’s question',
        heading: 'Paste the tutor’s question',
        hint: 'Copy the question exactly as the tutor asked it.',
    },
    {
        id: 'answer',
        label: 'My answer',
        heading: 'Paste your own answer',
        hint: 'Add what you replied, even where it needed fixing.',
    },
    {
        id: 'mistake',
        label: boxLabel('mistake'),
        heading: 'Paste the tutor’s corrections',
        hint: 'One line for each fix, for example: I go → I went.',
    },
    {
        id: 'vocabulary',
        label: boxLabel('vocabulary'),
        heading: 'Paste the new words',
        hint: 'One line for each word, with its meaning after a dash.',
    },
    {
        id: 'phrase',
        label: boxLabel('phrase'),
        heading: 'Paste the new phrases',
        hint: 'One line for each phrase, with how it is used.',
    },
    {
        id: 'grammar_note',
        label: boxLabel('grammar_note'),
        heading: 'Paste the grammar notes',
        hint: 'One note for each grammar point, formatting and all.',
    },
    {
        id: 'natural_version',
        label: boxLabel('natural_version'),
        heading: 'Paste a more natural version',
        hint: 'The way a native speaker would say the same thing.',
    },
];

/** The words shown on the topic step, which is not one of the seven. */
export const GUIDED_TOPIC = {
    label: 'Topic',
    heading: 'Choose a topic',
    hint: 'Your work starts saving as soon as a topic is set.',
} as const;

/** The heading of the screen that shows a whole question for review. */
export function guidedReviewHeading(questionNumber: number): string {
    return `Review question ${questionNumber}`;
}

const BOX_TYPES = new Set<string>(feedbackBoxes.map((box) => box.type));

const VIEWS = new Set<string>([
    'topic',
    'review',
    ...feedbackBoxes.map((box) => box.type),
    'question',
    'answer',
]);

/** The feedback box a view shows, or null for the prose steps and screens. */
export function feedbackTypeFor(view: GuidedView): FeedbackType | null {
    return BOX_TYPES.has(view) ? (view as FeedbackType) : null;
}

/** Whether a string read from an address is one guided mode can show. */
export function isGuidedView(value: string): value is GuidedView {
    return VIEWS.has(value);
}

/** The guided address for a question, on the editor's own path. */
export function guidedUrl(path: string, location: GuidedLocation): string {
    const cleanPath = path.split('?')[0] ?? path;

    return `${cleanPath}?guided=1&question=${location.question}&step=${location.view}`;
}

/**
 * The plain editor address: the same page without guided mode.
 */
export function editorUrl(path: string): string {
    return path.split('?')[0] ?? path;
}

/**
 * Reads guided mode out of an address. An address without the guided flag is
 * the plain editor, which is reported as null rather than a guess.
 */
export function readGuidedLocation(url: string): GuidedLocation | null {
    const at = url.indexOf('?');
    const params = new URLSearchParams(at === -1 ? '' : url.slice(at + 1));

    if (params.get('guided') !== '1') {
        return null;
    }

    const asked = Number.parseInt(params.get('question') ?? '', 10);
    const view = params.get('step') ?? '';

    return {
        question: Number.isFinite(asked) && asked >= 1 ? asked : 1,
        view: isGuidedView(view) ? view : 'question',
    };
}

/**
 * How many entries a step's box holds, for the quiet count under it. Steps
 * that hold prose rather than entries have no count to show.
 */
export function guidedEntryCount(
    question: { feedback: Partial<FeedbackValues> },
    view: GuidedView,
): number | null {
    const type = feedbackTypeFor(view);

    if (type === null) {
        return null;
    }

    if (type === 'grammar_note') {
        const notes = question.feedback.grammar_note;

        if (notes === undefined) {
            return null;
        }

        return notes.filter((note) => !isRichTextBlank(note.html)).length;
    }

    const value = question.feedback[type];

    if (value === undefined || typeof value === 'string') {
        return null;
    }

    return value.length;
}

/** What one entry is called in a box of this kind: a row, a note, an entry. */
function countNoun(kind: FeedbackBoxKind, count: number): string {
    if (kind === 'table') {
        return count === 1 ? 'row' : 'rows';
    }

    if (kind === 'notes') {
        return count === 1 ? 'note' : 'notes';
    }

    return count === 1 ? 'entry' : 'entries';
}

/** A small line about what the box already holds, for a guided step. */
export function guidedCountLine(
    question: { feedback: Partial<FeedbackValues> },
    view: GuidedView,
): string | null {
    const type = feedbackTypeFor(view);

    if (type === null) {
        return null;
    }

    const count = guidedEntryCount(question, view);

    if (count === null) {
        return null;
    }

    return `${count} ${countNoun(feedbackBoxFor(type).kind, count)} so far`;
}
