import type { AutoSaveFunction } from '@/composables/useAutoSave';
import {
    autosaveStore,
    autosaveUpdate,
} from '@/actions/App/Http/Controllers/PracticeSessionController';
import type { PracticeQuestionForm, PracticeSessionData } from '@/types';

/**
 * Reads Laravel's CSRF cookie. The app talks to the autosave endpoints with
 * `fetch`, which (unlike Inertia's own client) has to add the header by hand.
 */
function csrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match ? decodeURIComponent(match[1]) : null;
}

/** Drops the client-only `uid` from each question before sending. */
function cleanQuestions(questions: PracticeQuestionForm[]) {
    return questions.map((question) => ({
        question_html: question.question_html,
        answer_html: question.answer_html,
        feedback: question.feedback,
    }));
}

function requestBody(
    request: Parameters<AutoSaveFunction>[0],
): Record<string, unknown> {
    const questions = cleanQuestions(request.questions);

    if (request.sessionId === null) {
        return {
            topic: request.topic,
            questions,
            client_key: request.clientKey,
            save_key: request.saveKey,
        };
    }

    return {
        topic: request.topic,
        questions,
        version: request.version,
        save_key: request.saveKey,
    };
}

/**
 * Sends automatic saves to the server. A first save creates the session; later
 * saves update it. The server reports a stale version as a conflict rather than
 * overwriting newer work.
 */
export const savePracticeSession: AutoSaveFunction = async (
    request,
    options,
) => {
    const creating = request.sessionId === null;
    const url = creating
        ? autosaveStore.url()
        : autosaveUpdate.url(request.sessionId as number);
    const token = csrfToken();

    const response = await fetch(url, {
        method: creating ? 'POST' : 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token === null ? {} : { 'X-XSRF-TOKEN': token }),
        },
        credentials: 'same-origin',
        body: JSON.stringify(requestBody(request)),
        keepalive: options?.keepalive ?? false,
    });

    if (response.status === 201 || response.status === 200) {
        const body = (await response.json()) as {
            session: PracticeSessionData;
            saved_at: string;
        };

        return {
            outcome: 'saved',
            session: body.session,
            savedAt: body.saved_at,
        };
    }

    if (response.status === 422) {
        const body = (await response.json()) as {
            errors?: Record<string, string>;
        };

        return { outcome: 'invalid', errors: body.errors ?? {} };
    }

    if (response.status === 409) {
        const body = (await response.json()) as {
            session: PracticeSessionData;
        };

        return { outcome: 'conflict', session: body.session };
    }

    return { outcome: 'failed' };
};
