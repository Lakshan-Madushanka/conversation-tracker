import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { AutoSaveRequest } from '@/composables/useAutoSave';
import { emptyFeedbackValues } from '@/lib/feedback';
import { cleanFeedback, savePracticeSession } from '@/lib/session-autosave';

function request(overrides: Partial<AutoSaveRequest> = {}): AutoSaveRequest {
    return {
        sessionId: 5,
        version: 1,
        clientKey: 'client-key',
        saveKey: 'save-key',
        topic: 'Travel',
        questions: [
            {
                uid: 'u1',
                question_html: '<p>Where did you go?</p>',
                answer_html: '<p>I went to the beach.</p>',
                feedback: emptyFeedbackValues(),
            },
        ],
        ...overrides,
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('cleanFeedback', () => {
    it('keeps grammar notes that hold content', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            grammar_note: [
                { id: 'n1', html: '<p>Use the past tense.</p>' },
                { id: 'n2', html: '<p>Articles come before nouns.</p>' },
            ],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.grammar_note).toHaveLength(2);
    });

    it('removes a grammar note left empty', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            grammar_note: [
                { id: 'n1', html: '<p>Keep me</p>' },
                { id: 'n2', html: '' },
            ],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.grammar_note).toHaveLength(1);
        expect(cleaned.grammar_note[0]?.id).toBe('n1');
    });

    it('removes a grammar note holding only empty formatting', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            grammar_note: [
                { id: 'n1', html: '<p></p>' },
                { id: 'n2', html: '<p>   </p>' },
                { id: 'n3', html: '<p><br></p>' },
            ],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.grammar_note).toHaveLength(0);
    });

    it('keeps other feedback boxes untouched', () => {
        const feedback = {
            ...emptyFeedbackValues(),
            mistake: [
                {
                    id: 'm1',
                    text: 'I go → I went',
                    correction_html: '',
                    reason: '',
                },
            ],
            grammar_note: [{ id: 'n1', html: '' }],
        };

        const cleaned = cleanFeedback(feedback);

        expect(cleaned.mistake).toHaveLength(1);
        expect(cleaned.grammar_note).toHaveLength(0);
    });
});

describe('saving a session that was archived elsewhere', () => {
    it('reports the archive with the message the server sent', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async (_url: string, _options?: RequestInit) =>
                    new Response(
                        JSON.stringify({
                            archived: true,
                            message:
                                'This session was archived. Restore it to keep editing.',
                        }),
                        {
                            status: 423,
                            headers: { 'Content-Type': 'application/json' },
                        },
                    ),
            ),
        );

        await expect(savePracticeSession(request())).resolves.toEqual({
            outcome: 'archived',
            message: 'This session was archived. Restore it to keep editing.',
        });
    });

    it('falls back to calm words when the body cannot be read', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async (_url: string, _options?: RequestInit) =>
                    new Response('not json', { status: 423 }),
            ),
        );

        await expect(savePracticeSession(request())).resolves.toEqual({
            outcome: 'archived',
            message: 'This session was archived. Restore it to keep editing.',
        });
    });
});
