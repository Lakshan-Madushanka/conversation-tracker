import { feedbackBoxFor } from '@/lib/feedback';
import {
    rowAttention,
    type FeedbackTableConfig,
    type TableColumn,
    type TableRow,
} from '@/lib/feedback-table';
import { isRichTextBlank } from '@/lib/rich-text';
import type { PracticeQuestionForm } from '@/types';

/**
 * The boxes that can still ask for something: a mistake without its
 * correction, a word without its meaning. Phrases and rich notes never ask,
 * and neither does the server, so the two agree without either of them
 * having to know about the other's phrasing.
 */
const ATTENTION_BOXES = ['mistake', 'vocabulary'] as const;

/** One of the boxes whose rows can ask for attention. */
export type AttentionBox = (typeof ATTENTION_BOXES)[number];

/** A row that still needs work, and which question and box it sits in. */
export interface AttentionFocus {
    questionUid: string;
    boxType: AttentionBox;
    entryId: string;
}

/** How many rows still need work, and where the first of them is. */
export interface AttentionScan {
    count: number;
    first: AttentionFocus | null;
}

/** How many of one box's rows still ask for something, and the first of them. */
export interface BoxAttentionScan {
    count: number;
    firstEntryId: string | null;
    /** What the first row is waiting for, for example "Add the correction.". */
    reason: string | null;
}

/** The id the fields of a feedback box are built on, as QuestionCard builds it. */
export function attentionFieldId(
    questionUid: string,
    boxType: AttentionBox,
): string {
    return `feedback-${questionUid}-${boxType}`;
}

/** The id of the note shown beside a row that still needs its attention column. */
export function attentionNoteId(fieldId: string, entryId: string): string {
    return `${fieldId}-attention-${entryId}`;
}

/** The id of the note to move focus to after finishing a session. */
export function attentionDomId(focus: AttentionFocus): string {
    return attentionNoteId(
        attentionFieldId(focus.questionUid, focus.boxType),
        focus.entryId,
    );
}

/**
 * Whether one column of a row holds anything. The example column holds a list
 * of entries rather than a line of markup: an empty list holds nothing, a list
 * with anything in it does not, which is the same rule the server applies to
 * the value it stores.
 */
function columnHasContent(column: TableColumn, row: TableRow): boolean {
    const value = row[column.key];

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return column.formatted ? !isRichTextBlank(value) : value.trim() !== '';
}

/** Whether a row holds anything at all, mirroring the server's blank rule. */
function holdsContent(config: FeedbackTableConfig, row: TableRow): boolean {
    return config.columns.some((column) => columnHasContent(column, row));
}

/**
 * Scans one box of one question for rows still missing a correction or a
 * meaning, carrying the wording the table would show beside the first of them.
 * Blank rows are skipped, mirroring the server's rule that drops them.
 */
export function scanBoxAttention(
    question: PracticeQuestionForm,
    boxType: AttentionBox,
): BoxAttentionScan {
    const table = feedbackBoxFor(boxType).table;

    if (table === undefined) {
        return { count: 0, firstEntryId: null, reason: null };
    }

    let count = 0;
    let firstEntryId: string | null = null;
    let reason: string | null = null;

    for (const row of question.feedback[boxType]) {
        const entry = row as TableRow;
        const why = rowAttention(table, entry);

        if (why === null || !holdsContent(table, entry)) {
            continue;
        }

        count += 1;

        if (firstEntryId === null) {
            firstEntryId = entry.id;
            reason = why;
        }
    }

    return { count, firstEntryId, reason };
}

/**
 * Scans the questions as they stand on screen for rows still missing a
 * correction or a meaning, in the order the learner meets them.
 *
 * Blank rows are skipped because the server drops them on save, so this
 * counts the same rows the finished summary reports.
 */
export function scanAttention(
    questions: PracticeQuestionForm[],
): AttentionScan {
    let count = 0;
    let first: AttentionFocus | null = null;

    for (const question of questions) {
        for (const boxType of ATTENTION_BOXES) {
            const box = scanBoxAttention(question, boxType);

            count += box.count;

            if (first === null && box.firstEntryId !== null) {
                first = {
                    questionUid: question.uid,
                    boxType,
                    entryId: box.firstEntryId,
                };
            }
        }
    }

    return { count, first };
}
