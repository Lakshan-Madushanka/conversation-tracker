import { describe, expect, it } from 'vite-plus/test';
import {
    archiveAvailabilityLabel,
    archiveInsteadLabel,
    bulkArchiveMessage,
    bulkDeleteTitle,
    bulkDeletionMessage,
    bulkRestoreMessage,
    rangeIds,
    restoreAvailabilityLabel,
    selectionCountLabel,
} from '@/lib/session-selection';
import type {
    BulkArchiveResult,
    BulkDeletionResult,
    BulkRestoreResult,
    SessionCardData,
} from '@/types';

function card(id: number): SessionCardData {
    return {
        id,
        topic: `Topic ${id}`,
        session_date: null,
        last_edited_at: null,
        archived_at: null,
        status: 'finished',
        counts: {
            questions: 0,
            words: 0,
            phrases: 0,
            mistakes: 0,
            grammar_notes: 0,
        },
    };
}

function archiveResult(
    overrides: Partial<BulkArchiveResult> = {},
): BulkArchiveResult {
    return {
        archived: true,
        archived_ids: [],
        archived_count: 0,
        skipped_unfinished: 0,
        skipped_archived: 0,
        skipped_missing: 0,
        ...overrides,
    };
}

function restoreResult(
    overrides: Partial<BulkRestoreResult> = {},
): BulkRestoreResult {
    return {
        restored: true,
        restored_ids: [],
        restored_count: 0,
        skipped_not_archived: 0,
        skipped_missing: 0,
        ...overrides,
    };
}

function deletionResult(
    overrides: Partial<BulkDeletionResult> = {},
): BulkDeletionResult {
    return {
        deleted: true,
        already_deleted: false,
        deleted_ids: [],
        deleted_count: 0,
        skipped_missing: 0,
        ...overrides,
    };
}

describe('the selection count and what can be archived', () => {
    it('counts the chosen sessions', () => {
        expect(selectionCountLabel(0)).toBe('0 selected');
        expect(selectionCountLabel(1)).toBe('1 selected');
        expect(selectionCountLabel(12)).toBe('12 selected');
    });

    it('says when there is nothing to act on', () => {
        expect(archiveAvailabilityLabel(0, 0)).toBe(
            'Choose sessions to act on.',
        );
    });

    it('explains why unfinished sessions cannot be archived', () => {
        expect(archiveAvailabilityLabel(2, 0)).toBe(
            'Unfinished sessions cannot be archived.',
        );
    });

    it('counts how many of the selection can be archived', () => {
        expect(archiveAvailabilityLabel(3, 3)).toBe(
            'All of these finished sessions can be archived.',
        );
        expect(archiveAvailabilityLabel(1, 1)).toBe(
            'This finished session can be archived.',
        );
        expect(archiveAvailabilityLabel(3, 1)).toBe('1 of 3 can be archived.');
    });

    it('says what can be restored in the Archived view', () => {
        expect(restoreAvailabilityLabel(0)).toBe('Choose sessions to act on.');
        expect(restoreAvailabilityLabel(1)).toBe(
            'This session can be restored.',
        );
        expect(restoreAvailabilityLabel(3)).toBe(
            'All of these sessions can be restored.',
        );
    });
});

describe('a range chosen with Shift', () => {
    it('takes every loaded card between both ends, in either direction', () => {
        const sessions = [card(1), card(2), card(3), card(4), card(5)];

        expect(rangeIds(sessions, 2, 4)).toEqual([2, 3, 4]);
        expect(rangeIds(sessions, 4, 2)).toEqual([2, 3, 4]);
        expect(rangeIds(sessions, 3, 3)).toEqual([3]);
    });

    it('falls back to the card clicked when the anchor is no longer loaded', () => {
        const sessions = [card(1), card(2)];

        expect(rangeIds(sessions, 99, 2)).toEqual([2]);
    });
});

describe('the message after a bulk archive', () => {
    it('says how many were archived and why others were skipped', () => {
        expect(
            bulkArchiveMessage(
                archiveResult({
                    archived_ids: [1, 2, 3],
                    archived_count: 3,
                    skipped_unfinished: 2,
                    skipped_archived: 1,
                    skipped_missing: 1,
                }),
            ),
        ).toBe(
            '3 sessions archived. 2 sessions were skipped because unfinished sessions cannot be archived. 1 session was already archived. 1 session was no longer here.',
        );
    });

    it('explains when nothing could be archived', () => {
        expect(
            bulkArchiveMessage(archiveResult({ skipped_unfinished: 2 })),
        ).toBe(
            'No sessions were archived. 2 sessions were skipped because unfinished sessions cannot be archived.',
        );
    });
});

describe('the message after a bulk restore', () => {
    it('says how many came back and what was already in the Library', () => {
        expect(
            bulkRestoreMessage(
                restoreResult({
                    restored_ids: [1, 2],
                    restored_count: 2,
                    skipped_not_archived: 1,
                    skipped_missing: 1,
                }),
            ),
        ).toBe(
            '2 sessions restored. 1 session was already in the Library. 1 session was no longer here.',
        );
    });

    it('explains when nothing could be restored', () => {
        expect(
            bulkRestoreMessage(restoreResult({ skipped_not_archived: 1 })),
        ).toBe(
            'No sessions were restored. 1 session was already in the Library.',
        );
    });
});

describe('the message after a bulk delete', () => {
    it('counts what was deleted and anything already gone', () => {
        expect(
            bulkDeletionMessage(
                deletionResult({
                    deleted_ids: [1, 2],
                    deleted_count: 2,
                    skipped_missing: 1,
                }),
            ),
        ).toBe('2 sessions deleted. 1 session was already deleted.');
    });

    it('does not claim a second delete when everything was already gone', () => {
        expect(
            bulkDeletionMessage(
                deletionResult({
                    already_deleted: true,
                    skipped_missing: 3,
                }),
            ),
        ).toBe('These sessions were already deleted.');
    });
});

describe('the bulk delete warning wording', () => {
    it('names one or several sessions', () => {
        expect(bulkDeleteTitle(1)).toBe('Delete this session?');
        expect(bulkDeleteTitle(4)).toBe('Delete 4 sessions?');
    });

    it('offers Archive for the finished sessions in the selection', () => {
        expect(archiveInsteadLabel(1)).toBe(
            'Archive this finished session instead',
        );
        expect(archiveInsteadLabel(3)).toBe(
            'Archive the 3 finished sessions instead',
        );
    });
});
