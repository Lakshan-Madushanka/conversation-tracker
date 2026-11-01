/**
 * The learner's own marks on collection items: the star, the status and the
 * personal notes. One place builds the requests and reads the answers, so the
 * list, the panels and the page all agree on how a mark is named and saved.
 */

import type { AutoSaveFunction } from '@/composables/useAutoSave';
import { jsonRequest } from '@/lib/http';
import { store as marksStore } from '@/routes/collections/marks';
import { store as notesStore } from '@/routes/collections/notes';
import type {
    CollectionItem,
    CollectionItemStatusValue,
    CollectionKindValue,
    CollectionMistakeItem,
    CollectionNoteItem,
    CollectionTabKey,
    ItemMarkData,
} from '@/types';

/** The request value that means "no status anymore". */
const NO_STATUS = 'none';

/** The mark kinds each tab shows, by the backend's names. */
const TAB_KINDS: Record<CollectionTabKey, CollectionKindValue> = {
    vocabulary: 'vocabulary',
    phrases: 'phrase',
    grammar: 'grammar_note',
    mistakes: 'mistake',
};

/** The kind of item a tab holds, as the marks endpoints name it. */
export function markKindForTab(tab: CollectionTabKey): CollectionKindValue {
    return TAB_KINDS[tab];
}

/** Anything the collections list can show a mark for. */
export type MarkableItem =
    | CollectionItem
    | CollectionNoteItem
    | CollectionMistakeItem;

/**
 * What the item is called in words, for accessible names: the word or phrase
 * itself, the mistake as it was said, or a grammar note's first line.
 */
export function markItemName(item: MarkableItem): string {
    return 'title' in item && item.title !== ''
        ? item.title
        : 'text' in item && item.text !== ''
          ? item.text
          : 'correction' in item && item.correction !== ''
            ? item.correction
            : item.key;
}

/** The star button's accessible name, with the item in it. */
export function starAriaLabel(
    unit: string,
    name: string,
    starred: boolean,
): string {
    return starred ? `Unstar the ${unit} ${name}` : `Star the ${unit} ${name}`;
}

/** The status switcher's accessible name, with the item in it. */
export function statusAriaLabel(unit: string, name: string): string {
    return `Change the status of the ${unit} ${name}`;
}

/**
 * Saves the desired star and status of one item. The request carries the full
 * state rather than a toggle, so a retry or a second tab can never flip it.
 */
export async function saveItemMark(
    kind: CollectionKindValue,
    itemKey: string,
    starred: boolean,
    status: CollectionItemStatusValue | null,
): Promise<ItemMarkData> {
    const response = await jsonRequest(marksStore.url(), {
        method: 'PUT',
        body: JSON.stringify({
            kind,
            item: itemKey,
            starred,
            status: status ?? NO_STATUS,
        }),
    });

    if (!response.ok) {
        throw new Error(`Saving the mark failed (${response.status}).`);
    }

    const body = (await response.json()) as { mark: ItemMarkData };

    return body.mark;
}

/** The note on screen, as it travels to the server. */
export interface NotesSnapshot {
    html: string;
}

/**
 * What identifies a saved note: the version the server holds now, plus its
 * cleaned text, so a conflict can show the other version next to the learner's.
 *
 * A note has no row of its own — the mark row is the storage — so `id` stays 0
 * and saves to the same item only differ by their `version`.
 */
export interface NotesMarker {
    id: 0;
    version: number;
    html: string;
}

/** Laravel lists validation mistakes per field as one or more messages. */
function firstError(values: Record<string, unknown>): Record<string, string> {
    const errors: Record<string, string> = {};

    for (const [field, value] of Object.entries(values)) {
        if (typeof value === 'string' && value !== '') {
            errors[field] = value;
        } else if (
            Array.isArray(value) &&
            typeof value[0] === 'string' &&
            value[0] !== ''
        ) {
            errors[field] = value[0];
        }
    }

    return errors;
}

/**
 * The note save the editor's autosave rules run: it waits for the pause,
 * sends one at a time, retries and raises the conflict for the learner to
 * choose on. The server answers with the note as it is saved now.
 */
export function createNotesSaver(
    kind: CollectionKindValue,
    itemKey: string,
): AutoSaveFunction<NotesSnapshot, NotesMarker> {
    return async (request, options) => {
        const response = await jsonRequest(notesStore.url(), {
            method: 'PUT',
            body: JSON.stringify({
                kind,
                item: itemKey,
                html: request.html,
                version: request.version ?? 0,
            }),
            keepalive: options?.keepalive ?? false,
        });

        if (response.status === 200) {
            const body = (await response.json()) as { mark: ItemMarkData };

            return {
                outcome: 'saved',
                saved: {
                    id: 0,
                    version: body.mark.notes_version,
                    html: body.mark.notes_html,
                },
                savedAt: new Date().toISOString(),
            };
        }

        if (response.status === 409) {
            const body = (await response.json()) as { mark: ItemMarkData };

            return {
                outcome: 'conflict',
                saved: {
                    id: 0,
                    version: body.mark.notes_version,
                    html: body.mark.notes_html,
                },
            };
        }

        if (response.status === 422) {
            const body = (await response.json()) as {
                errors?: Record<string, unknown>;
            };

            return {
                outcome: 'invalid',
                errors: firstError(body.errors ?? {}),
            };
        }

        return { outcome: 'failed' };
    };
}
