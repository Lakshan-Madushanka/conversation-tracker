/**
 * The mistakes box's view of the shared table rules: the columns, the
 * separator handling and the attention wording that make a mistakes table.
 *
 * The rules themselves live in `feedback-table.ts`, so the mistakes and
 * vocabulary boxes always split a paste the same way. This mirrors
 * `App\Support\MistakeEntries`.
 */

import {
    countSeparators as countSeparatorsIn,
    duplicateKey,
    hasTable as hasTableIn,
    isHeaderRow as isHeaderRowIn,
    parseRow,
    parseRows,
    rowsFromPaste,
    rowsFromTable,
    rowsFromTsv,
    rowAttention,
    type FeedbackTableConfig,
    type PastedRows,
    type TableCells,
} from '@/lib/feedback-table';
import { richTextToText } from '@/lib/rich-text';

/** The most rows one mistakes box can hold. */
export const MAX_ROWS = 200;

/** The longest single cell, in characters. */
export const MAX_CELL_LENGTH = 1000;

/** What the column says when a correction is missing. */
export const ATTENTION_ADD_CORRECTION = 'Add the correction.';

/** What the column says when the line held more than one separator. */
export const ATTENTION_MORE_THAN_ONE =
    'More than one separator found. Please split it yourself.';

/** Cells that mark the first row of a mistakes table as its header. */
const HEADER_LABELS: ReadonlySet<string> = new Set([
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

/** The three columns of the mistakes box, and how a paste maps onto them. */
export const MISTAKES_TABLE: FeedbackTableConfig = {
    columns: [
        {
            key: 'text',
            header: 'What I said',
            formatted: false,
            placeholder: 'e.g. I go',
        },
        {
            key: 'correction_html',
            header: 'Correction',
            formatted: true,
            placeholder: 'e.g. I went',
        },
        {
            key: 'reason',
            header: 'Why (optional)',
            formatted: false,
            placeholder: 'e.g. Past tense',
        },
    ],
    headerLabels: HEADER_LABELS,
    attentionMissing: ATTENTION_ADD_CORRECTION,
    attentionMoreThanOne: ATTENTION_MORE_THAN_ONE,
    duplicateNeedsSecondColumn: true,
};

/** A mistake built from a paste, before it is given an id. */
export type ParsedMistake = TableCells;

/** The result of reading a paste: the rows, and whether a header was skipped. */
export type PastedMistakes = PastedRows;

/** The words of a correction, with its formatting ignored. */
export function correctionText(entry: TableCells): string {
    return richTextToText(entry.correction_html as string ?? '');
}

/** How many separators a cleaned line holds. */
export function countSeparators(line: string): number {
    return countSeparatorsIn(line);
}

/**
 * Why a row needs the learner's attention, worked out from its content rather
 * than stored. A row with a correction is fine.
 */
export function mistakeAttention(entry: TableCells): string | null {
    return rowAttention(MISTAKES_TABLE, entry);
}

/** Split one pasted line into what I said and the correction. */
export function parseMistakeLine(line: string): ParsedMistake {
    return parseRow(MISTAKES_TABLE, line);
}

/** Read several pasted lines, one mistake row each. */
export function parseMistakeLines(text: string): ParsedMistake[] {
    return parseRows(MISTAKES_TABLE, text);
}

/**
 * Read pasted content as mistake rows: a table keeps its columns, otherwise
 * the lines are used, each split by the separator rules.
 */
export function mistakesFromPaste(text: string, html = ''): PastedMistakes {
    return rowsFromPaste(MISTAKES_TABLE, text, html);
}

/** Whether markup holds a table at all. */
export function hasTable(html: string): boolean {
    return hasTableIn(html);
}

/**
 * Map a pasted table onto the columns: the first cell is what I said, the
 * second the correction, and any further cells join into why. Returns null when
 * there is no table to read.
 */
export function mistakesFromTable(html: string): PastedMistakes | null {
    return rowsFromTable(MISTAKES_TABLE, html);
}

/** Read tab-separated rows, as a spreadsheet puts on the clipboard. */
export function mistakesFromTsv(text: string): PastedMistakes | null {
    return rowsFromTsv(MISTAKES_TABLE, text);
}

/**
 * Whether a row is a header: at least one cell, and every non-empty cell is a
 * recognised label. When unsure, the row is kept as data.
 */
export function isHeaderRow(cells: string[]): boolean {
    return isHeaderRowIn(MISTAKES_TABLE, cells);
}

/** The key two rows are compared by, ignoring case, spaces and formatting. */
export function mistakeDuplicateKey(entry: TableCells): string {
    return duplicateKey(MISTAKES_TABLE, entry);
}
