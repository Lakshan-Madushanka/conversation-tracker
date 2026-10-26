import { describe, expect, it } from 'vite-plus/test';
import {
    matchKindLabel,
    matchTone,
    searchMatchHref,
} from '@/lib/library-search';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type { SearchMatch } from '@/types';

function match(overrides: Partial<SearchMatch> = {}): SearchMatch {
    return {
        key: 'topic',
        kind: 'topic',
        type: null,
        question_position: null,
        entry_id: null,
        entry_position: null,
        segments: [{ text: 'Travel', match: true }],
        ...overrides,
    };
}

function query(href: string): URLSearchParams {
    return new URL(href, 'http://localhost').searchParams;
}

describe('where a matching item sends the learner', () => {
    it('opens a finished session at the item', () => {
        const href = searchMatchHref(
            { id: 7, status: 'finished' },
            match({
                key: 'entry:mistake:m1',
                kind: 'entry',
                type: 'mistake',
                question_position: 2,
                entry_id: 'm1',
                entry_position: 0,
            }),
        );

        expect(href.startsWith(sessionsShow.url(7))).toBe(true);

        const params = query(href);

        expect(params.get('question')).toBe('2');
        expect(params.get('field')).toBe('entry');
        expect(params.get('type')).toBe('mistake');
        expect(params.get('entry')).toBe('m1');
        expect(params.get('position')).toBe('0');
    });

    it('opens an unfinished session in its editor at the item', () => {
        const href = searchMatchHref(
            { id: 9, status: 'unfinished' },
            match({
                key: 'answer:1',
                kind: 'answer',
                question_position: 1,
            }),
        );

        expect(href.startsWith(sessionsEdit.url(9))).toBe(true);
        expect(query(href).get('field')).toBe('answer');
    });

    it('opens a topic match at the top of the session', () => {
        expect(searchMatchHref({ id: 7, status: 'finished' }, match())).toBe(
            sessionsShow.url(7),
        );
    });

    it('names the question and answer fields', () => {
        const question = searchMatchHref(
            { id: 7, status: 'finished' },
            match({ kind: 'question', question_position: 3 }),
        );
        const answer = searchMatchHref(
            { id: 7, status: 'finished' },
            match({ kind: 'answer', question_position: 3 }),
        );

        expect(query(question).get('field')).toBe('question');
        expect(query(answer).get('field')).toBe('answer');
    });
});

describe('how a matching item is labelled', () => {
    it('uses the same section name as the editor', () => {
        expect(matchKindLabel(match())).toBe('Topic');
        expect(matchKindLabel(match({ kind: 'question' }))).toBe(
            'Tutor’s question',
        );
        expect(matchKindLabel(match({ kind: 'answer' }))).toBe('My answer');
        expect(
            matchKindLabel(match({ kind: 'entry', type: 'grammar_note' })),
        ).toBe('Grammar notes');
        expect(
            matchKindLabel(match({ kind: 'entry', type: 'natural_version' })),
        ).toBe('A more natural version');
    });

    it('wears the same colour tag as its section', () => {
        expect(matchTone(match())).toBe('neutral');
        expect(matchTone(match({ kind: 'entry', type: 'mistake' }))).toBe(
            'mistakes',
        );
        expect(matchTone(match({ kind: 'entry', type: 'vocabulary' }))).toBe(
            'vocabulary',
        );
        expect(matchTone(match({ kind: 'entry', type: 'phrase' }))).toBe(
            'phrases',
        );
    });
});
