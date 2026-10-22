import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { discardSession, finishSession } from '@/lib/session-finish';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function stubFetch(
    impl: (url: string, init: RequestInit) => Promise<Response>,
) {
    const mock = vi.fn(impl);
    vi.stubGlobal('fetch', mock);

    return mock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('finishSession', () => {
    it('posts to the finish route and hands back what was saved', async () => {
        const summary = {
            empty: false as const,
            session: { id: 7, version: 4 },
            counts: {
                questions: 2,
                words: 3,
                phrases: 1,
                mistakes: 4,
                grammar_notes: 0,
            },
            needs_attention: 1,
            empty_questions_removed: 1,
        };
        const mock = stubFetch(async () => jsonResponse(summary));

        await expect(finishSession(7)).resolves.toEqual(summary);

        expect(mock).toHaveBeenCalledTimes(1);
        expect(mock.mock.calls[0][0]).toContain('/sessions/7/finish');
        expect(mock.mock.calls[0][1]?.method).toBe('POST');
    });

    it('passes the empty warning through untouched', async () => {
        stubFetch(async () => jsonResponse({ empty: true }));

        await expect(finishSession(7)).resolves.toEqual({ empty: true });
    });

    it('says nothing was finished when the server refuses', async () => {
        stubFetch(async () => jsonResponse({ message: 'Nope' }, 500));

        await expect(finishSession(7)).resolves.toBeNull();
    });

    it('says nothing was finished when the request never arrived', async () => {
        stubFetch(async () => {
            throw new Error('network down');
        });

        await expect(finishSession(7)).resolves.toBeNull();
    });
});

describe('discardSession', () => {
    it('reports a session that was removed', async () => {
        const mock = stubFetch(async () => jsonResponse({ deleted: true }));

        await expect(discardSession(7)).resolves.toBe('discarded');
        expect(mock.mock.calls[0][1]?.method).toBe('DELETE');
    });

    it('reports a session the server refused to remove', async () => {
        stubFetch(async () => jsonResponse({ message: 'not empty' }, 422));

        await expect(discardSession(7)).resolves.toBe('has_content');
    });

    it('reports a discard that could not be attempted', async () => {
        stubFetch(async () => jsonResponse({ message: 'boom' }, 500));

        await expect(discardSession(7)).resolves.toBe('failed');
    });

    it('reports a discard that never reached the app', async () => {
        stubFetch(async () => {
            throw new Error('network down');
        });

        await expect(discardSession(7)).resolves.toBe('failed');
    });
});
