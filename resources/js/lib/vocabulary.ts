/**
 * The vocabulary box's view of the shared table rules: its three columns (the
 * word, its meaning and an optional example), its separator handling and the
 * wording it uses when the meaning is missing.
 *
 * The rules themselves live in `feedback-table.ts`, so the mistakes and
 * vocabulary boxes always split a paste the same way.
 */

import {
    duplicateKey,
    newRow,
    parseRow,
    rowsFromPaste,
    rowAttention,
    type FeedbackTableConfig,
    type PastedRows,
    type TableCells,
} from '@/lib/feedback-table';
import type { VocabularyEntry } from '@/types';

/** The most rows one vocabulary box can hold. */
export const MAX_ROWS = 200;

/** The longest single cell, in characters. */
export const MAX_CELL_LENGTH = 1000;

/** What the column says when a meaning is missing. */
export const ATTENTION_ADD_MEANING = 'Add the meaning.';

/** What the column says when the line held more than one separator. */
export const ATTENTION_MORE_THAN_ONE =
    'More than one separator found. Please split it yourself.';

/** Cells that mark the first row of a vocabulary table as its header. */
const HEADER_LABELS: ReadonlySet<string> = new Set([
    'word',
    'words',
    'vocabulary',
    'term',
    'terms',
    'meaning',
    'meanings',
    'definition',
    'definitions',
    'example',
    'examples',
    'sentence',
    'sentences',
]);

/** The three columns of the vocabulary box, and how a paste maps onto them. */
export const VOCABULARY_TABLE: FeedbackTableConfig = {
    columns: [
        {
            key: 'text',
            header: 'Word',
            formatted: false,
            placeholder: 'e.g. reservation',
        },
        {
            key: 'meaning',
            header: 'Meaning',
            formatted: false,
            placeholder: 'e.g. a booking',
        },
        {
            key: 'example_html',
            header: 'Example (optional)',
            formatted: true,
            placeholder: 'e.g. I made a reservation.',
        },
    ],
    headerLabels: HEADER_LABELS,
    attentionMissing: ATTENTION_ADD_MEANING,
    attentionMoreThanOne: ATTENTION_MORE_THAN_ONE,
    duplicateNeedsSecondColumn: false,
};

/** A new vocabulary row, ready to edit or save. */
export function newVocabularyEntry(
    cells: Partial<Omit<VocabularyEntry, 'id'>> = {},
): VocabularyEntry {
    return newRow(VOCABULARY_TABLE, cells) as VocabularyEntry;
}

/** Why a row needs the learner's attention, worked out from its content. */
export function vocabularyAttention(entry: TableCells): string | null {
    return rowAttention(VOCABULARY_TABLE, entry);
}

/** Split one pasted line into the word and its meaning. */
export function parseVocabularyLine(line: string): TableCells {
    return parseRow(VOCABULARY_TABLE, line);
}

/**
 * Read pasted content as vocabulary rows: a table keeps its columns, otherwise
 * the lines are used, each split by the separator rules.
 */
export function vocabularyFromPaste(text: string, html = ''): PastedRows {
    return rowsFromPaste(VOCABULARY_TABLE, text, html);
}

/** The key two rows are compared by: the word alone, ignoring case and spaces. */
export function vocabularyDuplicateKey(entry: TableCells): string {
    return duplicateKey(VOCABULARY_TABLE, entry);
}
