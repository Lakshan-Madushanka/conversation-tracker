import { describe, expect, it } from 'vite-plus/test';
import { readingSections } from '@/lib/reading';
import type { PracticeQuestionData } from '@/types';

function question(
    feedback: PracticeQuestionData['feedback'] = {},
): PracticeQuestionData {
    return {
        id: 1,
        position: 1,
        question_html: '<p>What would you like to start with?</p>',
        answer_html: '<p>I want a soup.</p>',
        feedback,
    };
}

describe('the sections a question can be read as', () => {
    it('leaves out the boxes that hold nothing', () => {
        expect(readingSections(question())).toEqual([]);
        expect(
            readingSections(question({ mistake: [], natural_version: '' })),
        ).toEqual([]);
    });

    it('follows the order the boxes are shown in the editor', () => {
        const sections = readingSections(
            question({
                natural_version: '<p>I&#039;d like the soup.</p>',
                phrase: [
                    {
                        id: 'p1',
                        text: 'if it is possible',
                        meaning: '',
                        example_html: '',
                    },
                ],
                mistake: [
                    {
                        id: 'm1',
                        text: 'I want',
                        correction_html: '<strong>I&#039;d like</strong>',
                        reason: '',
                    },
                ],
            }),
        );

        expect(sections.map((section) => section.tone)).toEqual([
            'mistakes',
            'phrases',
            'natural',
        ]);
    });

    it('keeps what was said as plain text and the correction as markup', () => {
        const [mistakes] = readingSections(
            question({
                mistake: [
                    {
                        id: 'm1',
                        text: 'I want a soup',
                        correction_html:
                            '<strong>I&#039;d like a soup</strong>',
                        reason: 'Sounds more polite',
                    },
                ],
            }),
        );

        expect(mistakes.entries).toEqual([
            {
                text: { value: 'I want a soup', formatted: false },
                details: [
                    {
                        value: '<strong>I&#039;d like a soup</strong>',
                        formatted: true,
                    },
                    { value: 'Sounds more polite', formatted: false },
                ],
            },
        ]);
    });

    it('drops the columns that hold nothing', () => {
        const [vocabulary] = readingSections(
            question({
                vocabulary: [
                    {
                        id: 'v1',
                        text: 'soup',
                        meaning: 'a liquid food',
                        example_html: '',
                    },
                ],
            }),
        );

        expect(vocabulary.entries[0].details).toEqual([
            { value: 'a liquid food', formatted: false },
        ]);
    });

    it('reads every grammar note as its own entry', () => {
        const [grammar] = readingSections(
            question({
                grammar_note: [
                    { id: 'g1', html: '<p>Use <strong>could</strong>.</p>' },
                    { id: 'g2', html: '<p>Say <em>I&#039;d like</em>.</p>' },
                ],
            }),
        );

        expect(grammar.entries).toHaveLength(2);
        expect(grammar.entries[1].text).toEqual({
            value: '<p>Say <em>I&#039;d like</em>.</p>',
            formatted: true,
        });
        expect(grammar.entries[1].details).toEqual([]);
    });

    it('reads the natural version as one piece of markup', () => {
        const [natural] = readingSections(
            question({ natural_version: '<p>I&#039;d like the soup.</p>' }),
        );

        expect(natural.entries).toEqual([
            {
                text: {
                    value: '<p>I&#039;d like the soup.</p>',
                    formatted: true,
                },
                details: [],
            },
        ]);
    });
});
