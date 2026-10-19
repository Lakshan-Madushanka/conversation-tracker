/**
 * The rules shared by the table feedback boxes (mistakes, vocabulary and
 * phrases): how a pasted line splits into its columns, how a pasted table maps
 * onto them, and which rows need the learner's attention or repeat an earlier
 * one.
 *
 * A box's own columns live in a `FeedbackTableConfig`, so the same code can
 * drive every table box.
 *
 * This mirrors `App\Support\FeedbackTable`, so the browser and the server
 * always agree on what a paste becomes. The server applies the same rules on
 * save, so what is stored always matches what the learner saw.
 */

import { newId } from '@/lib/id';
import { cleanLine, normalizeEntryText } from '@/lib/list-entries';
import {
    escapeHtml,
    normalizeInlineHtml,
    richTextToText,
} from '@/lib/rich-text';
import {FeedbackEntry} from "@/types";

/** The most rows one table box can hold. */
export const MAX_TABLE_ROWS = 200;

/** The longest single cell, in characters. */
export const MAX_TABLE_CELL = 1000;

/**
 * A separator inside a cleaned line. Arrows count wherever they appear; a
 * dash (single, double, en or em) counts only with a space on both sides, so
 * hyphenated words such as "3-day" stay whole.
 */
const SEPARATOR = /→|->|=>|(?<=\s)-{1,2}(?=\s)|(?<=\s)[–—](?=\s)/gu;

/** One column of a table box. */
export interface TableColumn {
    /** The payload key the column is sent and stored under. */
    key: string;
    /** The column heading, also used as the cell's mobile label. */
    header: string;
    /** Whether the column keeps bold and italic, or is plain text. */
    formatted: boolean;
    /** Placeholder shown while the cell is edited. */
    placeholder: string;
}

/** Everything the table code needs to know about one box. */
export interface FeedbackTableConfig {
    /** The columns, in order: subject, then the attention column, then the rest. */
    columns: readonly TableColumn[];
    /** Cells that mark the first pasted row as a header row. */
    headerLabels: ReadonlySet<string>;
    /** What the attention column says when it is empty. Omit it when an empty attention column is normal and needs no reason. */
    attentionMissing?: string;
    /** What the attention column says when the line held more than one separator. */
    attentionMoreThanOne: string;
    /** Whether a duplicate is only a duplicate when the attention column is filled too. */
    duplicateNeedsSecondColumn: boolean;
}

/** A table row holding its columns plus a client id. */
export type TableRow = {
    id: string;
    [key: string]: string | FeedbackEntry[];
};

/** A table row's columns, before it is given an id. */
export type TableCells = Record<string, string|FeedbackEntry[]>;

/** The result of reading a paste: the rows, and whether a header was skipped. */
export interface PastedRows {
    rows: TableCells[];
    headerSkipped: boolean;
}

/** One row of a pasted table, as words and as markup. */
interface ColumnRow {
    text: string[];
    html: string[];
}

/** A new row holding the given cells, ready to edit or save. */
export function newRow(
    config: FeedbackTableConfig,
    cells: TableCells = {},
): TableRow {
    const row: TableRow = { id: newId() };

    for (const column of config.columns) {
        row[column.key] = cells[column.key] ?? '';
    }

    return row;
}

/** The stored value of one column: markup for a formatted column, text otherwise. */
export function cellValue(column: TableColumn, row: TableCells): string|FeedbackEntry[] {
    return row[column.key] ?? '';
}

/** The words of one column, with its formatting ignored. */
export function cellText(column: TableColumn, row: TableCells): string {
    const value = cellValue(column, row) as string;

    return column.formatted ? richTextToText(value) : value;
}

/** How many separators a cleaned line holds. */
export function countSeparators(line: string): number {
    return [...cleanLine(line).matchAll(SEPARATOR)].length;
}

/**
 * Split one pasted line into its first two columns. Cleaning runs first, so a
 * leading bullet is a bullet, never a separator.
 *
 * - one separator: the text before it is the first column, the text after it is
 *   the second;
 * - no separator, or more than one: the whole cleaned line stays in the first
 *   column, so nothing is guessed at and nothing is lost.
 */
export function splitLine(line: string): [string, string] {
    const cleaned = cleanLine(line);
    const matches = [...cleaned.matchAll(SEPARATOR)];

    if (matches.length === 1) {
        const [match] = matches;
        const at = match.index ?? 0;

        return [
            cleaned.slice(0, at).trim(),
            cleaned.slice(at + match[0].length).trim(),
        ];
    }

    return [cleaned, ''];
}

/** Read one pasted line into the box's columns. */
export function parseRow(
    config: FeedbackTableConfig,
    line: string,
): TableCells {
    const [first, second] = splitLine(line);
    const cells: TableCells = {};

    config.columns.forEach((column, index) => {
        if (index === 0) {
            cells[column.key] = first;
        } else if (index === 1) {
            cells[column.key] =
                column.formatted && second !== '' ? escapeHtml(second) : second;
        } else {
            cells[column.key] = '';
        }
    });

    return cells;
}

/** Read several pasted lines, one row each. */
export function parseRows(
    config: FeedbackTableConfig,
    text: string,
): TableCells[] {
    return splitCleanLines(text).map((line) => parseRow(config, line));
}

/**
 * Why a row needs the learner's attention, worked out from its content rather
 * than stored. A row with its attention column filled is fine.
 */
export function rowAttention(
    config: FeedbackTableConfig,
    row: TableCells,
): string | null {
    const [, second] = config.columns;

    if (cellText(second, row).trim() !== '') {
        return null;
    }

    const first = cellValue(config.columns[0], row) as string;

    if (countSeparators(first) > 1) {
        return config.attentionMoreThanOne;
    }

    return config.attentionMissing ?? null;
}

/** The key two rows are compared by, ignoring case, spaces and formatting. */
export function duplicateKey(
    config: FeedbackTableConfig,
    row: TableCells,
): string {
    const [first, second] = config.columns;
    const firstText = normalizeEntryText(cellText(first, row));

    // A row with nothing in its subject is already flagged for attention, not a
    // duplicate of another half-finished row.
    if (firstText === '') {
        return '';
    }

    if (!config.duplicateNeedsSecondColumn) {
        return firstText;
    }

    const secondText = normalizeEntryText(cellText(second, row));

    if (secondText === '') {
        return '';
    }

    return `${firstText}\u0000${secondText}`;
}

/**
 * Read pasted content as table rows: a table keeps its columns, otherwise the
 * lines are used, each split by the separator rules.
 */
export function rowsFromPaste(
    config: FeedbackTableConfig,
    text: string,
    html = '',
): PastedRows {
    const table = rowsFromTable(config, html);

    if (table !== null) {
        return table;
    }

    const tabbed = rowsFromTsv(config, text);

    if (tabbed !== null) {
        return tabbed;
    }

    return { rows: parseRows(config, text), headerSkipped: false };
}

/** Whether markup holds a table at all. */
export function hasTable(html: string): boolean {
    return html.trim() !== '' && /<table[\s>]/iu.test(html);
}

/**
 * Map a pasted table onto the columns: the first cell is the subject, the
 * second the attention column, and further cells join onto the last column.
 * Formats inside a formatted column are reduced to bold and italic. Returns
 * null when there is no table to read.
 */
export function rowsFromTable(
    config: FeedbackTableConfig,
    html: string,
): PastedRows | null {
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
            // Keep the markup alongside the words for formatted columns.
            text: cells.map((cell) => collapseSpaces(cell.textContent ?? '')),
            html: cells.map((cell) => cell.innerHTML),
        });
    }

    return rowsFromColumns(config, columns);
}

/** Read tab-separated rows, as a spreadsheet puts on the clipboard. */
export function rowsFromTsv(
    config: FeedbackTableConfig,
    text: string,
): PastedRows | null {
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

    return rowsFromColumns(config, columns);
}

/**
 * Whether a row is a header: at least one cell, and every non-empty cell is a
 * recognised label. When unsure, the row is kept as data.
 */
export function isHeaderRow(
    config: FeedbackTableConfig,
    cells: string[],
): boolean {
    const labels = cells
        .map((cell) =>
            collapseSpaces(cell)
                .toLowerCase()
                .replace(/[:：]\s*$/u, ''),
        )
        .filter((cell) => cell !== '');

    return (
        labels.length > 0 &&
        labels.every((label) => config.headerLabels.has(label))
    );
}

/** Map the rows of a pasted table onto the box's columns. */
function rowsFromColumns(
    config: FeedbackTableConfig,
    columns: ColumnRow[],
): PastedRows {
    const rows: TableCells[] = [];
    const lastIndex = config.columns.length - 1;
    let headerSkipped = false;

    columns.forEach((column, index) => {
        if (index === 0 && isHeaderRow(config, column.text)) {
            headerSkipped = true;

            return;
        }

        const cells: TableCells = {};

        config.columns.forEach((configColumn, columnIndex) => {
            if (columnIndex === lastIndex) {
                cells[configColumn.key] = configColumn.formatted
                    ? normalizeInlineHtml(
                          column.html.slice(columnIndex).join(' '),
                      )
                    : column.text.slice(columnIndex).join(' ').trim();

                return;
            }

            if (configColumn.formatted) {
                const raw = column.html[columnIndex];

                cells[configColumn.key] =
                    raw === undefined ? '' : normalizeInlineHtml(raw);

                return;
            }

            cells[configColumn.key] = (column.text[columnIndex] ?? '').trim();
        });

        if (
            config.columns.every(
                (configColumn) => cells[configColumn.key] === '',
            )
        ) {
            return;
        }

        rows.push(cells);
    });

    return { rows, headerSkipped };
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
