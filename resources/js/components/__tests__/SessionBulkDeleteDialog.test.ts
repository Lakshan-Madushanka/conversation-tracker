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
import SessionBulkDeleteDialog from '@/components/SessionBulkDeleteDialog.vue';
import {
    archiveSessions,
    deleteSessionsPermanently,
    fetchBulkDeletePreview,
} from '@/lib/session-bulk';
import type { ProgressNumbers, SessionDeletionBatchPreview } from '@/types';

vi.mock('@/lib/session-bulk', () => ({
    archiveSessions: vi.fn(),
    deleteSessionsPermanently: vi.fn(),
    fetchBulkDeletePreview: vi.fn(),
    restoreSessions: vi.fn(),
}));

let mounted: VueWrapper | undefined;

function numbers(overrides: Partial<ProgressNumbers> = {}): ProgressNumbers {
    return {
        words: 10,
        phrases: 4,
        mistakes: 3,
        grammar_notes: 2,
        practice_days: 5,
        current_streak: 3,
        ...overrides,
    };
}

function preview(
    overrides: Partial<SessionDeletionBatchPreview> = {},
): SessionDeletionBatchPreview {
    return {
        requested_count: 3,
        session_count: 3,
        missing_count: 0,
        finished_count: 1,
        unfinished_count: 2,
        archivable_count: 1,
        all_finished_archived: false,
        names: [
            {
                topic: 'Ordering at a restaurant',
                session_date: '2026-10-12T09:00:00.000000Z',
            },
            {
                topic: 'Travel plans',
                session_date: '2026-10-11T09:00:00.000000Z',
            },
            {
                topic: 'Job interview',
                session_date: '2026-10-10T09:00:00.000000Z',
            },
        ],
        more_count: 0,
        counts: {
            questions: 3,
            words: 6,
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
        },
        progress: {
            before: numbers(),
            after: numbers({ words: 4, practice_days: 4, current_streak: 2 }),
        },
        token: 'token-1',
        ...overrides,
    };
}

async function openDialog(
    data: SessionDeletionBatchPreview,
): Promise<VueWrapper> {
    vi.mocked(fetchBulkDeletePreview).mockResolvedValue(data);

    mounted = mount(SessionBulkDeleteDialog, {
        attachTo: document.body,
        props: { open: true, ids: [1, 2, 3] },
    });

    await flushPromises();

    return mounted;
}

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.includes(text),
    );
}

async function tickUnderstanding(): Promise<void> {
    document.getElementById('bulk-delete-understand')?.click();
    await flushPromises();
}

beforeEach(() => {
    vi.mocked(archiveSessions).mockResolvedValue({
        archived: true,
        archived_ids: [1],
        archived_count: 1,
        skipped_unfinished: 2,
        skipped_archived: 0,
        skipped_missing: 0,
    });
});

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    vi.clearAllMocks();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('deleting only unfinished sessions', () => {
    it('gives a clear confirmation without Archive or the tick', async () => {
        await openDialog(
            preview({
                finished_count: 0,
                archivable_count: 0,
                all_finished_archived: false,
            }),
        );

        expect(document.body.textContent).toContain('Delete 3 sessions?');
        expect(document.body.textContent).toContain('progress totals');
        expect(document.body.textContent).toContain('will not change');
        expect(document.getElementById('bulk-delete-understand')).toBeNull();
        expect(buttonWith('Archive the')).toBeUndefined();
        expect(buttonWith('Delete permanently')?.disabled).toBe(false);
    });

    it('puts Cancel first for the keyboard', async () => {
        await openDialog(preview({ finished_count: 0, archivable_count: 0 }));
        await flushPromises();

        expect(document.activeElement?.id).toBe('bulk-delete-cancel');
    });
});

describe('deleting a selection with finished sessions', () => {
    it('names the first sessions and counts the rest', async () => {
        await openDialog(preview({ more_count: 2 }));

        expect(document.body.textContent).toContain('Ordering at a restaurant');
        expect(document.body.textContent).toContain('Travel plans');
        expect(document.body.textContent).toContain('Job interview');
        expect(document.body.textContent).toContain('and 2 more');
        expect(document.body.textContent).toContain('Delete 3 sessions?');
    });

    it('warns prominently and shows the combined progress effect', async () => {
        await openDialog(preview());

        expect(document.body.textContent).toContain('This cannot be undone.');
        expect(document.body.textContent).toContain(
            '1 finished session will leave the Library, Collections, and Review.',
        );

        // Words, practice days and the streak change; phrases do not.
        expect(document.body.textContent).toContain('Words learned');
        expect(document.body.textContent).toContain('Practice days');
        expect(document.body.textContent).toContain('Current streak');
        expect(document.body.textContent).not.toContain('Phrases saved');
    });

    it('keeps Delete disabled until the learner understands', async () => {
        await openDialog(preview());

        expect(buttonWith('Delete permanently')?.disabled).toBe(true);

        await tickUnderstanding();

        expect(buttonWith('Delete permanently')?.disabled).toBe(false);
    });

    it('offers Archive for the finished sessions only', async () => {
        await openDialog(preview({ finished_count: 2, archivable_count: 2 }));

        expect(
            buttonWith('Archive the 2 finished sessions instead'),
        ).toBeDefined();
    });

    it('archives instead of deleting when Archive is chosen', async () => {
        await openDialog(preview());

        expect(
            buttonWith('Archive this finished session instead'),
        ).toBeDefined();

        buttonWith('Archive this finished session instead')?.click();
        await flushPromises();

        expect(archiveSessions).toHaveBeenCalledWith([1, 2, 3]);
        expect(deleteSessionsPermanently).not.toHaveBeenCalled();
        expect(mounted?.emitted('archived')?.[0]).toEqual([
            expect.objectContaining({ archived_count: 1 }),
        ]);
        expect(mounted?.emitted('update:open')).toContainEqual([false]);
    });
});

describe('confirming a bulk delete', () => {
    it('deletes the selection once with the token it showed', async () => {
        let finish: (
            outcome: Awaited<ReturnType<typeof deleteSessionsPermanently>>,
        ) => void = () => {};

        vi.mocked(deleteSessionsPermanently).mockReturnValue(
            new Promise((resolve) => {
                finish = resolve;
            }),
        );

        await openDialog(preview());
        await tickUnderstanding();

        buttonWith('Delete permanently')?.click();
        await nextTick();
        buttonWith('Delete permanently')?.click();
        await nextTick();

        // A second press while the first is on its way cannot start another.
        expect(deleteSessionsPermanently).toHaveBeenCalledTimes(1);

        finish({
            outcome: 'deleted',
            result: {
                deleted: true,
                already_deleted: false,
                deleted_ids: [1, 2, 3],
                deleted_count: 3,
                skipped_missing: 0,
            },
        });

        await flushPromises();

        expect(deleteSessionsPermanently).toHaveBeenCalledWith(
            [1, 2, 3],
            'token-1',
            expect.any(String),
        );
        expect(mounted?.emitted('deleted')?.[0]).toEqual([
            expect.objectContaining({ deleted_count: 3 }),
        ]);
        expect(mounted?.emitted('update:open')).toContainEqual([false]);
    });
});

describe('a selection that changed while the warning was open', () => {
    it('shows fresh numbers, clears the tick, and deletes nothing', async () => {
        await openDialog(preview());
        await tickUnderstanding();

        vi.mocked(deleteSessionsPermanently).mockResolvedValue({
            outcome: 'stale',
            message:
                'These sessions changed since you opened the warning. Check the numbers and confirm again.',
            preview: preview({
                counts: {
                    questions: 1,
                    words: 9,
                    phrases: 0,
                    mistakes: 0,
                    grammar_notes: 0,
                },
                token: 'token-2',
            }),
        });

        buttonWith('Delete permanently')?.click();
        await flushPromises();

        expect(document.body.textContent).toContain('These sessions changed');
        expect(document.body.textContent).toContain(
            'The numbers below are fresh.',
        );
        expect(buttonWith('Delete permanently')?.disabled).toBe(true);
        expect(mounted?.emitted('deleted')).toBeUndefined();

        // Archive is still a safe way out after the numbers moved.
        expect(buttonWith('Archive this')).toBeDefined();
    });
});

describe('a selection that is already gone', () => {
    it('reports it missing instead of pretending to delete it', async () => {
        await openDialog(
            preview({
                session_count: 0,
                finished_count: 0,
                unfinished_count: 0,
                archivable_count: 0,
                names: [],
            }),
        );

        expect(mounted?.emitted('missing')).toEqual([[]]);
        expect(mounted?.emitted('update:open')).toContainEqual([false]);
        expect(deleteSessionsPermanently).not.toHaveBeenCalled();
    });
});

describe('a warning that cannot read the selection', () => {
    it('says nothing was deleted and offers another try', async () => {
        vi.mocked(fetchBulkDeletePreview).mockResolvedValue(null);

        mounted = mount(SessionBulkDeleteDialog, {
            attachTo: document.body,
            props: { open: true, ids: [1, 2, 3] },
        });

        await flushPromises();

        expect(document.body.textContent).toContain(
            'We could not check these sessions',
        );
        expect(document.body.textContent).toContain(
            'Nothing has been deleted.',
        );
        expect(buttonWith('Try again')).toBeDefined();
    });
});
