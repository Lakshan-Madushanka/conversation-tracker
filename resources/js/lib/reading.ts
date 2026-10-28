/**
 * Turns a saved question into the sections a reading page shows: one per
 * feedback box that holds something, in the order the boxes appear in the
 * editor, with each entry reduced to the line it leads with and whatever
 * explains it.
 *
 * Plain text stays plain and markup stays markup, so the reader can show
 * bold and italic without ever treating the learner's own words as markup.
 */

import type { FeedbackTone } from '@/components/SectionLabel.vue';
import { exampleParts } from '@/lib/example';
import type { FeedbackBoxConfig } from '@/lib/feedback';
import { feedbackBoxes, isFeedbackValueBlank } from '@/lib/feedback';
import {
    cellValue,
    type TableColumn,
    type TableCells,
} from '@/lib/feedback-table';
import type {
    FeedbackType,
    FeedbackValue,
    GrammarNote,
    PracticeQuestionData,
} from '@/types';

/** One piece of an entry: its words, and how those words are stored. */
export interface ReadingPart {
    value: string;
    /** Whether the value is markup (bold and italic), or plain text. */
    formatted: boolean;
}

/** One entry of a feedback box, ready to be read. */
export interface ReadingEntry {
    /** What the entry is: what was said, the word, the phrase, or the note. */
    text: ReadingPart;
    /** What explains it: the correction, meaning, why or example. */
    details: ReadingPart[];
}

/** One column of a table box, ready to be a heading. */
export interface ReadingColumn {
    header: string;
}

/** One cell of a table row: the pieces it holds, in order. */
export interface ReadingCell {
    parts: ReadingPart[];
}

/** A table box, ready to be read: its columns and one entry per row. */
export interface ReadingTable {
    columns: ReadingColumn[];
    rows: ReadingCell[][];
}

/** One feedback box of a question, ready to be shown. */
export interface ReadingSection {
    /** Which box this is, used to find it from a search result. */
    type: FeedbackType;
    tone: FeedbackTone;
    label: string;
    entries: ReadingEntry[];
    /** The columns of a table box, so it can be read as the editor shows it. */
    table?: ReadingTable;
}

/** The boxes of a question that hold something, in reading order. */
export function readingSections(
    question: PracticeQuestionData,
): ReadingSection[] {
    const sections: ReadingSection[] = [];

    for (const box of feedbackBoxes) {
        const value = question.feedback[box.type];

        if (value === undefined || isFeedbackValueBlank(value)) {
            continue;
        }

        sections.push({
            type: box.type,
            tone: box.tone,
            label: box.label,
            entries: readingEntries(box, value),
            ...(box.kind === 'table'
                ? { table: readingTable(box, value) }
                : {}),
        });
    }

    return sections;
}

/** The columns and rows of a table box, each row holding its cells. */
function readingTable(
    box: FeedbackBoxConfig,
    value: FeedbackValue,
): ReadingTable {
    const columns = box.table?.columns ?? [];

    return {
        columns: columns.map((column) => ({ header: column.header })),
        rows: (value as TableCells[]).map((row) =>
            columns.map((column) => ({ parts: partsFor(column, row) })),
        ),
    };
}

/** What each box calls its entries, one and many. */
const ENTRY_NOUNS: Record<FeedbackType, readonly [string, string]> = {
    mistake: ['mistake', 'mistakes'],
    vocabulary: ['word', 'words'],
    phrase: ['phrase', 'phrases'],
    grammar_note: ['grammar note', 'grammar notes'],
    natural_version: ['natural version', 'natural versions'],
};

/**
 * How many entries a question's boxes hold, in the editor's order, as a short
 * line for a collapsed question: "2 words · 1 mistake".
 */
export function readingEntrySummary(sections: ReadingSection[]): string {
    return sections
        .map((section) => {
            const count = section.entries.length;
            const [one, many] = ENTRY_NOUNS[section.type];

            return `${count} ${count === 1 ? one : many}`;
        })
        .join(' · ');
}

function readingEntries(
    box: FeedbackBoxConfig,
    value: FeedbackValue,
): ReadingEntry[] {
    if (box.kind === 'text') {
        return [{ text: asMarkup(value as string), details: [] }];
    }

    if (box.kind === 'notes') {
        return (value as GrammarNote[]).map((note) => ({
            text: asMarkup(note.html),
            details: [],
        }));
    }

    const columns = box.table?.columns ?? [];

    return (value as TableCells[]).map((row) => {
        const [lead, ...rest] = columns;

        return {
            text: partFor(lead, row),
            details: rest
                .flatMap((column) => partsFor(column, row))
                .filter((part) => part.value.trim() !== ''),
        };
    });
}

/**
 * Every part of one column. A plain column holds one piece of text; the
 * example column may hold a list of entries, each with its own formatting.
 */
function partsFor(column: TableColumn, row: TableCells): ReadingPart[] {
    const value = cellValue(column, row);

    if (Array.isArray(value)) {
        return exampleParts(value).map((part) => ({
            value: part.html,
            formatted: true,
        }));
    }

    return [
        {
            value: typeof value === 'string' ? value : '',
            formatted: column.formatted,
        },
    ];
}

function partFor(column: TableColumn, row: TableCells): ReadingPart {
    const value = cellValue(column, row);

    return {
        value: typeof value === 'string' ? value : '',
        formatted: column.formatted,
    };
}

function asMarkup(value: string): ReadingPart {
    return { value, formatted: true };
}
