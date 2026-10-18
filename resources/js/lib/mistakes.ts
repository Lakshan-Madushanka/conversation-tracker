/**
 * The rules for the mistakes box: how a pasted line splits into the three
 * columns (what I said, the correction, and an optional why), and how a pasted
 * table maps onto them.
 *
 * This mirrors `App\Support\MistakeEntries`, so the browser and the server
 * always agree on what a paste becomes. The server applies the same rules on
 * save, so what is stored always matches what the learner saw.
 */

import { cleanLine, normalizeEntryText } from '@/lib/list-entries';
import {
    escapeHtml,
    normalizeInlineHtml,
    richTextToText,
} from '@/lib/rich-text';
import type { MistakeEntry } from '@/types';

/** The most rows one mistakes box can hold. */
export const MAX_ROWS = 200;

/** The longest single cell, in characters. */
export const MAX_CELL_LENGTH = 1000;

/** What the column says when a correction is missing. */
export const ATTENTION_ADD_CORRECTION = 'Add the correction.';

/** What the column says when the line held more than one separator. */
export const ATTENTION_MORE_THAN_ONE =
    'More than one separator found. Please split it yourself.';

/**
 * A separator inside a cleaned line. Arrows count wherever they appear; a
 * dash (single, double, en or em) counts only with a space on both sides, so
 * hyphenated words such as "3-day" stay whole.
 */
const SEPARATOR = /→|->|=>|(?<=\s)-{1,2}(?=\s)|(?<=\s)[–—](?=\s)/gu;

/** A mistake built from a paste, before it is given an id. */
export interface ParsedMistake {
    text: string;
    correction_html: string;
    reason: string;
}

/** The result of reading a paste: the rows, and whether a header was skipped. */
export interface PastedMistakes {
    rows: ParsedMistake[];
    headerSkipped: boolean;
}

/** The words of a correction, with its formatting ignored. */
export function correctionText(
    entry: Pick<MistakeEntry, 'correction_html'>,
): string {
    return richTextToText(entry.correction_html);
}

/** How many separators a cleaned line holds. */
export function countSeparators(line: string): number {
    return [...line.matchAll(SEPARATOR)].length;
}

/**
 * Why a row needs the learner's attention, worked out from its content rather
 * than stored. A row with a correction is fine.
 */
export function mistakeAttention(
    entry: Pick<MistakeEntry, 'text' | 'correction_html'>,
): string | null {
    if (correctionText(entry).trim() !== '') {
        return null;
    }

    if (countSeparators(cleanLine(entry.text)) > 1) {
        return ATTENTION_MORE_THAN_ONE;
    }

    return ATTENTION_ADD_CORRECTION;
}

/**
 * Split one pasted line into its parts. Cleaning runs first, so a leading
 * bullet is a bullet, never a separator.
 *
 * - one separator: the text before it is what I said, the text after it is the
 *   correction;
 * - no separator, or more than one: the whole cleaned line stays as what I
 *   said, so nothing is guessed at and nothing is lost.
 */
export function parseMistakeLine(line: string): ParsedMistake {
    const cleaned = cleanLine(line);
    const matches = [...cleaned.matchAll(SEPARATOR)];

    if (matches.length === 1) {
        const [match] = matches;
        const at = match.index ?? 0;
        const text = cleaned.slice(0, at).trim();
        const correction = cleaned.slice(at + match[0].length).trim();

        return {
            text,
            correction_html: correction === '' ? '' : escapeHtml(correction),
            reason: '',
        };
    }

    return { text: cleaned, correction_html: '', reason: '' };
}

/** Read several pasted lines, one mistake row each. */
export function parseMistakeLines(text: string): ParsedMistake[] {
    return splitCleanLines(text).map((line) => parseMistakeLine(line));
}

/**
 * Read pasted content as mistake rows: a table keeps its columns, otherwise
 * the lines are used, each split by the separator rules.
 */
export function mistakesFromPaste(text: string, html = ''): PastedMistakes {
    const table = mistakesFromTable(html);

    if (table !== null) {
        return table;
    }

    const tabbed = mistakesFromTsv(text);

    if (tabbed !== null) {
        return tabbed;
    }

    return { rows: parseMistakeLines(text), headerSkipped: false };
}

/** Whether markup holds a table at all. */
export function hasTable(html: string): boolean {
    return html.trim() !== '' && /<table[\s>]/iu.test(html);
}

/**
 * Map a pasted table onto the columns: the first cell is what I said, the
 * second the correction, and any further cells join into why. Formats inside a
 * correction cell are reduced to bold and italic. Returns null when there is
 * no table to read.
 */
export function mistakesFromTable(html: string): PastedMistakes | null {
    if (typeof DOMParser === 'undefined' || !hasTable(html)) {
        return null;
    }

    const document = new DOMParser().parseFromString(html, 'text/html');
    const table = document.querySelector('table');

    if (!table) {
        return null;
    }

    const columns: ColumnRow[] = [];

    for (const row of table.querySelectorAll('tr')) {
        const cells = Array.from(row.children).filter((cell) =>
            ['TD', 'TH'].includes(cell.tagName),
        );

        if (cells.length === 0) {
            continue;
        }

        columns.push({
            // Keep the markup alongside the words for the correction column.
            text: cells.map((cell) => collapseSpaces(cell.textContent ?? '')),
            html: cells.map((cell) => cell.innerHTML),
        });
    }

    return rowsFromColumns(columns);
}

/** One row of a pasted table, as words and as markup. */
interface ColumnRow {
    text: string[];
    html: string[];
}

function rowsFromColumns(columns: ColumnRow[]): PastedMistakes {
    const rows: ParsedMistake[] = [];
    let headerSkipped = false;

    columns.forEach((column, index) => {
        if (index === 0 && isHeaderRow(column.text)) {
            headerSkipped = true;

            return;
        }

        const text = (column.text[0] ?? '').trim();
        const correction_html =
            column.html[1] === undefined
                ? ''
                : normalizeInlineHtml(column.html[1]);
        const reason = column.text.slice(2).join(' ').trim();

        if (text === '' && correction_html === '' && reason === '') {
            return;
        }

        rows.push({ text, correction_html, reason });
    });

    return { rows, headerSkipped };
}

/** Read tab-separated rows, as a spreadsheet puts on the clipboard. */
export function mistakesFromTsv(text: string): PastedMistakes | null {
    const lines = text
        .replaceAll(/\r\n|[\r\u2028\u2029]/gu, '\n')
        .split('\n')
        .filter((line) => line.trim() !== '');

    if (lines.length === 0 || lines.some((line) => !line.includes('\t'))) {
        return null;
    }

    const columns: ColumnRow[] = lines.map((line) => {
        const cells = line.split('\t').map((cell) => collapseSpaces(cell));

        return { text: cells, html: cells.map((cell) => escapeHtml(cell)) };
    });

    return rowsFromColumns(columns);
}

/** Cells that mark the first row of a table as its header. */
const HEADER_LABELS = new Set([
    'mistake',
    'mistakes',
    'wrong',
    'what i said',
    'what i say',
    'i said',
    'correction',
    'corrections',
    'correct',
    'corrected',
    'better',
    'why',
    'explanation',
    'reason',
]);

/**
 * Whether a row is a header: at least one cell, and every non-empty cell is a
 * recognised label. When unsure, the row is kept as data.
 */
export function isHeaderRow(cells: string[]): boolean {
    const labels = cells
        .map((cell) =>
            collapseSpaces(cell)
                .toLowerCase()
                .replace(/[:：]\s*$/u, ''),
        )
        .filter((cell) => cell !== '');

    return (
        labels.length > 0 && labels.every((label) => HEADER_LABELS.has(label))
    );
}

/** The key two rows are compared by, ignoring case, spaces and formatting. */
export function mistakeDuplicateKey(
    entry: Pick<MistakeEntry, 'text' | 'correction_html'>,
): string {
    const text = normalizeEntryText(entry.text);
    const correction = normalizeEntryText(correctionText(entry));

    // A row with no correction is already flagged for attention, not a
    // duplicate of another half-finished row.
    if (text === '' || correction === '') {
        return '';
    }

    return `${text}\u0000${correction}`;
}

/** Clean, non-empty lines, in order, as a paste would produce them. */
function splitCleanLines(text: string): string[] {
    return text
        .replaceAll(/\r\n|[\r\u2028\u2029]/gu, '\n')
        .split('\n')
        .map((line) => cleanLine(line))
        .filter((line) => line !== '');
}

/** Turn runs of spaces and tabs into a single space, then trim. */
function collapseSpaces(text: string): string {
    return text.replaceAll(/\s+/gu, ' ').trim();
}
