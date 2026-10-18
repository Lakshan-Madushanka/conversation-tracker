import { describe, expect, it } from 'vite-plus/test';
import {
    ATTENTION_ADD_MEANING,
    ATTENTION_MORE_THAN_ONE,
    parseVocabularyLine,
    vocabularyAttention,
    vocabularyDuplicateKey,
    vocabularyFromPaste,
} from '@/lib/vocabulary';

/** The meaning of a parsed line, so tests read naturally. */
function meaningOf(line: string): string {
    return parseVocabularyLine(line).meaning;
}

describe('splitting a pasted line', () => {
    it.each(['→', '->', '=>'])('splits at an arrow (%s)', (arrow) => {
        const parsed = parseVocabularyLine(`reservation ${arrow} a booking`);

        expect(parsed.text).toBe('reservation');
        expect(parsed.meaning).toBe('a booking');
        expect(parsed.example_html).toBe('');
    });

    it.each(['-', '--', '–', '—'])('splits at a spaced dash (%s)', (dash) => {
        const line = `reluctant ${dash} unwilling`;

        expect(parseVocabularyLine(line).text).toBe('reluctant');
        expect(meaningOf(line)).toBe('unwilling');
    });

    it('leaves a hyphen inside a word alone', () => {
        const parsed = parseVocabularyLine('a well-known place');

        expect(parsed.text).toBe('a well-known place');
        expect(parsed.meaning).toBe('');
    });

    it('removes a leading bullet before it looks for a separator', () => {
        const line = '- reservation - a booking';

        expect(parseVocabularyLine(line).text).toBe('reservation');
        expect(meaningOf(line)).toBe('a booking');
    });
});

describe('needing attention', () => {
    it('asks for a meaning when there is no separator', () => {
        const parsed = parseVocabularyLine('reservation');

        expect(vocabularyAttention(parsed)).toBe(ATTENTION_ADD_MEANING);
    });

    it('asks to split a line with more than one separator', () => {
        const parsed = parseVocabularyLine('a - b - c');

        expect(parsed.text).toBe('a - b - c');
        expect(parsed.meaning).toBe('');
        expect(vocabularyAttention(parsed)).toBe(ATTENTION_MORE_THAN_ONE);
    });

    it('is cleared once a meaning is present', () => {
        const parsed = parseVocabularyLine('reservation - a booking');

        expect(vocabularyAttention(parsed)).toBeNull();
    });
});

describe('reading a pasted table', () => {
    const table = `
        <table>
            <tr><td>reservation</td><td>a booking</td><td>I made a reservation.</td></tr>
            <tr><td>reluctant</td><td>unwilling</td><td>He was reluctant to go.</td></tr>
        </table>`;

    it('maps the cells onto the three columns', () => {
        const { rows } = vocabularyFromPaste('', table);

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('reservation');
        expect(rows[0].meaning).toBe('a booking');
        expect(rows[0].example_html).toBe('I made a reservation.');
    });

    it('keeps bold and italic in an example cell only', () => {
        const rich = `
            <table>
                <tr>
                    <td><b>reservation</b></td>
                    <td>a booking</td>
                    <td>I made a <em>reservation</em>.</td>
                </tr>
            </table>`;

        const { rows } = vocabularyFromPaste('', rich);

        expect(rows[0].text).toBe('reservation');
        expect(rows[0].meaning).toBe('a booking');
        expect(rows[0].example_html).toBe('I made a <em>reservation</em>.');
    });

    it('joins extra columns into the example', () => {
        const wide = `
            <table>
                <tr>
                    <td>reservation</td><td>a booking</td><td>I made a</td><td>reservation.</td>
                </tr>
            </table>`;

        const { rows } = vocabularyFromPaste('', wide);

        expect(rows[0].example_html).toBe('I made a reservation.');
    });

    it('skips a vocabulary header row and says so', () => {
        const header = `
            <table>
                <tr><th>Word</th><th>Meaning</th><th>Example</th></tr>
                <tr><td>reservation</td><td>a booking</td><td></td></tr>
            </table>`;

        const { rows, headerSkipped } = vocabularyFromPaste('', header);

        expect(headerSkipped).toBe(true);
        expect(rows).toHaveLength(1);
        expect(rows[0].text).toBe('reservation');
    });
});

describe('reading a spreadsheet paste', () => {
    it('maps tab-separated rows onto the columns', () => {
        const { rows } = vocabularyFromPaste(
            'reservation\ta booking\tI made one.\nreluctant\tunwilling\t',
        );

        expect(rows).toHaveLength(2);
        expect(rows[0].text).toBe('reservation');
        expect(rows[0].meaning).toBe('a booking');
        expect(rows[0].example_html).toBe('I made one.');
        expect(rows[1].example_html).toBe('');
    });
});

describe('duplicates', () => {
    it('matches on the word alone, ignoring case, spaces and formatting', () => {
        const first = { text: 'reservation', meaning: 'a booking' };
        const second = { text: '  ReserVATION ', meaning: 'something else' };

        expect(vocabularyDuplicateKey(first)).toBe(
            vocabularyDuplicateKey(second),
        );
    });

    it('does not treat a row with no word as a duplicate', () => {
        expect(vocabularyDuplicateKey({ text: '', meaning: 'a booking' })).toBe(
            '',
        );
    });
});
