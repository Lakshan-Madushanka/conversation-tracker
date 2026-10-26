import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { archiveSession, restoreSession } from '@/lib/session-archive';

function response(status = 200): Response {
    return new Response(null, { status });
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('archiving a session', () => {
    it('reports a finished session as archived', async () => {
        const fetch = vi.fn(async (_url: string, _options?: RequestInit) =>
            response(),
        );
        vi.stubGlobal('fetch', fetch);

        await expect(archiveSession(7)).resolves.toBe('archived');

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(String(fetch.mock.calls[0]?.[0])).toContain(
            '/sessions/7/archive',
        );
        expect(fetch.mock.calls[0]?.[1]).toMatchObject({ method: 'POST' });
    });

    it('reports the server refusing an unfinished session', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => response(422)),
        );

        await expect(archiveSession(7)).resolves.toBe('unfinished');
    });

    it('reports any other failure calmly', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => response(500)),
        );

        await expect(archiveSession(7)).resolves.toBe('failed');
    });

    it('treats a network failure as a failure, not a crash', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => {
                throw new Error('offline');
            }),
        );

        await expect(archiveSession(7)).resolves.toBe('failed');
    });
});

describe('restoring a session', () => {
    it('says when the session is back', async () => {
        const fetch = vi.fn(async (_url: string, _options?: RequestInit) =>
            response(),
        );
        vi.stubGlobal('fetch', fetch);

        await expect(restoreSession(7)).resolves.toBe(true);

        expect(String(fetch.mock.calls[0]?.[0])).toContain(
            '/sessions/7/restore',
        );
        expect(fetch.mock.calls[0]?.[1]).toMatchObject({ method: 'POST' });
    });

    it('says when it could not be restored', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => response(500)),
        );

        await expect(restoreSession(7)).resolves.toBe(false);
    });

    it('treats a network failure as a failure, not a crash', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => {
                throw new Error('offline');
            }),
        );

        await expect(restoreSession(7)).resolves.toBe(false);
    });
});
