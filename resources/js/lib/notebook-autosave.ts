import type { AutoSaveFunction } from '@/composables/useAutoSave';
import { update as autosaveUpdate } from '@/routes/notebooks/autosave';
import { jsonRequest } from '@/lib/http';
import { isRichTextBlank } from '@/lib/rich-text';
import type { NotebookData, NotebookSnapshot } from '@/types/notebook';
import type { GrammarNote } from '@/types/practice';

function cleanFeedback(feedback: NotebookSnapshot['feedback']) {
    return {
        ...feedback,
        grammar_note: feedback.grammar_note.filter(
            (note: GrammarNote) => !isRichTextBlank(note.html),
        ),
    };
}

export const saveNotebook: AutoSaveFunction<
    NotebookSnapshot,
    NotebookData
> = async (request, options) => {
    const url = autosaveUpdate.url(request.sessionId as number);

    const response = await jsonRequest(url, {
        method: 'PUT',
        body: JSON.stringify({
            name: request.name,
            topic: request.topic,
            description: request.description,
            version: request.version,
            save_key: request.saveKey,
            feedback: cleanFeedback(request.feedback),
        }),
        keepalive: options?.keepalive ?? false,
    });

    if (response.status === 200) {
        const body = (await response.json()) as {
            notebook: NotebookData;
            saved_at: string;
        };

        return {
            outcome: 'saved',
            saved: body.notebook,
            savedAt: body.saved_at,
        };
    }

    if (response.status === 404) {
        return { outcome: 'deleted' };
    }

    if (response.status === 422) {
        const body = (await response.json()) as {
            errors?: Record<string, string>;
        };

        return { outcome: 'invalid', errors: body.errors ?? {} };
    }

    if (response.status === 409) {
        const body = (await response.json()) as { notebook: NotebookData };

        return { outcome: 'conflict', saved: body.notebook };
    }

    return { outcome: 'failed' };
};
