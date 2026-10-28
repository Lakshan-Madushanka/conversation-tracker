import { describe, expect, it } from 'vite-plus/test';
import { readingEntrySummary, readingSections } from '@/lib/reading';
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

    it('reads every example entry, keeping its formatting', () => {
        const [vocabulary] = readingSections(
            question({
                vocabulary: [
                    {
                        id: 'v1',
                        text: 'soup',
                        meaning: 'a liquid food',
                        example_html: [
                            {
                                id: 'e1',
                                text: 'A warm soup.',
                                html: 'A <strong>warm</strong> soup.',
                            },
                            {
                                id: 'e2',
                                text: 'Soup of the day.',
                                html: 'Soup of the <em>day</em>.',
                            },
                        ],
                    },
                ],
            }),
        );

        expect(vocabulary.entries[0].details).toEqual([
            { value: 'a liquid food', formatted: false },
            { value: 'A <strong>warm</strong> soup.', formatted: true },
            { value: 'Soup of the <em>day</em>.', formatted: true },
        ]);
    });

    it('reads an example saved as one piece of markup', () => {
        const [vocabulary] = readingSections(
            question({
                vocabulary: [
                    {
                        id: 'v1',
                        text: 'soup',
                        meaning: '',
                        example_html: 'I made a <em>reservation</em>.',
                    },
                ],
            }),
        );

        expect(vocabulary.entries[0].details).toEqual([
            { value: 'I made a <em>reservation</em>.', formatted: true },
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

    it('gives a table box its editor columns and one row per entry', () => {
        const [vocabulary] = readingSections(
            question({
                vocabulary: [
                    {
                        id: 'v1',
                        text: 'soup',
                        meaning: 'a liquid food',
                        example_html: '<p>A warm soup.</p>',
                    },
                ],
            }),
        );

        expect(vocabulary.table?.columns).toEqual([
            { header: 'Word' },
            { header: 'Meaning' },
            { header: 'Example (optional)' },
        ]);
        expect(vocabulary.table?.rows[0]?.map((cell) => cell.parts)).toEqual([
            [{ value: 'soup', formatted: false }],
            [{ value: 'a liquid food', formatted: false }],
            [{ value: '<p>A warm soup.</p>', formatted: true }],
        ]);
    });

    it('summarises the entries of a question in the editor order', () => {
        const sections = readingSections(
            question({
                natural_version: '<p>I&#039;d like the soup.</p>',
                grammar_note: [{ id: 'g1', html: '<p>Use could.</p>' }],
                phrase: [
                    {
                        id: 'p1',
                        text: 'if it is possible',
                        meaning: '',
                        example_html: '',
                    },
                ],
                vocabulary: [
                    {
                        id: 'v1',
                        text: 'soup',
                        meaning: 'a liquid food',
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

        expect(readingEntrySummary(sections)).toBe(
            '1 mistake · 1 word · 1 phrase · 1 grammar note · 1 natural version',
        );
    });

    it('has no summary when every box is empty', () => {
        expect(readingEntrySummary(readingSections(question()))).toBe('');
    });
});
