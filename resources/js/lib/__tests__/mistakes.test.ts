import { describe, expect, it } from 'vite-plus/test';
import {
    ATTENTION_ADD_CORRECTION,
    ATTENTION_MORE_THAN_ONE,
    correctionText,
    countSeparators,
    mistakesFromPaste,
    mistakeAttention,
    mistakeDuplicateKey,
    isHeaderRow,
    parseMistakeLine,
} from '@/lib/mistakes';

/** The words of a parsed correction, so tests read naturally. */
function correctionOf(line: string): string {
    return correctionText(parseMistakeLine(line));
}

describe('splitting a pasted line', () => {
    it.each(['→', '->', '=>'])('splits at an arrow (%s)', (arrow) => {
        const parsed = parseMistakeLine(
            `I want a pasta ${arrow} I'd like some pasta`,
        );

        expect(parsed.text).toBe('I want a pasta');
        expect(
            correctionOf(`I want a pasta ${arrow} I'd like some pasta`),
        ).toBe("I'd like some pasta");
        expect(parsed.reason).toBe('');
    });

    it.each(['-', '--', '–', '—'])('splits at a spaced dash (%s)', (dash) => {
        const line = `She don't like ${dash} She doesn't like`;

        expect(parseMistakeLine(line).text).toBe("She don't like");
        expect(correctionOf(line)).toBe("She doesn't like");
    });

    it('leaves a hyphen inside a word alone', () => {
        const parsed = parseMistakeLine('I have a 3-day holiday');

        expect(parsed.text).toBe('I have a 3-day holiday');
        expect(parsed.correction_html).toBe('');
    });

    it('splits at the arrow even when a correction holds a hyphenated word', () => {
        const line = 'I have 3 days holiday → I have a 3-day holiday';

        expect(parseMistakeLine(line).text).toBe('I have 3 days holiday');
        expect(correctionOf(line)).toBe('I have a 3-day holiday');
    });

    it('removes a leading bullet before it looks for a separator', () => {
        const line = "- I want a pasta - I'd like some pasta";

        expect(parseMistakeLine(line).text).toBe('I want a pasta');
        expect(correctionOf(line)).toBe("I'd like some pasta");
    });
});

describe('lines with more than one separator', () => {
    const cases = [
        "She don't like - She doesn't like - third person needs doesn't",
        "I want a pasta → I'd like some pasta - polite request",
        "I want pasta → I'd like pasta → please",
    ];

    it.each(cases)('keeps the whole line and asks to split it: %s', (line) => {
        const parsed = parseMistakeLine(line);

        expect(parsed.text).toBe(line);
        expect(parsed.correction_html).toBe('');
        expect(mistakeAttention(parsed)).toBe(ATTENTION_MORE_THAN_ONE);
    });
});

describe('needing attention', () => {
    it('asks for a correction when there is no separator', () => {
        const parsed = parseMistakeLine('I have a 3-day holiday');

        expect(mistakeAttention(parsed)).toBe(ATTENTION_ADD_CORRECTION);
    });

    it('is cleared once a correction is present', () => {
        const parsed = parseMistakeLine("I want a pasta → I'd like some pasta");

        expect(mistakeAttention(parsed)).toBeNull();
    });
});

describe('separators that do not count', () => {
    it.each([
        'I like: pasta / rice, bread | butter',
        'well-known',
        'twenty-one',
        '3-day',
    ])('does not split %s', (line) => {
        expect(countSeparators(line)).toBe(0);
        expect(parseMistakeLine(line).correction_html).toBe('');
    });
});

describe('reading a pasted table', () => {
    const table = `
        <table>
            <tr><td>I want a pasta</td><td>I'd like some pasta</td><td>Polite request</td></tr>
            <tr><td>She don't like</td><td>She doesn't like</td><td>Third person</td></tr>
        </table>`;

    it('maps the cells onto the three columns', () => {
        const { rows } = mistakesFromPaste('', table);

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('I want a pasta');
        expect(correctionText(rows[0])).toBe("I'd like some pasta");
        expect(rows[0].reason).toBe('Polite request');
    });

    it('keeps bold and italic in a correction cell only', () => {
        const rich = `
            <table>
                <tr>
                    <td><b>I</b> want a pasta</td>
                    <td><strong>I'd</strong> like <em>some</em> pasta</td>
                    <td>Reason</td>
                </tr>
            </table>`;

        const { rows } = mistakesFromPaste('', rich);

        expect(rows[0].text).toBe('I want a pasta');
        expect(rows[0].correction_html).toBe(
            '<strong>I&#39;d</strong> like <em>some</em> pasta',
        );
    });

    it('drops formatting other than bold and italic', () => {
        const rich = `
            <table>
                <tr>
                    <td>x</td>
                    <td><span style="color:red">wrong</span></td>
                </tr>
            </table>`;

        const { rows } = mistakesFromPaste('', rich);

        expect(rows[0].correction_html).toBe('wrong');
    });

    it('joins extra columns into why', () => {
        const wide = `
            <table>
                <tr>
                    <td>I go</td><td>I went</td><td>Past</td><td>tense</td>
                </tr>
            </table>`;

        const { rows } = mistakesFromPaste('', wide);

        expect(rows[0].reason).toBe('Past tense');
    });

    it('skips a header row and says so', () => {
        const header = `
            <table>
                <tr><th>Mistake</th><th>Correction</th><th>Explanation</th></tr>
                <tr><td>I go</td><td>I went</td><td>Past</td></tr>
            </table>`;

        const { rows, headerSkipped } = mistakesFromPaste('', header);

        expect(headerSkipped).toBe(true);
        expect(rows).toHaveLength(1);
        expect(rows[0].text).toBe('I go');
    });

    it('keeps a first row that is not a header', () => {
        const { rows, headerSkipped } = mistakesFromPaste('', table);

        expect(headerSkipped).toBe(false);
        expect(rows).toHaveLength(2);
    });

    it('marks a table row with no correction as needing attention', () => {
        const missing = `
            <table>
                <tr><td>I go</td><td></td><td></td></tr>
            </table>`;

        const { rows } = mistakesFromPaste('', missing);

        expect(rows[0].correction_html).toBe('');
        expect(mistakeAttention(rows[0])).toBe(ATTENTION_ADD_CORRECTION);
    });
});

describe('reading a spreadsheet paste', () => {
    it('maps tab-separated rows onto the columns', () => {
        const { rows } = mistakesFromPaste(
            "I go\tI went\tPast\nShe don't\tShe doesn't\t",
        );

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('I go');
        expect(correctionText(rows[0])).toBe('I went');
        expect(rows[0].reason).toBe('Past');
        expect(rows[1].reason).toBe('');
    });
});

describe('plain text pastes', () => {
    it('reads one row per line', () => {
        const { rows } = mistakesFromPaste(
            "I go → I went\nShe don't like - She doesn't like",
        );

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('I go');
        expect(rows[1].text).toBe("She don't like");
    });
});

describe('header labels', () => {
    it.each([
        [['Mistake', 'Correction', 'Explanation']],
        [['Wrong', 'Corrected']],
        [['What I said', 'Better', 'Why']],
        [['Correction:']],
    ])('recognises %j as a header', (cells) => {
        expect(isHeaderRow(cells)).toBe(true);
    });

    it('keeps a row that is not a header', () => {
        expect(isHeaderRow(['I go', 'I went', 'Past'])).toBe(false);
        expect(isHeaderRow(['I go', 'Correction', ''])).toBe(false);
    });
});

describe('duplicates', () => {
    it('matches on both columns, ignoring case, spaces and formatting', () => {
        const first = { text: 'I go', correction_html: 'I went' };
        const second = {
            text: '  i  GO ',
            correction_html: '<strong>I  went</strong>',
        };

        expect(mistakeDuplicateKey(first)).toBe(mistakeDuplicateKey(second));
    });

    it('does not match the same words with a different correction', () => {
        expect(
            mistakeDuplicateKey({ text: 'I go', correction_html: 'I went' }),
        ).not.toBe(
            mistakeDuplicateKey({
                text: 'I go',
                correction_html: 'I am going',
            }),
        );
    });

    it('does not treat a missing correction as a duplicate', () => {
        expect(mistakeDuplicateKey({ text: 'I go', correction_html: '' })).toBe(
            '',
        );
    });
});
