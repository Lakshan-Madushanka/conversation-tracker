import type { FeedbackTone } from '@/components/SectionLabel.vue';
import { newId } from '@/lib/id';
import { isRichTextBlank } from '@/lib/rich-text';
import type {
    FeedbackEntry,
    FeedbackType,
    FeedbackValue,
    FeedbackValues,
    GrammarNote,
    ListFeedbackType,
    PracticeQuestionData,
    PracticeQuestionForm,
} from '@/types';

/**
 * How a box holds its content:
 *
 * - `list` — plain text entries, one per pasted line (mistakes, vocabulary,
 *   phrases).
 * - `notes` — many formatted notes, one per paste, never split by line.
 * - `text` — one piece of formatted text (the natural version).
 */
export type FeedbackBoxKind = 'list' | 'notes' | 'text';

export interface FeedbackBoxConfig {
    /** Backend key for this box. */
    type: FeedbackType;
    /** The box's name, shown as its coloured tag and used as its accessible label. */
    label: string;
    /** Colour tone reused from the session reading page. */
    tone: FeedbackTone;
    kind: FeedbackBoxKind;
    /** One-line hint shown while the box is collapsed and empty. */
    hint: string;
    /** Placeholder inside the field. */
    placeholder: string;
}

/** Types whose boxes hold a list of entries split from pasted lines. */
export const LIST_TYPES: readonly ListFeedbackType[] = [
    'mistake',
    'vocabulary',
    'phrase',
];

/** The five feedback boxes, in the order they are shown on a question card. */
export const feedbackBoxes: readonly FeedbackBoxConfig[] = [
    {
        type: 'mistake',
        label: 'Mistakes and corrections',
        tone: 'mistakes',
        kind: 'list',
        hint: 'Add corrections from the tutor',
        placeholder: 'e.g. I go → I went',
    },
    {
        type: 'vocabulary',
        label: 'New vocabulary',
        tone: 'vocabulary',
        kind: 'list',
        hint: 'Add new words',
        placeholder: 'e.g. reservation',
    },
    {
        type: 'phrase',
        label: 'New phrases and expressions',
        tone: 'phrases',
        kind: 'list',
        hint: 'Add new phrases',
        placeholder: 'e.g. Could I have the bill, please?',
    },
    {
        type: 'grammar_note',
        label: 'Grammar notes',
        tone: 'grammar',
        kind: 'notes',
        hint: 'Add a grammar explanation',
        placeholder: 'Explain the grammar point, with formatting…',
    },
    {
        type: 'natural_version',
        label: 'A more natural version',
        tone: 'natural',
        kind: 'text',
        hint: 'Add a more natural way to say it',
        placeholder: 'Paste a more natural version…',
    },
];

export function feedbackBoxFor(type: FeedbackType): FeedbackBoxConfig {
    const box = feedbackBoxes.find((candidate) => candidate.type === type);

    if (!box) {
        throw new Error(`Unknown feedback type: ${type}`);
    }

    return box;
}

/** A full set of empty boxes, used for new questions. */
export function emptyFeedbackValues(): FeedbackValues {
    return {
        mistake: [],
        vocabulary: [],
        phrase: [],
        grammar_note: [],
        natural_version: '',
    };
}

/** A new list entry, ready to be edited or saved. */
export function newEntry(text: string): FeedbackEntry {
    return { id: newId(), text };
}

/** A new grammar note, ready to be edited or saved. */
export function newNote(html: string): GrammarNote {
    return { id: newId(), html };
}

/**
 * Whether a single box holds content (formatting-only counts as empty).
 *
 * Notes carry HTML and entries carry plain text, so the shape of the items
 * says which check to run.
 */
export function isFeedbackValueBlank(value: FeedbackValue): boolean {
    if (typeof value === 'string') {
        return isRichTextBlank(value);
    }

    if (isGrammarNotes(value)) {
        return value.every((note) => isRichTextBlank(note.html));
    }

    return value.every((entry) => entry.text.trim().length === 0);
}

/** Grammar notes carry HTML; entries carry plain text. Never mixed in a box. */
function isGrammarNotes(
    items: FeedbackEntry[] | GrammarNote[],
): items is GrammarNote[] {
    return items.every((item) => 'html' in item);
}

/** Replace the value of one box, whatever kind of content it holds. */
export function setFeedbackValue<T extends FeedbackType>(
    values: FeedbackValues,
    type: T,
    value: FeedbackValues[T],
): void {
    values[type] = value;
}

/** Whether a question has anything typed into it at all. */
export function questionHasContent(question: PracticeQuestionForm): boolean {
    if (
        !isRichTextBlank(question.question_html) ||
        !isRichTextBlank(question.answer_html)
    ) {
        return true;
    }

    return Object.entries(question.feedback).some(
        ([, value]) => isFeedbackValueBlank(value) === false,
    );
}

/**
 * Turns a saved question from the backend into an editable one, filling in any
 * boxes the backend did not send.
 */
export function toQuestionForm(
    question: PracticeQuestionData,
    uid: string,
): PracticeQuestionForm {
    return {
        uid,
        question_html: question.question_html,
        answer_html: question.answer_html,
        feedback: normalizeFeedback(question.feedback),
    };
}

/**
 * Give every box its own value, so a question the backend left partly empty
 * still opens with the shapes the editor works with.
 */
function normalizeFeedback(feedback: Partial<FeedbackValues>): FeedbackValues {
    return {
        mistake: feedback.mistake ?? [],
        vocabulary: feedback.vocabulary ?? [],
        phrase: feedback.phrase ?? [],
        grammar_note: feedback.grammar_note ?? [],
        natural_version: feedback.natural_version ?? '',
    };
}

export type { ListFeedbackType };
