import { describe, expect, it } from 'vite-plus/test';
import {
    attentionDomId,
    attentionFieldId,
    attentionNoteId,
    scanAttention,
} from '@/lib/attention';
import { emptyFeedbackValues } from '@/lib/feedback';
import type { PracticeQuestionForm } from '@/types';

function question(
    uid: string,
    feedback: Partial<PracticeQuestionForm['feedback']> = {},
): PracticeQuestionForm {
    return {
        uid,
        question_html: '<p>Question?</p>',
        answer_html: '',
        feedback: { ...emptyFeedbackValues(), ...feedback },
    };
}

/** A mistake row: what was said, its correction and the optional why. */
function mistake(
    id: string,
    text: string,
    correction = '',
    reason = '',
): PracticeQuestionForm['feedback']['mistake'][number] {
    return { id, text, correction_html: correction, reason };
}

/** A vocabulary row: the word, its meaning and an optional example. */
function word(
    id: string,
    text: string,
    meaning = '',
    example = '',
): PracticeQuestionForm['feedback']['vocabulary'][number] {
    return { id, text, meaning, example_html: example };
}

describe('scanAttention', () => {
    it('counts a mistake that has no correction yet', () => {
        const scan = scanAttention([
            question('q1', { mistake: [mistake('m1', 'I am agree')] }),
        ]);

        expect(scan.count).toBe(1);
        expect(scan.first).toEqual({
            questionUid: 'q1',
            boxType: 'mistake',
            entryId: 'm1',
        });
    });

    it('leaves alone a mistake that has a correction', () => {
        const scan = scanAttention([
            question('q1', {
                mistake: [mistake('m1', 'I am agree', 'I agree')],
            }),
        ]);

        expect(scan.count).toBe(0);
        expect(scan.first).toBeNull();
    });

    it('counts a word that has no meaning yet', () => {
        const scan = scanAttention([
            question('q1', { vocabulary: [word('v1', 'booking')] }),
        ]);

        expect(scan.count).toBe(1);
        expect(scan.first?.boxType).toBe('vocabulary');
    });

    it('never counts a phrase, which asks for nothing', () => {
        const scan = scanAttention([
            question('q1', {
                phrase: [
                    {
                        id: 'p1',
                        text: 'Could I have the bill?',
                        meaning: '',
                        example_html: '',
                    },
                ],
            }),
        ]);

        expect(scan.count).toBe(0);
    });

    it('skips a row that holds nothing at all', () => {
        const scan = scanAttention([
            question('q1', { mistake: [mistake('m1', '')] }),
        ]);

        expect(scan.count).toBe(0);
    });

    it('counts the rows that hold something among blank ones', () => {
        const scan = scanAttention([
            question('q1', {
                mistake: [mistake('m1', ''), mistake('m2', 'I am agree')],
            }),
        ]);

        expect(scan.count).toBe(1);
        expect(scan.first?.entryId).toBe('m2');
    });

    it('counts a word whose only content is its example', () => {
        const scan = scanAttention([
            question('q1', { vocabulary: [word('v1', '', '', 'I made it.')] }),
        ]);

        expect(scan.count).toBe(1);
    });

    it('reads the questions in order, mistakes before words', () => {
        const scan = scanAttention([
            question('q1', {
                vocabulary: [word('v1', 'booking')],
                mistake: [mistake('m1', 'I am agree')],
            }),
            question('q2', { mistake: [mistake('m2', 'He go')] }),
        ]);

        expect(scan.count).toBe(3);
        expect(scan.first).toEqual({
            questionUid: 'q1',
            boxType: 'mistake',
            entryId: 'm1',
        });
    });

    it('counts nothing when every box is empty', () => {
        const scan = scanAttention([question('q1')]);

        expect(scan.count).toBe(0);
        expect(scan.first).toBeNull();
    });
});

describe('the ids focus is sent to', () => {
    it('builds the field id the question card gives a box', () => {
        expect(attentionFieldId('q1', 'vocabulary')).toBe(
            'feedback-q1-vocabulary',
        );
    });

    it('builds the note id beside one row', () => {
        expect(attentionNoteId('feedback-q1-mistake', 'm1')).toBe(
            'feedback-q1-mistake-attention-m1',
        );
    });

    it('names the note for a focus target', () => {
        expect(
            attentionDomId({
                questionUid: 'q1',
                boxType: 'mistake',
                entryId: 'm1',
            }),
        ).toBe('feedback-q1-mistake-attention-m1');
    });
});
