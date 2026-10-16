import type { FeedbackTone } from '@/components/SectionLabel.vue';
import { isRichTextBlank } from '@/lib/rich-text';
import type {
    FeedbackType,
    FeedbackValues,
    PracticeQuestionData,
    PracticeQuestionForm,
} from '@/types';

export interface FeedbackBoxConfig {
    /** Backend key for this box. */
    type: FeedbackType;
    /** The box's name, shown as its coloured tag and used as its accessible label. */
    label: string;
    /** Colour tone reused from the session reading page. */
    tone: FeedbackTone;
    /** Rich boxes use the shared editor; the rest are plain text. */
    rich: boolean;
    /** One-line hint shown while the box is collapsed and empty. */
    hint: string;
    /** Placeholder inside the field. */
    placeholder: string;
}

/** The five feedback boxes, in the order they are shown on a question card. */
export const feedbackBoxes: readonly FeedbackBoxConfig[] = [
    {
        type: 'mistake',
        label: 'Mistakes and corrections',
        tone: 'mistakes',
        rich: false,
        hint: 'Add corrections from the tutor',
        placeholder: 'e.g. I go → I went',
    },
    {
        type: 'vocabulary',
        label: 'New vocabulary',
        tone: 'vocabulary',
        rich: false,
        hint: 'Add new words',
        placeholder: 'e.g. reservation',
    },
    {
        type: 'phrase',
        label: 'New phrases and expressions',
        tone: 'phrases',
        rich: false,
        hint: 'Add new phrases',
        placeholder: 'e.g. Could I have the bill, please?',
    },
    {
        type: 'grammar_note',
        label: 'Grammar notes',
        tone: 'grammar',
        rich: true,
        hint: 'Add a grammar explanation',
        placeholder: 'Explain the grammar point, with formatting…',
    },
    {
        type: 'natural_version',
        label: 'A more natural version',
        tone: 'natural',
        rich: true,
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
        mistake: '',
        vocabulary: '',
        phrase: '',
        grammar_note: '',
        natural_version: '',
    };
}

/** Whether a single box holds content (formatting-only counts as empty). */
export function isFeedbackValueBlank(
    type: FeedbackType,
    value: string,
): boolean {
    return feedbackBoxFor(type).rich
        ? isRichTextBlank(value)
        : value.trim().length === 0;
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
        ([type, value]) =>
            isFeedbackValueBlank(type as FeedbackType, value) === false,
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
        feedback: { ...emptyFeedbackValues(), ...question.feedback },
    };
}
