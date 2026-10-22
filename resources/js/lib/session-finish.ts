import { jsonRequest } from '@/lib/http';
import {
    destroy as sessionsDestroy,
    finish as sessionsFinish,
} from '@/routes/sessions';
import type { FinishSessionResult } from '@/types';

/** Why a discard could not go through, in words the learner can be told. */
export type DiscardOutcome = 'discarded' | 'has_content' | 'failed';

/**
 * Marks a session finished: cards that held nothing are dropped, the moment is
 * recorded, and what was saved comes back as counts.
 *
 * The server answers `empty: true` instead of finishing when there is nothing
 * worth finishing, so the learner is warned rather than shown an empty
 * summary. A request that never reached the app comes back as null, which the
 * caller has to say out loud — never as "empty".
 */
export async function finishSession(
    sessionId: number,
): Promise<FinishSessionResult | null> {
    try {
        const response = await jsonRequest(sessionsFinish.url(sessionId), {
            method: 'POST',
        });

        if (!response.ok) {
            return null;
        }

        return (await response.json()) as FinishSessionResult;
    } catch {
        return null;
    }
}

/**
 * Removes a session the learner decided not to keep. The server refuses with
 * 422 when the session holds something worth keeping, which is reported
 * separately so the learner is told why nothing was thrown away.
 */
export async function discardSession(
    sessionId: number,
): Promise<DiscardOutcome> {
    try {
        const response = await jsonRequest(sessionsDestroy.url(sessionId), {
            method: 'DELETE',
        });

        if (response.ok) {
            return 'discarded';
        }

        return response.status === 422 ? 'has_content' : 'failed';
    } catch {
        return 'failed';
    }
}
