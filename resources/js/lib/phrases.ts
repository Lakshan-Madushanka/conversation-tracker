/**
 * The phrases box's view of the shared table rules: its three columns (the
 * phrase, its meaning or use, and an optional example), its separator handling
 * and the wording it uses when a line cannot be split automatically.
 *
 * A phrase without a meaning is normal — "What do you recommend?" is a
 * complete phrase on its own — so an empty meaning never asks for attention.
 *
 * The rules themselves live in `feedback-table.ts`, so every table box always
 * splits a paste the same way.
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
import type { PhraseEntry } from '@/types';

/** The most rows one phrases box can hold. */
export const MAX_ROWS = 200;

/** The longest single cell, in characters. */
export const MAX_CELL_LENGTH = 1000;

/** What the column says when the line held more than one separator. */
export const ATTENTION_MORE_THAN_ONE =
    'More than one separator found. Please split it yourself.';

/** Cells that mark the first row of a phrases table as its header. */
const HEADER_LABELS: ReadonlySet<string> = new Set([
    'phrase',
    'phrases',
    'expression',
    'expressions',
    'meaning',
    'meanings',
    'use',
    'uses',
    'when to use',
    'example',
    'examples',
    'sentence',
    'sentences',
]);

/** The three columns of the phrases box, and how a paste maps onto them. */
export const PHRASE_TABLE: FeedbackTableConfig = {
    columns: [
        {
            key: 'text',
            header: 'Phrase',
            formatted: false,
            placeholder: 'e.g. Could I have the menu, please?',
        },
        {
            key: 'meaning',
            header: 'Meaning or use',
            formatted: false,
            placeholder: 'e.g. a polite way to ask',
        },
        {
            key: 'example_html',
            header: 'Example (optional)',
            formatted: true,
            placeholder: 'e.g. Could I have the menu, please?',
        },
    ],
    headerLabels: HEADER_LABELS,
    attentionMoreThanOne: ATTENTION_MORE_THAN_ONE,
    duplicateNeedsSecondColumn: false,
};

/** A new phrases row, ready to edit or save. */
export function newPhraseEntry(
    cells: Partial<Omit<PhraseEntry, 'id'>> = {},
): PhraseEntry {
    return newRow(PHRASE_TABLE, cells) as PhraseEntry;
}

/** Why a row needs the learner's attention, worked out from its content. */
export function phraseAttention(entry: TableCells): string | null {
    return rowAttention(PHRASE_TABLE, entry);
}

/** Split one pasted line into the phrase and its meaning. */
export function parsePhraseLine(line: string): TableCells {
    return parseRow(PHRASE_TABLE, line);
}

/**
 * Read pasted content as phrase rows: a table keeps its columns, otherwise the
 * lines are used, each split by the separator rules.
 */
export function phrasesFromPaste(text: string, html = ''): PastedRows {
    return rowsFromPaste(PHRASE_TABLE, text, html);
}

/** The key two rows are compared by: the phrase alone, ignoring case and spaces. */
export function phraseDuplicateKey(entry: TableCells): string {
    return duplicateKey(PHRASE_TABLE, entry);
}
