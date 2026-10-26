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
import { archiveSession, restoreSession } from '@/lib/session-archive';
import Index from '@/pages/sessions/Index.vue';
import type { SessionCardData, TopicSummary } from '@/types';

vi.mock('@/lib/session-archive', () => ({
    archiveSession: vi.fn(),
    restoreSession: vi.fn(),
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

function filters(overrides: Partial<LibraryFilters> = {}): LibraryFilters {
    return { ...defaultLibraryFilters(), ...overrides };
}

interface PageOptions {
    sessions?: SessionCardData[];
    total?: number;
    allTotal?: number;
    archivedTotal?: number;
    filters?: LibraryFilters;
    topics?: TopicSummary[];
}

/** Answers a list refresh with the page this test wants to show next. */
function stubPage(options: PageOptions = {}) {
    const sessions = options.sessions ?? [];
    const total = options.total ?? sessions.length;

    const fetch = vi.fn(
        async (_url: string, _options?: RequestInit) =>
            new Response(
                JSON.stringify({
                    sessions,
                    next_cursor: null,
                    has_more: false,
                    total,
                    all_total: options.allTotal ?? total,
                    archived_total: options.archivedTotal ?? 0,
                    filters: options.filters ?? filters(),
                    topics: options.topics ?? [],
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
        archivedTotal?: number;
        allTotal?: number;
        filters?: LibraryFilters;
    } = {},
): VueWrapper {
    mounted = mount(Index, {
        attachTo: document.body,
        props: {
            sessions,
            next_cursor: null,
            has_more: false,
            total: sessions.length,
            all_total: options.allTotal ?? sessions.length,
            archived_total: options.archivedTotal ?? 0,
            filters: options.filters ?? defaultLibraryFilters(),
            topics: [],
            timezone: 'UTC',
        },
    });

    return mounted;
}

async function chooseMenuItem(label: string): Promise<void> {
    (
        mounted?.get('button[aria-haspopup="menu"]').element as
            | HTMLElement
            | undefined
    )?.click();
    await mounted?.vm.$nextTick();
    await mounted?.vm.$nextTick();

    const item = [
        ...document.querySelectorAll<HTMLElement>('[role="menuitem"]'),
    ].find((candidate) => candidate.textContent?.includes(label));

    item?.click();
    await mounted?.vm.$nextTick();
}

beforeEach(() => {
    vi.mocked(archiveSession).mockResolvedValue('archived');
    vi.mocked(restoreSession).mockResolvedValue(true);
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

describe('archiving from the Library list', () => {
    it('archives at once and offers Undo in the message', async () => {
        stubPage({ sessions: [], archivedTotal: 1 });
        const success = vi.spyOn(toast, 'success');

        mountLibrary([session(1)]);

        await chooseMenuItem('Archive');
        await flushPromises();

        expect(archiveSession).toHaveBeenCalledWith(1);
        expect(success).toHaveBeenCalledWith(
            'Session archived.',
            expect.objectContaining({
                action: expect.objectContaining({ label: 'Undo' }),
            }),
        );
    });

    it('restores the session again when Undo is chosen', async () => {
        const fetch = stubPage({ sessions: [session(1)], archivedTotal: 0 });
        const success = vi.spyOn(toast, 'success');

        mountLibrary([session(1)]);

        await chooseMenuItem('Archive');
        await flushPromises();

        const options = success.mock.calls[0]?.[1] as
            | { action?: { onClick?: () => void } }
            | undefined;

        options?.action?.onClick?.();
        await flushPromises();

        expect(restoreSession).toHaveBeenCalledWith(1);
        expect(success).toHaveBeenLastCalledWith('Session restored.');
        expect(fetch).toHaveBeenCalled();
    });

    it('says why an unfinished session cannot be archived', async () => {
        vi.mocked(archiveSession).mockResolvedValue('unfinished');
        const fetch = stubPage();
        const error = vi.spyOn(toast, 'error');

        const wrapper = mountLibrary([session(1)]);
        const card = wrapper.findComponent(SessionCard);

        card.vm.$emit('archive', session(1, { status: 'unfinished' }));
        await flushPromises();

        expect(error).toHaveBeenCalledWith(
            'Finish this session to archive it.',
        );
        expect(fetch).not.toHaveBeenCalled();
    });

    it('explains a failed archive without changing the list', async () => {
        vi.mocked(archiveSession).mockResolvedValue('failed');
        const fetch = stubPage();
        const error = vi.spyOn(toast, 'error');

        const wrapper = mountLibrary([session(1)]);
        const card = wrapper.findComponent(SessionCard);

        card.vm.$emit('archive', session(1));
        await flushPromises();

        expect(error).toHaveBeenCalledWith(
            'We could not archive this session. Nothing was changed.',
        );
        expect(fetch).not.toHaveBeenCalled();
    });
});

describe('restoring from the Archived view', () => {
    it('brings the session back and refreshes the list', async () => {
        const archived = session(1, {
            archived_at: '2026-10-20T09:00:00.000000Z',
        });
        const fetch = stubPage({
            sessions: [session(1)],
            archivedTotal: 0,
        });
        const success = vi.spyOn(toast, 'success');

        mountLibrary([archived], {
            archivedTotal: 1,
            filters: filters({ view: 'archived' }),
        });

        await chooseMenuItem('Restore');
        await flushPromises();

        expect(restoreSession).toHaveBeenCalledWith(1);
        expect(success).toHaveBeenCalledWith('Session restored.');
        expect(fetch).toHaveBeenCalled();
    });

    it('explains a restore that failed', async () => {
        vi.mocked(restoreSession).mockResolvedValue(false);
        const fetch = stubPage();
        const error = vi.spyOn(toast, 'error');

        mountLibrary(
            [
                session(1, {
                    archived_at: '2026-10-20T09:00:00.000000Z',
                }),
            ],
            {
                archivedTotal: 1,
                filters: filters({ view: 'archived' }),
            },
        );

        await chooseMenuItem('Restore');
        await flushPromises();

        expect(error).toHaveBeenCalledWith(
            'We could not restore this session. Nothing was changed.',
        );
        expect(fetch).not.toHaveBeenCalled();
    });
});
