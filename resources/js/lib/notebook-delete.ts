import { jsonRequest } from '@/lib/http';
import { deletePreview, destroy } from '@/routes/notebooks';
import type {
    NotebookDeletionPreview,
    NotebookDeletionResult,
} from '@/types/notebook';

/** How opening the delete warning ended. */
export type NotebookDeletePreviewOutcome =
    | { outcome: 'preview'; preview: NotebookDeletionPreview }
    | { outcome: 'missing' }
    | { outcome: 'failed' };

/** How a permanent delete ended. A stale answer carries the fresh warning. */
export type DeleteNotebookOutcome =
    | { outcome: 'deleted'; result: NotebookDeletionResult }
    | { outcome: 'stale'; preview: NotebookDeletionPreview; message: string }
    | { outcome: 'missing' }
    | { outcome: 'failed' };

/** Reads what deleting a notebook would remove, changing nothing. */
export async function fetchNotebookDeletePreview(
    notebookId: number,
): Promise<NotebookDeletePreviewOutcome> {
    try {
        const response = await jsonRequest(deletePreview.url(notebookId));

        if (response.status === 404) {
            return { outcome: 'missing' };
        }

        if (!response.ok) {
            return { outcome: 'failed' };
        }

        const body: unknown = await response.json();

        return isPreview(body)
            ? { outcome: 'preview', preview: body }
            : { outcome: 'failed' };
    } catch {
        return { outcome: 'failed' };
    }
}

/**
 * Deletes the notebook permanently. The token is the impact the learner saw; a
 * notebook that changed since then is refused with the fresh warning instead
 * of being removed under obsolete numbers.
 */
export async function deleteNotebookPermanently(
    notebookId: number,
    token: string,
): Promise<DeleteNotebookOutcome> {
    try {
        const response = await jsonRequest(destroy.url(notebookId), {
            method: 'DELETE',
            body: JSON.stringify({ token }),
        });

        if (response.status === 404) {
            return { outcome: 'missing' };
        }

        if (response.status === 409) {
            const body: unknown = await response.json();

            if (
                isRecord(body) &&
                isPreview(body.preview) &&
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

        if (
            !isRecord(body) ||
            body.deleted !== true ||
            typeof body.already_deleted !== 'boolean' ||
            !isRecord(body.notebook) ||
            typeof body.notebook.id !== 'number' ||
            typeof body.notebook.name !== 'string' ||
            !isRecord(body.counts)
        ) {
            return { outcome: 'failed' };
        }

        return {
            outcome: 'deleted',
            result: body as unknown as NotebookDeletionResult,
        };
    } catch {
        return { outcome: 'failed' };
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isPreview(value: unknown): value is NotebookDeletionPreview {
    if (!isRecord(value)) {
        return false;
    }

    return (
        typeof value.token === 'string' &&
        isRecord(value.notebook) &&
        typeof value.notebook.id === 'number' &&
        typeof value.notebook.name === 'string' &&
        typeof value.notebook.topic === 'string' &&
        isRecord(value.counts) &&
        isRecord(value.items) &&
        Array.isArray(value.reviews)
    );
}
