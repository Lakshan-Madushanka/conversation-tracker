import { nextTick } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { toast } from 'vue-sonner';
import SessionCard from '@/components/SessionCard.vue';
import {
    defaultLibraryFilters,
    type LibraryFilters,
} from '@/lib/library-filters';
import {
    deleteSessionPermanently,
    fetchDeletePreview,
} from '@/lib/session-delete';
import Index from '@/pages/sessions/Index.vue';
import type { SessionCardData, SessionDeletionPreview } from '@/types';

vi.mock('@/lib/session-delete', () => ({
    fetchDeletePreview: vi.fn(),
    deleteSessionPermanently: vi.fn(),
}));

let mounted: VueWrapper | undefined;

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
        status: 'unfinished',
        counts: {
            questions: 1,
            words: 1,
            phrases: 0,
            mistakes: 0,
            grammar_notes: 0,
        },
        ...overrides,
    };
}

function preview(): SessionDeletionPreview {
    return {
        session: {
            id: 1,
            topic: 'Topic 1',
            session_date: '2026-10-12T09:00:00.000000Z',
            finished: false,
            archived: false,
        },
        counts: {
            questions: 1,
            words: 1,
            phrases: 0,
            mistakes: 0,
            grammar_notes: 0,
        },
        progress: {
            before: {
                words: 0,
                phrases: 0,
                mistakes: 0,
                grammar_notes: 0,
                practice_days: 0,
                current_streak: 0,
            },
            after: {
                words: 0,
                phrases: 0,
                mistakes: 0,
                grammar_notes: 0,
                practice_days: 0,
                current_streak: 0,
            },
        },
        token: 'token-1',
    };
}

/** Answers a list refresh with the page this test wants to show next. */
function stubPage(sessions: SessionCardData[] = []) {
    const fetch = vi.fn(
        async (_url: string, _options?: RequestInit) =>
            new Response(
                JSON.stringify({
                    sessions,
                    next_cursor: null,
                    has_more: false,
                    total: sessions.length,
                    all_total: sessions.length,
                    archived_total: 0,
                    filters: defaultLibraryFilters(),
                    topics: [],
                    timezone: 'UTC',
                }),
                {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                },
            ),
    );

    vi.stubGlobal('fetch', fetch);

    return fetch;
}

function mountLibrary(
    sessions: SessionCardData[],
    options: {
        filters?: LibraryFilters;
        archivedTotal?: number;
    } = {},
): VueWrapper {
    mounted = mount(Index, {
        attachTo: document.body,
        props: {
            sessions,
            next_cursor: null,
            has_more: false,
            total: sessions.length,
            all_total: sessions.length,
            archived_total: options.archivedTotal ?? 0,
            filters: options.filters ?? defaultLibraryFilters(),
            topics: [],
            timezone: 'UTC',
        },
    });

    return mounted;
}

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.includes(text),
    );
}

async function openDelete(sessionId: number): Promise<void> {
    mounted
        ?.findAllComponents(SessionCard)
        .find((card) => card.props('session').id === sessionId)
        ?.vm.$emit('delete', session(sessionId));

    await flushPromises();
}

beforeEach(() => {
    vi.mocked(fetchDeletePreview).mockResolvedValue({
        outcome: 'preview',
        preview: preview(),
    });

    vi.mocked(deleteSessionPermanently).mockResolvedValue({
        outcome: 'deleted',
        result: {
            deleted: true,
            already_deleted: false,
            session: { id: 1, topic: 'Topic 1' },
            counts: {
                questions: 1,
                words: 1,
                phrases: 0,
                mistakes: 0,
                grammar_notes: 0,
            },
        },
    });

    window.sessionStorage.clear();
});

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    vi.clearAllMocks();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.sessionStorage.clear();
    document.body.innerHTML = '';
});

describe('deleting from the Library list', () => {
    it('removes the card, confirms once, and focuses the next card', async () => {
        const fetch = stubPage([session(2)]);
        const success = vi.spyOn(toast, 'success');

        mountLibrary([session(1), session(2)]);
        await openDelete(1);

        buttonWith('Delete permanently')?.click();

        await flushPromises();
        await nextTick();

        expect(deleteSessionPermanently).toHaveBeenCalledWith(
            1,
            'token-1',
            expect.any(String),
        );
        expect(success).toHaveBeenCalledWith('Deleted “Topic 1”.');
        expect(success.mock.calls[0]?.[1]).toBeUndefined();

        expect(fetch).toHaveBeenCalled();
        expect(document.body.textContent).not.toContain('Topic 1');
        expect(document.activeElement?.id).toBe('session-link-2');
    });

    it('shows the empty state and focuses the heading when nothing is left', async () => {
        stubPage([]);

        mountLibrary([session(1)]);
        await openDelete(1);

        buttonWith('Delete permanently')?.click();

        await flushPromises();
        await nextTick();

        expect(document.body.textContent).toContain('No sessions yet');
        expect(document.activeElement?.id).toBe('library-heading');
    });

    it('removes a session that was already deleted without a second claim', async () => {
        vi.mocked(fetchDeletePreview).mockResolvedValue({ outcome: 'missing' });
        const info = vi.spyOn(toast, 'info');
        const fetch = stubPage([]);

        mountLibrary([session(1)]);
        await openDelete(1);

        await flushPromises();
        await nextTick();

        expect(deleteSessionPermanently).not.toHaveBeenCalled();
        expect(info).toHaveBeenCalledWith('This session was already deleted.');
        expect(fetch).toHaveBeenCalled();
        expect(document.body.textContent).not.toContain('Topic 1');
    });

    it('deletes from the Archived view and leaves the learner there', async () => {
        stubPage([]);

        mountLibrary(
            [
                session(1, {
                    status: 'finished',
                    archived_at: '2026-10-20T09:00:00.000000Z',
                }),
            ],
            {
                archivedTotal: 1,
                filters: {
                    ...defaultLibraryFilters(),
                    view: 'archived',
                },
            },
        );

        await openDelete(1);

        buttonWith('Delete permanently')?.click();

        await flushPromises();
        await nextTick();

        expect(document.body.textContent).toContain('No archived sessions');
        expect(document.activeElement?.id).toBe('library-heading');
    });
});
