/**
 * The examples a vocabulary or phrase row holds, whatever shape they were
 * saved in: one piece of markup from a paste or an older save, or a list of
 * entries the editor added one by one.
 *
 * Every part carries both its safe markup and its words, so a reading view can
 * show the formatting while a plain editor or an accessible name still gets
 * the words alone.
 */

import { escapeHtml, isRichTextBlank, richTextToText } from '@/lib/rich-text';
import type { FeedbackEntry } from '@/types';

/** What an example column can hold, as the backend or the editor sends it. */
export type ExampleValue = string | FeedbackEntry[] | null | undefined;

/** One piece of an example: safe markup, and the same words as plain text. */
export interface ExamplePart {
    html: string;
    text: string;
}

/**
 * The examples in order. An example saved as one piece of markup is one part;
 * one saved by the editor is a list of entries, each keeping its own bold and
 * italic.
 */
export function exampleParts(value: ExampleValue): ExamplePart[] {
    if (Array.isArray(value)) {
        return value.flatMap((entry) => {
            const html =
                typeof entry.html === 'string' && entry.html.trim() !== ''
                    ? entry.html
                    : escapeHtml(entry.text);

            const text = richTextToText(html);

            return text === '' ? [] : [{ html, text }];
        });
    }

    if (typeof value !== 'string' || isRichTextBlank(value)) {
        return [];
    }

    return [{ html: value, text: richTextToText(value) }];
}

/**
 * The examples as entries the list editor can work with. A list keeps the ids
 * the server saved, so editing one still updates the same entry; a single
 * piece of markup becomes one entry with a stable id for that row.
 */
export function exampleEntries(value: ExampleValue): FeedbackEntry[] {
    if (Array.isArray(value)) {
        return value.map((entry) => ({
            id: entry.id,
            text:
                entry.text === ''
                    ? richTextToText(entry.html ?? '')
                    : entry.text,
        }));
    }

    return exampleParts(value).map((part, index) => ({
        id: `example-${index}`,
        text: part.text,
    }));
}
