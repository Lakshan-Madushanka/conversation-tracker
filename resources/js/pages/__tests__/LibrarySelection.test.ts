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
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import LibraryFilterBar from '@/components/LibraryFilterBar.vue';
import { shortDate } from '@/lib/format';
import {
    defaultLibraryFilters,
    type LibraryFilters,
} from '@/lib/library-filters';
import {
    archiveSessions,
    deleteSessionsPermanently,
    fetchBulkDeletePreview,
    fetchSelection,
    restoreSessions,
} from '@/lib/session-bulk';
import Index from '@/pages/sessions/Index.vue';
import type {
    ProgressNumbers,
    SessionCardData,
    SessionDeletionBatchPreview,
} from '@/types';

vi.mock('@/lib/session-bulk', () => ({
    archiveSessions: vi.fn(),
    deleteSessionsPermanently: vi.fn(),
    fetchBulkDeletePreview: vi.fn(),
    fetchSelection: vi.fn(),
    restoreSessions: vi.fn(),
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
            phrases: 0,
            mistakes: 0,
            grammar_notes: 0,
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
    hasMore?: boolean;
    nextCursor?: string | null;
}

/** Answers a list refresh with the page this test wants to show next. */
function stubPage(options: PageOptions = {}) {
    const sessions = options.sessions ?? [];

    const fetch = vi.fn(
        async (_url: string, _options?: RequestInit) =>
            new Response(
                JSON.stringify({
                    sessions,
                    next_cursor: options.nextCursor ?? null,
                    has_more: options.hasMore ?? false,
                    total: options.total ?? sessions.length,
                    all_total: options.allTotal ?? sessions.length,
                    archived_total: options.archivedTotal ?? 0,
                    filters: options.filters ?? filters(),
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
        archivedTotal?: number;
        allTotal?: number;
        filters?: LibraryFilters;
        hasMore?: boolean;
        nextCursor?: string | null;
    } = {},
): VueWrapper {
    mounted = mount(Index, {
        attachTo: document.body,
        props: {
            sessions,
            next_cursor: options.nextCursor ?? null,
            has_more: options.hasMore ?? false,
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

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.trim() === text,
    );
}

async function beginSelection(): Promise<void> {
    buttonWith('Select')?.click();
    await flushPromises();
}

function countText(): string {
    return (
        document
            .querySelector('section[aria-label="Selection actions"] p')
            ?.textContent?.trim() ?? ''
    );
}

function preview(
    overrides: Partial<SessionDeletionBatchPreview> = {},
): SessionDeletionBatchPreview {
    const numbers: ProgressNumbers = {
        words: 10,
        phrases: 4,
        mistakes: 3,
        grammar_notes: 2,
        practice_days: 5,
        current_streak: 3,
    };

    return {
        requested_count: 2,
        session_count: 2,
        missing_count: 0,
        finished_count: 1,
        unfinished_count: 1,
        archivable_count: 1,
        all_finished_archived: false,
        names: [
            {
                topic: 'Topic 1',
                session_date: '2026-10-12T09:00:00.000000Z',
            },
        ],
        more_count: 1,
        counts: {
            questions: 2,
            words: 2,
            phrases: 0,
            mistakes: 0,
            grammar_notes: 0,
        },
        progress: { before: numbers, after: { ...numbers, words: 8 } },
        token: 'token-1',
        ...overrides,
    };
}

beforeEach(() => {
    vi.mocked(fetchSelection).mockResolvedValue({
        outcome: 'selection',
        selection: {
            ids: [],
            finished_ids: [],
            total: 0,
            limit: 1000,
            truncated: false,
        },
    });

    vi.mocked(archiveSessions).mockResolvedValue({
        archived: true,
        archived_ids: [1],
        archived_count: 1,
        skipped_unfinished: 0,
        skipped_archived: 0,
        skipped_missing: 0,
    });

    vi.mocked(restoreSessions).mockResolvedValue({
        restored: true,
        restored_ids: [1],
        restored_count: 1,
        skipped_not_archived: 0,
        skipped_missing: 0,
    });

    vi.mocked(fetchBulkDeletePreview).mockResolvedValue(preview());

    vi.mocked(deleteSessionsPermanently).mockResolvedValue({
        outcome: 'deleted',
        result: {
            deleted: true,
            already_deleted: false,
            deleted_ids: [1, 2],
            deleted_count: 2,
            skipped_missing: 0,
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
    vi.useRealTimers();
    window.sessionStorage.clear();
    document.body.innerHTML = '';
});

describe('turning on selection mode', () => {
    it('shows a labeled checkbox on each card and hides the card menu', async () => {
        mountLibrary([session(1), session(2, { status: 'unfinished' })]);

        await beginSelection();

        expect(buttonWith('Done')).toBeDefined();
        expect(
            document.querySelector('button[aria-haspopup="menu"]'),
        ).toBeNull();

        const first = document.getElementById('session-select-1');
        const label = document.querySelector('label[for="session-select-1"]');

        expect(first?.getAttribute('role')).toBe('checkbox');
        expect(label?.textContent?.trim()).toBe(
            `Select “Topic 1” from ${shortDate('2026-10-12T09:00:00.000000Z')}, finished.`,
        );

        expect(countText()).toBe('0 selected');
        expect(buttonWith('Archive')?.disabled).toBe(true);
        expect(buttonWith('Delete')?.disabled).toBe(true);
    });

    it('selects a card instead of opening it, and says what can be archived', async () => {
        mountLibrary([session(1), session(2, { status: 'unfinished' })]);

        await beginSelection();

        await mounted?.get('#session-select-card-2').trigger('click');

        expect(countText()).toBe('1 selected');
        expect(document.body.textContent).toContain(
            'Unfinished sessions cannot be archived.',
        );
        expect(buttonWith('Archive')?.disabled).toBe(true);
        expect(buttonWith('Delete')?.disabled).toBe(false);
        expect(
            document
                .getElementById('session-select-card-2')
                ?.getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('selects the whole range when Shift is held', async () => {
        mountLibrary([session(1), session(2), session(3), session(4)]);

        await beginSelection();

        await mounted?.get('#session-select-card-1').trigger('click');
        await mounted
            ?.get('#session-select-card-4')
            .trigger('click', { shiftKey: true });

        expect(countText()).toBe('4 selected');
    });

    it('turns selection mode off again from the header', async () => {
        mountLibrary([session(1)]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        buttonWith('Done')?.click();
        await flushPromises();

        expect(buttonWith('Select')).toBeDefined();
        expect(document.getElementById('session-select-1')).toBeNull();
        expect(
            document.querySelector('section[aria-label="Selection actions"]'),
        ).toBeNull();
    });
});

describe('choosing every matching session', () => {
    it('selects every match, not only the loaded cards', async () => {
        mountLibrary([session(1), session(2)], { allTotal: 12 });

        await beginSelection();

        vi.mocked(fetchSelection).mockResolvedValue({
            outcome: 'selection',
            selection: {
                ids: [1, 2, 3, 4, 5],
                finished_ids: [1, 2, 3, 4, 5],
                total: 5,
                limit: 1000,
                truncated: false,
            },
        });

        buttonWith('Select all')?.click();
        await flushPromises();

        expect(fetchSelection).toHaveBeenCalledWith({
            tz: expect.any(String),
        });
        expect(countText()).toBe('5 selected');
        expect(document.body.textContent).toContain(
            'All of these finished sessions can be archived.',
        );
    });

    it('refuses a list larger than the limit and explains why', async () => {
        mountLibrary([session(1)]);

        await beginSelection();

        vi.mocked(fetchSelection).mockResolvedValue({
            outcome: 'selection',
            selection: {
                ids: [],
                finished_ids: [],
                total: 1500,
                limit: 1000,
                truncated: true,
            },
        });

        buttonWith('Select all')?.click();
        await flushPromises();

        expect(countText()).toBe('0 selected');
        expect(document.body.textContent).toContain(
            'This list has 1500 sessions, and up to 1000 can be selected at once. Nothing was selected.',
        );
    });

    it('explains a Select all that could not reach the server', async () => {
        mountLibrary([session(1)]);

        await beginSelection();

        vi.mocked(fetchSelection).mockResolvedValue({ outcome: 'failed' });

        buttonWith('Select all')?.click();
        await flushPromises();

        expect(countText()).toBe('0 selected');
        expect(document.body.textContent).toContain(
            'We could not select every session.',
        );
    });
});

describe('what clears the selection', () => {
    it('clears it when a filter changes and says so calmly', async () => {
        vi.useFakeTimers();
        vi.spyOn(router, 'get').mockImplementation(() => {});

        mountLibrary([session(1)]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        mounted
            ?.findComponent(LibraryFilterBar)
            .vm.$emit('change', filters({ status: 'finished' }));

        await nextTick();
        vi.advanceTimersByTime(300);
        await nextTick();

        expect(countText()).toBe('0 selected');
        expect(document.body.textContent).toContain(
            'Selection cleared because the list changed.',
        );
    });

    it('keeps the selection when only the order changes', async () => {
        vi.spyOn(router, 'get').mockImplementation(() => {});

        mountLibrary([session(1), session(2)]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        mounted
            ?.findComponent(LibraryFilterBar)
            .vm.$emit('change', filters({ sort: 'oldest' }));

        await nextTick();
        await flushPromises();

        expect(countText()).toBe('1 selected');
    });

    it('ends selection mode when the learner changes view', async () => {
        vi.spyOn(router, 'get').mockImplementation(() => {});

        mountLibrary([session(1)]);

        await beginSelection();

        await mounted
            ?.findAll('[role="tab"]')
            .find((tab) => tab.text().includes('Archived'))
            ?.trigger('mousedown');

        await nextTick();

        expect(buttonWith('Select')).toBeDefined();
    });
});

describe('loading more sessions', () => {
    it('keeps the sessions already chosen', async () => {
        const fetch = stubPage({
            sessions: [session(2), session(3)],
            total: 3,
        });

        mountLibrary([session(1)], {
            hasMore: true,
            nextCursor: 'cursor-1',
            allTotal: 3,
        });

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        buttonWith('Load more sessions')?.click();
        await flushPromises();
        await nextTick();

        expect(fetch).toHaveBeenCalled();
        expect(countText()).toBe('1 selected');
        expect(document.getElementById('session-select-2')).not.toBeNull();
        expect(
            document
                .getElementById('session-select-1')
                ?.getAttribute('aria-checked'),
        ).toBe('true');
    });
});

describe('archiving several sessions', () => {
    it('archives at once, closes selection mode, and offers one Undo', async () => {
        stubPage({ sessions: [], archivedTotal: 1 });
        const success = vi.spyOn(toast, 'success');

        vi.mocked(archiveSessions).mockResolvedValue({
            archived: true,
            archived_ids: [1],
            archived_count: 1,
            skipped_unfinished: 1,
            skipped_archived: 0,
            skipped_missing: 0,
        });

        mountLibrary([session(1), session(2, { status: 'unfinished' })]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');
        await mounted?.get('#session-select-card-2').trigger('click');

        buttonWith('Archive')?.click();
        await flushPromises();

        expect(archiveSessions).toHaveBeenCalledWith([1, 2]);
        expect(success).toHaveBeenCalledWith(
            '1 session archived. 1 session was skipped because unfinished sessions cannot be archived.',
            expect.objectContaining({
                action: expect.objectContaining({ label: 'Undo' }),
            }),
        );
        expect(buttonWith('Select')).toBeDefined();
    });

    it('restores only the sessions the archive really changed', async () => {
        stubPage({ sessions: [session(1)], archivedTotal: 0 });
        const success = vi.spyOn(toast, 'success');

        mountLibrary([session(1), session(2)]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        buttonWith('Archive')?.click();
        await flushPromises();

        const options = success.mock.calls.at(-1)?.[1] as
            | { action?: { onClick?: () => void } }
            | undefined;

        options?.action?.onClick?.();
        await flushPromises();

        expect(restoreSessions).toHaveBeenCalledWith([1]);
        expect(success).toHaveBeenLastCalledWith('1 session restored.');
    });

    it('explains why Archive is unavailable for unfinished sessions only', async () => {
        mountLibrary([session(1, { status: 'unfinished' })]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        expect(document.body.textContent).toContain(
            'Unfinished sessions cannot be archived.',
        );
        expect(buttonWith('Archive')?.disabled).toBe(true);

        buttonWith('Archive')?.click();
        await flushPromises();

        expect(archiveSessions).not.toHaveBeenCalled();
    });
});

describe('restoring several sessions', () => {
    it('restores the Archived view selection and offers one Undo', async () => {
        stubPage({
            sessions: [],
            archivedTotal: 0,
            filters: filters({ view: 'archived' }),
        });
        const success = vi.spyOn(toast, 'success');

        mountLibrary(
            [
                session(1, { archived_at: '2026-10-20T09:00:00.000000Z' }),
                session(2, { archived_at: '2026-10-21T09:00:00.000000Z' }),
            ],
            {
                archivedTotal: 2,
                filters: filters({ view: 'archived' }),
            },
        );

        vi.mocked(restoreSessions).mockResolvedValue({
            restored: true,
            restored_ids: [1, 2],
            restored_count: 2,
            skipped_not_archived: 0,
            skipped_missing: 0,
        });

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');
        await mounted?.get('#session-select-card-2').trigger('click');

        buttonWith('Restore')?.click();
        await flushPromises();

        expect(restoreSessions).toHaveBeenCalledWith([1, 2]);
        expect(success).toHaveBeenCalledWith(
            '2 sessions restored.',
            expect.objectContaining({
                action: expect.objectContaining({ label: 'Undo' }),
            }),
        );

        const options = success.mock.calls.at(-1)?.[1] as
            | { action?: { onClick?: () => void } }
            | undefined;

        options?.action?.onClick?.();
        await flushPromises();

        expect(archiveSessions).toHaveBeenCalledWith([1, 2]);
    });
});

describe('deleting several sessions', () => {
    it('opens the warning with the whole selection and deletes after confirmation', async () => {
        stubPage({ sessions: [] });
        const success = vi.spyOn(toast, 'success');

        mountLibrary([session(1), session(2, { status: 'unfinished' })]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');
        await mounted?.get('#session-select-card-2').trigger('click');

        buttonWith('Delete')?.click();
        await flushPromises();

        expect(fetchBulkDeletePreview).toHaveBeenCalledWith(
            [1, 2],
            expect.any(String),
        );
        expect(document.body.textContent).toContain('Delete 2 sessions?');

        document.getElementById('bulk-delete-understand')?.click();
        await flushPromises();

        buttonWith('Delete permanently')?.click();
        await flushPromises();

        expect(deleteSessionsPermanently).toHaveBeenCalledWith(
            [1, 2],
            'token-1',
            expect.any(String),
        );
        expect(success).toHaveBeenCalledWith('2 sessions deleted.');
        expect(buttonWith('Select')).toBeDefined();
    });

    it('names and skips a selection that was already deleted elsewhere', async () => {
        stubPage({ sessions: [] });
        const info = vi.spyOn(toast, 'info');

        vi.mocked(fetchBulkDeletePreview).mockResolvedValue(
            preview({
                session_count: 0,
                finished_count: 0,
                unfinished_count: 0,
                archivable_count: 0,
                names: [],
            }),
        );

        mountLibrary([session(1)]);

        await beginSelection();
        await mounted?.get('#session-select-card-1').trigger('click');

        buttonWith('Delete')?.click();
        await flushPromises();

        expect(info).toHaveBeenCalledWith(
            'These sessions were already deleted.',
        );
        expect(deleteSessionsPermanently).not.toHaveBeenCalled();
        expect(buttonWith('Select')).toBeDefined();
    });
});

describe('whole sessions in search results', () => {
    it('selects the session, never one matching item', async () => {
        mountLibrary([
            session(1, {
                match_count: 1,
                matches: [
                    {
                        key: 'entry:vocabulary:v1',
                        kind: 'entry',
                        type: 'vocabulary',
                        question_position: 1,
                        entry_id: 'v1',
                        entry_position: 0,
                        segments: [{ text: 'soup', match: true }],
                    },
                ],
            }),
        ]);

        await beginSelection();

        expect(
            document.querySelectorAll('a[href*="field=entry"]'),
        ).toHaveLength(0);
        expect(document.getElementById('session-select-1')).not.toBeNull();

        await mounted?.get('#session-select-card-1').trigger('click');

        expect(countText()).toBe('1 selected');
    });
});
