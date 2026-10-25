import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useLibrarySessions } from '@/composables/useLibrarySessions';
import type { LibraryPage } from '@/lib/library';
import type { SessionCardData } from '@/types';

function session(
    id: number,
    overrides: Partial<SessionCardData> = {},
): SessionCardData {
    return {
        id,
        topic: `Topic ${id}`,
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
        status: 'finished',
        counts: {
            questions: 1,
            words: 1,
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
        },
        ...overrides,
    };
}

function page(overrides: Partial<LibraryPage> = {}): LibraryPage {
    return {
        sessions: [],
        next_cursor: null,
        has_more: false,
        total: 0,
        ...overrides,
    };
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

/** Answers each request with the next page the test prepared. */
function stubPages(pages: LibraryPage[]) {
    let index = 0;

    const mock = vi.fn(async (_url: string) => {
        const next = pages[index] ?? page();
        index += 1;

        return jsonResponse(next);
    });

    vi.stubGlobal('fetch', mock);

    return mock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('loading more of the Library', () => {
    it('adds the next page and moves the cursor on', async () => {
        const mock = stubPages([
            page({
                sessions: [session(3), session(4)],
                next_cursor: 'third',
                has_more: true,
                total: 6,
            }),
        ]);

        const library = useLibrarySessions(
            page({
                sessions: [session(1), session(2)],
                next_cursor: 'second',
                has_more: true,
                total: 6,
            }),
        );

        await expect(library.loadMore()).resolves.toBe(true);

        expect(library.sessions.value.map((card) => card.id)).toEqual([
            1, 2, 3, 4,
        ]);
        expect(library.nextCursor.value).toBe('third');
        expect(library.hasMore.value).toBe(true);
        expect(library.total.value).toBe(6);
        expect(mock).toHaveBeenCalledTimes(1);
        expect(String(mock.mock.calls[0]?.[0])).toContain('cursor=second');
    });

    it('explains a failed page and keeps what is already shown', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ message: 'nope' }, 500)),
        );

        const library = useLibrarySessions(
            page({
                sessions: [session(1)],
                next_cursor: 'second',
                has_more: true,
                total: 2,
            }),
        );

        await expect(library.loadMore()).resolves.toBe(false);

        expect(library.error.value).toBe(true);
        expect(library.sessions.value.map((card) => card.id)).toEqual([1]);
    });

    it('does nothing when there is no next page', async () => {
        const mock = stubPages([page()]);

        const library = useLibrarySessions(page({ sessions: [session(1)] }));

        await expect(library.loadMore()).resolves.toBe(false);

        expect(mock).not.toHaveBeenCalled();
    });
});

describe('refreshing the Library after an edit', () => {
    it('updates a card that is not on the first page', async () => {
        // The learner had loaded four cards before opening one to edit it.
        const mock = stubPages([
            page({
                sessions: [session(1), session(2)],
                next_cursor: 'third',
                has_more: true,
                total: 4,
            }),
            page({
                sessions: [
                    session(3),
                    session(4, {
                        last_edited_at: '2026-10-14T09:00:00.000000Z',
                    }),
                ],
                next_cursor: null,
                has_more: false,
                total: 4,
            }),
        ]);

        const library = useLibrarySessions(
            page({
                sessions: [session(1), session(2)],
                next_cursor: 'second',
                has_more: true,
                total: 4,
            }),
        );

        library.restore({
            sessions: [session(1), session(2), session(3), session(4)],
            nextCursor: null,
            hasMore: false,
            total: 4,
            scrollY: 0,
            savedAt: Date.now(),
        });

        await library.refresh();

        expect(library.sessions.value).toHaveLength(4);
        expect(library.sessions.value[3]?.last_edited_at).toBe(
            '2026-10-14T09:00:00.000000Z',
        );
        expect(mock).toHaveBeenCalledTimes(2);
        expect(library.hasMore.value).toBe(false);
        expect(library.nextCursor.value).toBeNull();
    });

    it('leaves the list alone when the network drops', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => {
                throw new Error('offline');
            }),
        );

        const library = useLibrarySessions(
            page({ sessions: [session(1)], total: 1 }),
        );

        await library.refresh();

        expect(library.sessions.value.map((card) => card.id)).toEqual([1]);
        expect(library.total.value).toBe(1);
    });

    it('does nothing when the Library was empty', async () => {
        const mock = stubPages([page()]);

        const library = useLibrarySessions(page());

        await library.refresh();

        expect(mock).not.toHaveBeenCalled();
    });
});
