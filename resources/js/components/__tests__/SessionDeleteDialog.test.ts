import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import SessionDeleteDialog from '@/components/SessionDeleteDialog.vue';
import { archiveSession } from '@/lib/session-archive';
import {
    deleteSessionPermanently,
    fetchDeletePreview,
} from '@/lib/session-delete';
import type { ProgressNumbers, SessionDeletionPreview } from '@/types';

vi.mock('@/lib/session-delete', () => ({
    fetchDeletePreview: vi.fn(),
    deleteSessionPermanently: vi.fn(),
}));

vi.mock('@/lib/session-archive', () => ({
    archiveSession: vi.fn(),
}));

let mounted: VueWrapper | undefined;

function numbers(overrides: Partial<ProgressNumbers> = {}): ProgressNumbers {
    return {
        words: 10,
        phrases: 4,
        mistakes: 3,
        grammar_notes: 2,
        practice_days: 5,
        current_streak: 2,
        ...overrides,
    };
}

function preview(
    overrides: {
        finished?: boolean;
        archived?: boolean;
        counts?: Partial<SessionDeletionPreview['counts']>;
        before?: Partial<ProgressNumbers>;
        after?: Partial<ProgressNumbers>;
        token?: string;
    } = {},
): SessionDeletionPreview {
    return {
        session: {
            id: 7,
            topic: 'Ordering at a restaurant',
            session_date: '2026-10-12T09:00:00.000000Z',
            finished: overrides.finished ?? false,
            archived: overrides.archived ?? false,
        },
        counts: {
            questions: 1,
            words: 2,
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
            ...overrides.counts,
        },
        progress: {
            before: numbers(overrides.before),
            after: numbers({
                words: 8,
                practice_days: 4,
                current_streak: 1,
                ...overrides.after,
            }),
        },
        token: overrides.token ?? 'token-1',
    };
}

async function openDialog(data: SessionDeletionPreview): Promise<VueWrapper> {
    vi.mocked(fetchDeletePreview).mockResolvedValue({
        outcome: 'preview',
        preview: data,
    });

    mounted = mount(SessionDeleteDialog, {
        attachTo: document.body,
        props: {
            open: true,
            sessionId: 7,
            topic: 'Ordering at a restaurant',
            sessionDate: '2026-10-12T09:00:00.000000Z',
        },
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
    document.getElementById('delete-understand')?.click();
    await flushPromises();
}

beforeEach(() => {
    vi.mocked(archiveSession).mockResolvedValue('archived');
});

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    vi.clearAllMocks();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('deleting an unfinished session', () => {
    it('names the session, lists what goes, and needs no tick', async () => {
        await openDialog(preview({ finished: false }));

        expect(document.body.textContent).toContain('Ordering at a restaurant');
        expect(document.body.textContent).toContain('This cannot be undone.');
        expect(document.body.textContent).toContain('progress totals');
        expect(document.body.textContent).toContain('2');

        expect(document.getElementById('delete-understand')).toBeNull();
        expect(buttonWith('Archive instead')).toBeUndefined();

        const remove = buttonWith('Delete permanently');

        expect(remove?.disabled).toBe(false);
    });

    it('puts the safe choice first for the keyboard', async () => {
        await openDialog(preview({ finished: false }));
        await flushPromises();

        expect(document.activeElement?.id).toBe('session-delete-cancel');
    });
});

describe('deleting a finished session', () => {
    it('warns prominently and shows only the progress lines that change', async () => {
        await openDialog(preview({ finished: true }));

        expect(document.body.textContent).toContain('This cannot be undone.');
        expect(document.body.textContent).toContain(
            'leave the Library, Collections, and Review',
        );

        // Words and streak change; phrases do not, because both sides are 4.
        expect(document.body.textContent).toContain('Words learned');
        expect(document.body.textContent).toContain('Current streak');
        expect(document.body.textContent).not.toContain('Phrases saved');
    });

    it('keeps Delete disabled until the learner understands', async () => {
        await openDialog(preview({ finished: true }));

        const remove = buttonWith('Delete permanently');

        expect(remove?.disabled).toBe(true);

        await tickUnderstanding();

        expect(buttonWith('Delete permanently')?.disabled).toBe(false);
    });

    it('offers Archive instead unless the session is already archived', async () => {
        await openDialog(preview({ finished: true }));

        expect(buttonWith('Archive instead')).toBeDefined();
    });

    it('does not offer Archive for an archived session', async () => {
        await openDialog(preview({ finished: true, archived: true }));

        expect(buttonWith('Archive instead')).toBeUndefined();
    });

    it('archives instead without deleting anything', async () => {
        await openDialog(preview({ finished: true }));

        buttonWith('Archive instead')?.click();
        await flushPromises();

        expect(archiveSession).toHaveBeenCalledWith(7);
        expect(deleteSessionPermanently).not.toHaveBeenCalled();
        expect(mounted?.emitted('archived')).toEqual([[7]]);
    });
});

describe('a session that changed while the warning was open', () => {
    it('shows the fresh numbers, clears the tick, and deletes nothing', async () => {
        await openDialog(preview({ finished: true }));
        await tickUnderstanding();

        vi.mocked(deleteSessionPermanently).mockResolvedValue({
            outcome: 'stale',
            message:
                'This session changed since you opened the warning. Check the numbers and confirm again.',
            preview: preview({
                finished: true,
                counts: { words: 5 },
                after: { words: 5 },
                token: 'token-2',
            }),
        });

        buttonWith('Delete permanently')?.click();
        await flushPromises();

        expect(document.body.textContent).toContain('This session changed');
        expect(document.body.textContent).toContain(
            'The numbers below are fresh.',
        );
        expect(buttonWith('Delete permanently')?.disabled).toBe(true);
        expect(mounted?.emitted('deleted')).toBeUndefined();

        // Archive is still a safe way out after the numbers moved.
        expect(buttonWith('Archive instead')).toBeDefined();
    });
});

describe('a session that is already gone', () => {
    it('reports it missing instead of pretending to delete it', async () => {
        vi.mocked(fetchDeletePreview).mockResolvedValue({
            outcome: 'missing',
        });

        mounted = mount(SessionDeleteDialog, {
            attachTo: document.body,
            props: {
                open: true,
                sessionId: 7,
                topic: 'Ordering at a restaurant',
                sessionDate: null,
            },
        });

        await flushPromises();

        expect(mounted.emitted('missing')).toEqual([[7]]);
        expect(mounted.emitted('update:open')).toEqual([[false]]);
        expect(deleteSessionPermanently).not.toHaveBeenCalled();
    });
});
