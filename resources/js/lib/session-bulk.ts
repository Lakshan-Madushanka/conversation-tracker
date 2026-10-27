import { jsonRequest } from '@/lib/http';
import {
    bulkArchive as sessionsBulkArchive,
    bulkDelete as sessionsBulkDelete,
    bulkDeletePreview as sessionsBulkDeletePreview,
    bulkRestore as sessionsBulkRestore,
    selection as sessionsSelection,
} from '@/routes/sessions';
import type {
    BulkArchiveResult,
    BulkDeletionResult,
    BulkRestoreResult,
    LibrarySelection,
    SessionDeletionBatchPreview,
} from '@/types';

/** How asking for every matching session ended. */
export type SelectionOutcome =
    | { outcome: 'selection'; selection: LibrarySelection }
    | { outcome: 'failed' };

/** How a permanent bulk delete ended. A stale answer carries fresh numbers. */
export type BulkDeleteOutcome =
    | { outcome: 'deleted'; result: BulkDeletionResult }
    | {
          outcome: 'stale';
          preview: SessionDeletionBatchPreview;
          message: string;
      }
    | { outcome: 'failed' };

/** Every session matching the filters on screen, not only the loaded cards. */
export async function fetchSelection(
    query: Record<string, string>,
): Promise<SelectionOutcome> {
    try {
        const response = await jsonRequest(sessionsSelection.url({ query }));

        if (!response.ok) {
            return { outcome: 'failed' };
        }

        const body: unknown = await response.json();

        return isSelection(body)
            ? { outcome: 'selection', selection: body }
            : { outcome: 'failed' };
    } catch {
        return { outcome: 'failed' };
    }
}

/** Puts every finished session in the selection away. Safe to repeat. */
export async function archiveSessions(
    ids: number[],
): Promise<BulkArchiveResult | null> {
    try {
        const response = await jsonRequest(sessionsBulkArchive.url(), {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });

        if (!response.ok) {
            return null;
        }

        const body: unknown = await response.json();

        return isArchiveResult(body) ? body : null;
    } catch {
        return null;
    }
}

/** Brings every archived session in the selection back. Safe to repeat. */
export async function restoreSessions(
    ids: number[],
): Promise<BulkRestoreResult | null> {
    try {
        const response = await jsonRequest(sessionsBulkRestore.url(), {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });

        if (!response.ok) {
            return null;
        }

        const body: unknown = await response.json();

        return isRestoreResult(body) ? body : null;
    } catch {
        return null;
    }
}

/** Reads what deleting the selection would remove and change. */
export async function fetchBulkDeletePreview(
    ids: number[],
    timezone: string,
): Promise<SessionDeletionBatchPreview | null> {
    try {
        const response = await jsonRequest(sessionsBulkDeletePreview.url(), {
            method: 'POST',
            body: JSON.stringify({ ids, tz: timezone }),
        });

        if (!response.ok) {
            return null;
        }

        const body: unknown = await response.json();

        return isBatchPreview(body) ? body : null;
    } catch {
        return null;
    }
}

/**
 * Deletes the selection permanently. The token is the state the learner saw;
 * a changed selection is refused with the fresh warning instead of being
 * removed under stale numbers.
 */
export async function deleteSessionsPermanently(
    ids: number[],
    token: string,
    timezone: string,
): Promise<BulkDeleteOutcome> {
    try {
        const response = await jsonRequest(sessionsBulkDelete.url(), {
            method: 'DELETE',
            body: JSON.stringify({ ids, token, tz: timezone }),
        });

        if (response.status === 409) {
            const body: unknown = await response.json();

            if (
                isRecord(body) &&
                isBatchPreview(body.preview) &&
                typeof body.message === 'string'
            ) {
                return {
                    outcome: 'stale',
                    preview: body.preview,
                    message: body.message,
                };
            }

            return { outcome: 'failed' };
        }

        if (!response.ok) {
            return { outcome: 'failed' };
        }

        const body: unknown = await response.json();

        return isDeletionResult(body)
            ? { outcome: 'deleted', result: body }
            : { outcome: 'failed' };
    } catch {
        return { outcome: 'failed' };
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isSelection(value: unknown): value is LibrarySelection {
    if (!isRecord(value)) {
        return false;
    }

    return (
        Array.isArray(value.ids) &&
        Array.isArray(value.finished_ids) &&
        typeof value.total === 'number' &&
        typeof value.limit === 'number' &&
        typeof value.truncated === 'boolean'
    );
}

function isArchiveResult(value: unknown): value is BulkArchiveResult {
    if (!isRecord(value)) {
        return false;
    }

    return (
        value.archived === true &&
        Array.isArray(value.archived_ids) &&
        typeof value.archived_count === 'number' &&
        typeof value.skipped_unfinished === 'number' &&
        typeof value.skipped_archived === 'number' &&
        typeof value.skipped_missing === 'number'
    );
}

function isRestoreResult(value: unknown): value is BulkRestoreResult {
    if (!isRecord(value)) {
        return false;
    }

    return (
        value.restored === true &&
        Array.isArray(value.restored_ids) &&
        typeof value.restored_count === 'number' &&
        typeof value.skipped_not_archived === 'number' &&
        typeof value.skipped_missing === 'number'
    );
}

function isBatchPreview(value: unknown): value is SessionDeletionBatchPreview {
    if (!isRecord(value)) {
        return false;
    }

    return (
        typeof value.token === 'string' &&
        typeof value.requested_count === 'number' &&
        typeof value.session_count === 'number' &&
        typeof value.missing_count === 'number' &&
        typeof value.finished_count === 'number' &&
        typeof value.unfinished_count === 'number' &&
        typeof value.archivable_count === 'number' &&
        typeof value.all_finished_archived === 'boolean' &&
        Array.isArray(value.names) &&
        typeof value.more_count === 'number' &&
        isRecord(value.counts) &&
        isRecord(value.progress) &&
        isRecord(value.progress.before) &&
        isRecord(value.progress.after)
    );
}

function isDeletionResult(value: unknown): value is BulkDeletionResult {
    if (!isRecord(value)) {
        return false;
    }

    return (
        value.deleted === true &&
        typeof value.already_deleted === 'boolean' &&
        Array.isArray(value.deleted_ids) &&
        typeof value.deleted_count === 'number' &&
        typeof value.skipped_missing === 'number'
    );
}
