import { jsonRequest } from '@/lib/http';
import {
    deletePermanently as sessionsDeletePermanently,
    deletePreview as sessionsDeletePreview,
} from '@/routes/sessions';
import type { SessionDeletionPreview, SessionDeletionResult } from '@/types';

/** How opening the delete warning ended. */
export type DeletePreviewOutcome =
    | { outcome: 'preview'; preview: SessionDeletionPreview }
    | { outcome: 'missing' }
    | { outcome: 'failed' };

/** How a permanent delete ended. A stale answer carries the fresh numbers. */
export type DeleteSessionOutcome =
    | { outcome: 'deleted'; result: SessionDeletionResult }
    | { outcome: 'stale'; preview: SessionDeletionPreview; message: string }
    | { outcome: 'missing' }
    | { outcome: 'failed' };

/** Reads what deleting a session would remove and change, changing nothing. */
export async function fetchDeletePreview(
    sessionId: number,
    timezone: string,
): Promise<DeletePreviewOutcome> {
    try {
        const response = await jsonRequest(
            sessionsDeletePreview.url(sessionId, { query: { tz: timezone } }),
        );

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
 * Deletes the session permanently. The token is the state the learner saw; a
 * changed session is refused with the fresh warning instead of being removed.
 */
export async function deleteSessionPermanently(
    sessionId: number,
    token: string,
    timezone: string,
): Promise<DeleteSessionOutcome> {
    try {
        const response = await jsonRequest(
            sessionsDeletePermanently.url(sessionId),
            {
                method: 'DELETE',
                body: JSON.stringify({ token, tz: timezone }),
            },
        );

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
            !isRecord(body.session) ||
            typeof body.session.id !== 'number' ||
            typeof body.session.topic !== 'string' ||
            !isRecord(body.counts)
        ) {
            return { outcome: 'failed' };
        }

        return {
            outcome: 'deleted',
            result: body as unknown as SessionDeletionResult,
        };
    } catch {
        return { outcome: 'failed' };
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isPreview(value: unknown): value is SessionDeletionPreview {
    if (!isRecord(value)) {
        return false;
    }

    return (
        typeof value.token === 'string' &&
        isRecord(value.session) &&
        typeof value.session.id === 'number' &&
        typeof value.session.topic === 'string' &&
        isRecord(value.counts) &&
        isRecord(value.progress) &&
        isRecord(value.progress.before) &&
        isRecord(value.progress.after)
    );
}
