import { describe, expect, it } from 'vite-plus/test';
import {
    cleanLine,
    normalizeEntryText,
    splitIntoEntries,
} from '@/lib/list-entries';

describe('splitIntoEntries', () => {
    it('turns pasted text into one entry per line', () => {
        const lines = [
            'reluctant — unwilling, not eager',
            'affordable — not too expensive',
            'catch up — talk after a long time',
            'reservation — a booking',
            'relish — to enjoy very much',
            'ahead of time — early',
            'on second thought — after reconsidering',
            'to be into something — to like something',
            'a waste of time — something useless',
            'get the hang of it — learn how to do it',
        ].join('\n');

        const entries = splitIntoEntries(lines);

        expect(entries).toHaveLength(10);
        expect(entries[0]).toBe('reluctant — unwilling, not eager');
    });

    it('ignores blank lines', () => {
        expect(splitIntoEntries('one\n\n\n   \ntwo\n')).toEqual(['one', 'two']);
    });

    it('tidies up spacing and line endings', () => {
        expect(
            splitIntoEntries(
                '  extra   spaces \t here  \r\nsecond\tline\rthird\r\n',
            ),
        ).toEqual(['extra spaces here', 'second line', 'third']);
    });

    it('keeps a very long line as one whole entry', () => {
        const line = 'a long entry that the learner pasted by mistake '.repeat(
            30,
        );

        const entries = splitIntoEntries(line);

        expect(entries).toHaveLength(1);
        expect(entries[0]).toHaveLength(line.trim().length);
    });
});

describe('cleanLine', () => {
    it.each([
        ['• reservation', 'reservation'],
        ['•reservation', 'reservation'],
        ['- reservation', 'reservation'],
        ['* reservation', 'reservation'],
        ['1. reservation', 'reservation'],
        ['2) reservation', 'reservation'],
        ['(3) reservation', 'reservation'],
        ['(a) reservation', 'reservation'],
        ['A. reservation', 'reservation'],
        ['- - nested reservation', 'nested reservation'],
        ['  12)   reservation  ', 'reservation'],
    ])('removes the marker from %s', (line, expected) => {
        expect(cleanLine(line)).toBe(expected);
    });

    it.each([
        '3 days',
        'reluctant — unwilling',
        'e.g. reservation',
        '2.5 grams',
        '-5 degrees',
        'I go → I went',
        '**bold** stays',
        '*italic* stays',
    ])('leaves %s alone', (line) => {
        expect(cleanLine(line)).toBe(line);
    });
});

describe('normalizeEntryText', () => {
    it('ignores case and extra spaces when entries are compared', () => {
        expect(normalizeEntryText('  Reservation  ')).toBe('reservation');
        expect(normalizeEntryText('a  long\tway')).toBe('a long way');
    });
});
