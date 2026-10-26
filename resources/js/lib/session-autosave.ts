import type { AutoSaveFunction } from '@/composables/useAutoSave';
import {
    autosaveStore,
    autosaveUpdate,
} from '@/actions/App/Http/Controllers/PracticeSessionController';
import { jsonRequest } from '@/lib/http';
import { isRichTextBlank } from '@/lib/rich-text';
import type {
    FeedbackValues,
    GrammarNote,
    PracticeQuestionForm,
    PracticeSessionData,
} from '@/types';

/**
 * Drops the client-only `uid` from each question and removes empty grammar
 * notes before sending. A note the learner opened but left blank is a draft,
 * not content: keeping it out of the payload stops the server from rejecting
 * the save.
 */
function cleanQuestions(questions: PracticeQuestionForm[]) {
    return questions.map((question) => ({
        question_html: question.question_html,
        answer_html: question.answer_html,
        feedback: cleanFeedback(question.feedback),
    }));
}

/** Removes grammar notes that hold only blank space or empty formatting. */
export function cleanFeedback(feedback: FeedbackValues): FeedbackValues {
    return {
        ...feedback,
        grammar_note: feedback.grammar_note.filter(
            (note: GrammarNote) => !isRichTextBlank(note.html),
        ),
    };
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

    const response = await jsonRequest(url, {
        method: creating ? 'POST' : 'PUT',
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

    if (response.status === 423) {
        const body = (await response.json().catch(() => ({}))) as {
            message?: string;
        };

        return {
            outcome: 'archived',
            message:
                body.message ??
                'This session was archived. Restore it to keep editing.',
        };
    }

    return { outcome: 'failed' };
};
