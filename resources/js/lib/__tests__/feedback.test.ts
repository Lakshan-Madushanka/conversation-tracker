import { describe, expect, it } from 'vite-plus/test';
import {
    emptyFeedbackValues,
    questionDataHasContent,
    questionHasContent,
    toQuestionForm,
} from '@/lib/feedback';
import type { PracticeQuestionData, PracticeQuestionForm } from '@/types';

function form(
    overrides: Partial<PracticeQuestionForm> = {},
): PracticeQuestionForm {
    return {
        uid: 'q1',
        question_html: '',
        answer_html: '',
        feedback_html: '',
        feedback: emptyFeedbackValues(),
        ...overrides,
    };
}

function saved(
    overrides: Partial<PracticeQuestionData> = {},
): PracticeQuestionData {
    return {
        id: 1,
        position: 1,
        question_html: '',
        answer_html: '',
        feedback_html: '',
        feedback: {},
        ...overrides,
    };
}

describe('questionHasContent', () => {
    it('counts overall feedback as content on its own', () => {
        expect(
            questionHasContent(form({ feedback_html: '<p>Good effort.</p>' })),
        ).toBe(true);
    });

    it('treats formatting-only overall feedback as empty', () => {
        expect(questionHasContent(form({ feedback_html: '<p></p><br>' }))).toBe(
            false,
        );
    });

    it('is false for a question holding nothing at all', () => {
        expect(questionHasContent(form())).toBe(false);
    });
});

describe('questionDataHasContent', () => {
    it('counts overall feedback as content on its own', () => {
        expect(
            questionDataHasContent(
                saved({ feedback_html: '<p>Good effort.</p>' }),
            ),
        ).toBe(true);
    });

    it('is false for a question holding nothing at all', () => {
        expect(questionDataHasContent(saved())).toBe(false);
    });
});

describe('toQuestionForm', () => {
    it('carries the saved overall feedback into the editor', () => {
        const question = toQuestionForm(
            saved({ feedback_html: '<p>Good effort.</p>' }),
            'q1',
        );

        expect(question.feedback_html).toBe('<p>Good effort.</p>');
    });
});
