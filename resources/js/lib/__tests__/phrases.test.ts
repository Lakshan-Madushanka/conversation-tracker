import { describe, expect, it } from 'vite-plus/test';
import {
    ATTENTION_MORE_THAN_ONE,
    parsePhraseLine,
    phraseAttention,
    phraseDuplicateKey,
    phrasesFromPaste,
} from '@/lib/phrases';
import { FeedbackEntry } from '@/types';

/** The meaning of a parsed line, so tests read naturally. */
function meaningOf(line: string): string | FeedbackEntry[] {
    return parsePhraseLine(line).meaning;
}

describe('splitting a pasted line', () => {
    it.each(['→', '->', '=>'])('splits at an arrow (%s)', (arrow) => {
        const parsed = parsePhraseLine(
            `off the beaten track ${arrow} a place tourists never go`,
        );

        expect(parsed.text).toBe('off the beaten track');
        expect(parsed.meaning).toBe('a place tourists never go');
        expect(parsed.example_html).toBe('');
    });

    it.each(['-', '--', '–', '—'])('splits at a spaced dash (%s)', (dash) => {
        const line = `a long way ${dash} far away`;

        expect(parsePhraseLine(line).text).toBe('a long way');
        expect(meaningOf(line)).toBe('far away');
    });

    it('keeps a phrase with commas and a question mark whole', () => {
        const parsed = parsePhraseLine(
            'Excuse me, could I have the menu, please?',
        );

        expect(parsed.text).toBe('Excuse me, could I have the menu, please?');
        expect(parsed.meaning).toBe('');
    });

    it('keeps a hyphenated phrase whole', () => {
        const parsed = parsePhraseLine('a so-so restaurant');

        expect(parsed.text).toBe('a so-so restaurant');
        expect(parsed.meaning).toBe('');
    });

    it('removes a leading bullet before it looks for a separator', () => {
        const line = '- Could I book a table? - Reserving ahead';

        expect(parsePhraseLine(line).text).toBe('Could I book a table?');
        expect(meaningOf(line)).toBe('Reserving ahead');
    });

    it('removes leading numbering before it looks for a separator', () => {
        const parsed = parsePhraseLine('1. Is service included?');

        expect(parsed.text).toBe('Is service included?');
        expect(parsed.meaning).toBe('');
    });
});

describe('needing attention', () => {
    it('never asks for a meaning when there is no separator', () => {
        const parsed = parsePhraseLine('What do you recommend?');

        expect(phraseAttention(parsed)).toBeNull();
    });

    it('asks to split a line with more than one separator', () => {
        const parsed = parsePhraseLine('a - b - c');

        expect(parsed.text).toBe('a - b - c');
        expect(parsed.meaning).toBe('');
        expect(phraseAttention(parsed)).toBe(ATTENTION_MORE_THAN_ONE);
    });

    it('is cleared once a meaning is present', () => {
        const parsed = parsePhraseLine('a long way - far away');

        expect(phraseAttention(parsed)).toBeNull();
    });
});

describe('reading a pasted table', () => {
    const table = `
        <table>
            <tr><td>off the beaten track</td><td>away from the usual places</td><td>We found a quiet cafe.</td></tr>
            <tr><td>Could I have the bill?</td><td>a polite way to ask</td><td></td></tr>
        </table>`;

    it('maps the cells onto the three columns', () => {
        const { rows } = phrasesFromPaste('', table);

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('off the beaten track');
        expect(rows[0].meaning).toBe('away from the usual places');
        expect(rows[0].example_html).toBe('We found a quiet cafe.');
        expect(rows[1].example_html).toBe('');
    });

    it('keeps bold and italic in an example cell only', () => {
        const rich = `
            <table>
                <tr>
                    <td><b>off the beaten track</b></td>
                    <td>away from the usual places</td>
                    <td>We found a <em>quiet cafe</em>.</td>
                </tr>
            </table>`;

        const { rows } = phrasesFromPaste('', rich);

        expect(rows[0].text).toBe('off the beaten track');
        expect(rows[0].meaning).toBe('away from the usual places');
        expect(rows[0].example_html).toBe('We found a <em>quiet cafe</em>.');
    });

    it('joins extra columns into the example', () => {
        const wide = `
            <table>
                <tr>
                    <td>a long way</td><td>far away</td><td>It is</td><td>a long way.</td>
                </tr>
            </table>`;

        const { rows } = phrasesFromPaste('', wide);

        expect(rows[0].example_html).toBe('It is a long way.');
    });

    it('skips a phrase header row and says so', () => {
        const header = `
            <table>
                <tr><th>Phrase</th><th>Meaning</th><th>When to use</th><th>Example</th></tr>
                <tr><td>a long way</td><td>far away</td><td>travel</td><td>It is a long way.</td></tr>
            </table>`;

        const { rows, headerSkipped } = phrasesFromPaste('', header);

        expect(headerSkipped).toBe(true);
        expect(rows).toHaveLength(1);
        expect(rows[0].text).toBe('a long way');
        expect(rows[0].meaning).toBe('far away');
        expect(rows[0].example_html).toBe('travel It is a long way.');
    });

    it('keeps a first row that only looks like a header', () => {
        const { rows, headerSkipped } = phrasesFromPaste(
            '',
            '<table><tr><td>phrase</td><td>a long way</td></tr></table>',
        );

        expect(headerSkipped).toBe(false);
        expect(rows[0].text).toBe('phrase');
        expect(rows[0].meaning).toBe('a long way');
    });
});

describe('reading a spreadsheet paste', () => {
    it('maps tab-separated rows onto the columns', () => {
        const { rows } = phrasesFromPaste(
            'a long way\tfar away\tIt is a long way.\nCould I have the bill?\ta polite way to ask\t',
        );

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('a long way');
        expect(rows[0].meaning).toBe('far away');
        expect(rows[0].example_html).toBe('It is a long way.');
        expect(rows[1].example_html).toBe('');
    });
});

describe('duplicates', () => {
    it('matches on the phrase alone, ignoring case, spaces and formatting', () => {
        const first = { text: 'a long way', meaning: 'far away' };
        const second = { text: '  A LONG  way ', meaning: 'something else' };

        expect(phraseDuplicateKey(first)).toBe(phraseDuplicateKey(second));
    });

    it('does not treat a row with no phrase as a duplicate', () => {
        expect(phraseDuplicateKey({ text: '', meaning: 'far away' })).toBe('');
    });
});
