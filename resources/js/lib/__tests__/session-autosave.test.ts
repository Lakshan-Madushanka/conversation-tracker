import { describe, expect, it } from 'vite-plus/test';
import { cleanFeedback } from '@/lib/session-autosave';
import { emptyFeedbackValues } from '@/lib/feedback';

describe('cleanFeedback', () => {
    it('keeps grammar notes that hold content', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            grammar_note: [
                { id: 'n1', html: '<p>Use the past tense.</p>' },
                { id: 'n2', html: '<p>Articles come before nouns.</p>' },
            ],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.grammar_note).toHaveLength(2);
    });

    it('removes a grammar note left empty', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            grammar_note: [
                { id: 'n1', html: '<p>Keep me</p>' },
                { id: 'n2', html: '' },
            ],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.grammar_note).toHaveLength(1);
        expect(cleaned.grammar_note[0]?.id).toBe('n1');
    });

    it('removes a grammar note holding only empty formatting', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            grammar_note: [
                { id: 'n1', html: '<p></p>' },
                { id: 'n2', html: '<p>   </p>' },
                { id: 'n3', html: '<p><br></p>' },
            ],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.grammar_note).toHaveLength(0);
    });

    it('keeps other feedback boxes untouched', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            mistake: [
                {
                    id: 'm1',
                    text: 'I go → I went',
                    correction_html: '',
                    reason: '',
                },
            ],
            grammar_note: [{ id: 'n1', html: '' }],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.mistake).toHaveLength(1);
        expect(cleaned.grammar_note).toHaveLength(0);
    });
});
