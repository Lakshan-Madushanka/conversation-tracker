import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import { useLibrarySessions } from '@/composables/useLibrarySessions';
import {
    defaultLibraryFilters,
    type LibraryFilters,
} from '@/lib/library-filters';
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
        archived_at: null,
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
        all_total: 0,
        archived_total: 0,
        filters: defaultLibraryFilters(),
        topics: [],
        timezone: 'UTC',
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
    vi.restoreAllMocks();
});

describe('loading more of the Library', () => {
    it('adds the next page and moves the cursor on', async () => {
        const mock = stubPages([
            page({
                sessions: [session(3), session(4)],
                next_cursor: 'third',
                has_more: true,
                total: 6,
                archived_total: 4,
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
        expect(library.archivedTotal.value).toBe(4);
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
                archived_total: 3,
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
            allTotal: 4,
            archivedTotal: 0,
            filters: defaultLibraryFilters(),
            timezone: 'UTC',
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
        expect(library.archivedTotal.value).toBe(3);
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

describe('changing the filters', () => {
    interface FilterRequest {
        url: string;
        options: {
            replace?: boolean;
            preserveState?: boolean;
            preserveScroll?: boolean;
            only?: string[];
            onSuccess?: (page: { props: unknown }) => void;
            onError?: () => void;
            onFinish?: () => void;
        };
    }

    function captureRequests(): FilterRequest[] {
        const requests: FilterRequest[] = [];

        vi.spyOn(router, 'get').mockImplementation((url, _data, options) => {
            requests.push({
                url: url as string,
                options: options as unknown as FilterRequest['options'],
            });
        });

        return requests;
    }

    function filters(overrides: Partial<LibraryFilters> = {}): LibraryFilters {
        return { ...defaultLibraryFilters(), ...overrides };
    }

    it('puts the filters in the address and replaces the history entry', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.applyFilters(
            filters({
                topics: [2],
                types: ['mistake'],
                date: 'custom',
                from: '2026-10-01',
                to: '2026-10-14',
                status: 'unfinished',
                sort: 'oldest',
            }),
        );

        expect(requests).toHaveLength(1);

        const request = requests[0]!;

        expect(request.url).toContain('topics=2');
        expect(request.url).toContain('types=mistake');
        expect(request.url).toContain('date=custom');
        expect(request.url).toContain('from=2026-10-01');
        expect(request.url).toContain('to=2026-10-14');
        expect(request.url).toContain('status=unfinished');
        expect(request.url).toContain('sort=oldest');
        expect(request.options.replace).toBe(true);
        expect(request.options.preserveState).toBe(true);
        expect(request.options.preserveScroll).toBe(true);
        expect(library.updating.value).toBe(true);
    });

    it('ignores the same filter state twice', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.applyFilters(filters({ topics: [1] }));
        library.applyFilters(filters({ topics: [1] }));

        expect(requests).toHaveLength(1);
    });

    it('keeps the chosen order when clearing', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({ filters: filters({ status: 'finished' }) }),
        );

        library.clearFilters();

        expect(requests[0]?.url).not.toContain('status=');
        expect(requests[0]?.url).not.toContain('sort=');
    });

    it('tries the same filters again after an error', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({ filters: filters({ topics: [1] }) }),
        );

        library.retryFilters();

        expect(requests).toHaveLength(1);
        expect(requests[0]?.url).toContain('topics=1');
    });

    it('replaces the list with the page the server sends back', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page({ sessions: [session(1)] }));

        library.applyFilters(filters({ topics: [1] }));

        requests[0]?.options.onSuccess?.({
            props: page({
                sessions: [session(1), session(2)],
                total: 2,
                all_total: 9,
                filters: filters({ topics: [1] }),
                topics: [{ id: 1, name: 'Work' }],
                timezone: 'Europe/London',
            }),
        });

        expect(library.sessions.value.map((card) => card.id)).toEqual([1, 2]);
        expect(library.total.value).toBe(2);
        expect(library.allTotal.value).toBe(9);
        expect(library.filters.value.topics).toEqual([1]);
        expect(library.topics.value).toEqual([{ id: 1, name: 'Work' }]);
        expect(library.timezone.value).toBe('Europe/London');
        expect(library.filterError.value).toBe(false);
    });

    it('explains a failure calmly and stops updating', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.applyFilters(filters({ status: 'finished' }));

        requests[0]?.options.onSuccess?.({ props: {} });

        expect(library.filterError.value).toBe(true);

        requests[0]?.options.onFinish?.();

        expect(library.updating.value).toBe(false);
    });

    it('lets only the latest filter request change the list', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.applyFilters(filters({ topics: [1] }));
        library.applyFilters(filters({ topics: [2] }));

        requests[0]?.options.onSuccess?.({
            props: page({ sessions: [session(1)] }),
        });

        expect(library.sessions.value).toEqual([]);
        expect(library.updating.value).toBe(true);

        requests[1]?.options.onSuccess?.({
            props: page({
                sessions: [session(2)],
                filters: filters({ topics: [2] }),
            }),
        });

        expect(library.sessions.value.map((card) => card.id)).toEqual([2]);
    });

    it('puts the search in the address and keeps every filter', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({ filters: filters({ topics: [1], status: 'finished' }) }),
        );

        library.setSearch('beach');

        expect(requests).toHaveLength(1);
        expect(requests[0]?.url).toContain('q=beach');
        expect(requests[0]?.url).toContain('topics=1');
        expect(requests[0]?.url).toContain('status=finished');
        expect(library.filters.value.q).toBe('beach');
    });

    it('clears only the search, keeping the filters', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({ filters: filters({ topics: [1], q: 'beach' }) }),
        );

        library.clearSearch();

        expect(requests).toHaveLength(1);
        expect(requests[0]?.url).toContain('topics=1');
        expect(requests[0]?.url).not.toContain('q=');
        expect(library.filters.value.q).toBe('');
    });

    it('ignores the same search twice', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.setSearch('beach');
        library.setSearch('beach');

        expect(requests).toHaveLength(1);
    });

    it('lets only the latest search change the list', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.setSearch('beach');
        library.setSearch('soup');

        requests[0]?.options.onSuccess?.({
            props: page({
                sessions: [session(1)],
                filters: filters({ q: 'beach' }),
            }),
        });

        expect(library.sessions.value).toEqual([]);

        requests[1]?.options.onSuccess?.({
            props: page({
                sessions: [session(2)],
                filters: filters({ q: 'soup' }),
            }),
        });

        expect(library.sessions.value.map((card) => card.id)).toEqual([2]);
        expect(library.filters.value.q).toBe('soup');
    });

    it('switches to the Archived view and leaves status out of it', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({
                filters: filters({
                    topics: [1],
                    status: 'finished',
                    q: 'beach',
                }),
            }),
        );

        library.setView('archived');

        expect(requests).toHaveLength(1);
        expect(requests[0]?.url).toContain('view=archived');
        expect(requests[0]?.url).toContain('topics=1');
        expect(requests[0]?.url).toContain('q=beach');
        expect(requests[0]?.url).not.toContain('status=');
        expect(library.filters.value.view).toBe('archived');
        expect(library.filters.value.status).toBe('all');
    });

    it('switches back to the Library view without an archived address', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({ filters: filters({ view: 'archived' }) }),
        );

        library.setView('library');

        expect(requests).toHaveLength(1);
        expect(requests[0]?.url).not.toContain('view=archived');
        expect(library.filters.value.view).toBe('library');
    });

    it('keeps the Library and Archived searches apart', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(
            page({ filters: filters({ q: 'beach' }) }),
        );

        library.setView('archived');
        library.setSearch('soup');

        expect(requests).toHaveLength(2);
        expect(requests[1]?.url).toContain('view=archived');
        expect(requests[1]?.url).toContain('q=beach');
        expect(requests[1]?.url).toContain('aq=soup');
        expect(library.filters.value.q).toBe('beach');
        expect(library.filters.value.archivedSearch).toBe('soup');

        library.setView('library');

        expect(requests[2]?.url).toContain('q=beach');
        expect(requests[2]?.url).toContain('aq=soup');
        expect(requests[2]?.url).not.toContain('view=archived');
    });

    it('takes the archived total from the page the server sends', () => {
        const requests = captureRequests();
        const library = useLibrarySessions(page());

        library.setView('archived');

        requests[0]?.options.onSuccess?.({
            props: page({
                filters: filters({ view: 'archived' }),
                archived_total: 5,
            }),
        });

        expect(library.filters.value.view).toBe('archived');
        expect(library.archivedTotal.value).toBe(5);
    });
});
