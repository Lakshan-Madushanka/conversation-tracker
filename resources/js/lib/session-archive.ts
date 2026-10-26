import { jsonRequest } from '@/lib/http';
import {
    archive as sessionsArchive,
    restore as sessionsRestore,
} from '@/routes/sessions';

/**
 * How an archive request ended. The server refuses an unfinished session with
 * a clear reason, which is shown to the learner rather than treated as a
 * network failure.
 */
export type ArchiveOutcome = 'archived' | 'unfinished' | 'failed';

/** Puts a finished session away. The server changes nothing on a refusal. */
export async function archiveSession(
    sessionId: number,
): Promise<ArchiveOutcome> {
    try {
        const response = await jsonRequest(sessionsArchive.url(sessionId), {
            method: 'POST',
        });

        if (response.ok) {
            return 'archived';
        }

        return response.status === 422 ? 'unfinished' : 'failed';
    } catch {
        return 'failed';
    }
}

/** Brings an archived session back exactly as it was. Safe to repeat. */
export async function restoreSession(sessionId: number): Promise<boolean> {
    try {
        const response = await jsonRequest(sessionsRestore.url(sessionId), {
            method: 'POST',
        });

        return response.ok;
    } catch {
        return false;
    }
}
