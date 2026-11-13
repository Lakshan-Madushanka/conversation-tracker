import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import NotebookDeleteDialog from '@/components/NotebookDeleteDialog.vue';
import {
    deleteNotebookPermanently,
    fetchNotebookDeletePreview,
} from '@/lib/notebook-delete';
import type {
    NotebookDeletionCounts,
    NotebookDeletionPreview,
    NotebookDeletionReview,
} from '@/types/notebook';

vi.mock('@/lib/notebook-delete', () => ({
    fetchNotebookDeletePreview: vi.fn(),
    deleteNotebookPermanently: vi.fn(),
}));

let mounted: VueWrapper | undefined;

function preview(
    overrides: {
        counts?: Partial<NotebookDeletionCounts>;
        items?: Partial<NotebookDeletionCounts>;
        reviews?: NotebookDeletionReview[];
        token?: string;
    } = {},
): NotebookDeletionPreview {
    return {
        notebook: { id: 7, name: 'Kitchen notes', topic: 'Cooking' },
        counts: {
            words: 3,
            phrases: 1,
            grammar_notes: 0,
            ...overrides.counts,
        },
        items: {
            words: 2,
            phrases: 1,
            grammar_notes: 0,
            ...overrides.items,
        },
        reviews: overrides.reviews ?? [
            {
                id: 4,
                name: 'Morning set',
                removed: 2,
                remaining: 1,
                becomes_empty: false,
            },
        ],
        token: overrides.token ?? 'token-1',
    };
}

async function openDialog(data: NotebookDeletionPreview): Promise<VueWrapper> {
    vi.mocked(fetchNotebookDeletePreview).mockResolvedValue({
        outcome: 'preview',
        preview: data,
    });

    mounted = mount(NotebookDeleteDialog, {
        attachTo: document.body,
        props: {
            open: true,
            notebookId: 7,
            notebookName: 'Kitchen notes',
            notebookTopic: 'Cooking',
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
    document.getElementById('notebook-delete-understand')?.click();
    await flushPromises();
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    vi.clearAllMocks();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('the notebook delete warning', () => {
    it('names the notebook and says what survives', async () => {
        await openDialog(preview());

        expect(document.body.textContent).toContain('Delete this notebook?');
        expect(document.body.textContent).toContain('Kitchen notes');
        expect(document.body.textContent).toContain('in Cooking');
        expect(document.body.textContent).toContain('This cannot be undone.');
        expect(document.body.textContent).toContain(
            'Other notebooks and sessions are not affected',
        );
        expect(document.body.textContent).toContain(
            'even when they contain the same words',
        );
    });

    it('shows saved rows beside distinct study items only when they differ', async () => {
        await openDialog(preview());

        expect(document.body.textContent).toContain('3 saved rows');
        expect(document.body.textContent).toContain('2 study items');

        // One phrase is one row and one item, so it is shown once, plainly.
        expect(document.body.textContent).not.toContain('1 saved row');
    });

    it('names each affected review and how much it loses', async () => {
        await openDialog(
            preview({
                reviews: [
                    {
                        id: 4,
                        name: 'Morning set',
                        removed: 2,
                        remaining: 1,
                        becomes_empty: false,
                    },
                    {
                        id: 5,
                        name: 'Tiny set',
                        removed: 1,
                        remaining: 0,
                        becomes_empty: true,
                    },
                ],
            }),
        );

        expect(document.body.textContent).toContain('Morning set');
        expect(document.body.textContent).toContain('2 of 3 items removed');
        expect(document.body.textContent).toContain('Tiny set');
        expect(document.body.textContent).toContain(
            'This review will become Empty.',
        );
        expect(document.body.textContent).toContain(
            'Their attempt history stays.',
        );
    });

    it('says nothing about reviews when none are affected', async () => {
        await openDialog(preview({ reviews: [] }));

        expect(document.body.textContent).not.toContain(
            'Saved reviews that lose items',
        );
    });

    it('puts the safe choice first for the keyboard', async () => {
        await openDialog(preview());
        await flushPromises();

        expect(document.activeElement?.id).toBe('notebook-delete-cancel');
    });

    it('keeps Delete disabled until the learner understands', async () => {
        await openDialog(preview());

        expect(buttonWith('Delete permanently')?.disabled).toBe(true);

        await tickUnderstanding();

        expect(buttonWith('Delete permanently')?.disabled).toBe(false);
    });

    it('deletes with the token the preview came with', async () => {
        await openDialog(preview({ token: 'token-9' }));
        await tickUnderstanding();

        vi.mocked(deleteNotebookPermanently).mockResolvedValue({
            outcome: 'deleted',
            result: {
                deleted: true,
                already_deleted: false,
                notebook: { id: 7, name: 'Kitchen notes' },
                counts: { words: 3, phrases: 1, grammar_notes: 0 },
            },
        });

        buttonWith('Delete permanently')?.click();
        await flushPromises();

        expect(deleteNotebookPermanently).toHaveBeenCalledWith(7, 'token-9');
        expect(mounted?.emitted('deleted')).toEqual([
            [{ id: 7, name: 'Kitchen notes' }],
        ]);
    });
});

describe('a notebook that changed while the warning was open', () => {
    it('shows the fresh numbers, clears the tick, and deletes nothing', async () => {
        await openDialog(preview());
        await tickUnderstanding();

        vi.mocked(deleteNotebookPermanently).mockResolvedValue({
            outcome: 'stale',
            message:
                'This notebook changed since you opened the warning. Check the numbers and confirm again.',
            preview: preview({
                counts: { words: 2 },
                items: { words: 2 },
                token: 'token-2',
            }),
        });

        buttonWith('Delete permanently')?.click();
        await flushPromises();

        expect(document.body.textContent).toContain('This notebook changed');
        expect(document.body.textContent).toContain(
            'The numbers below are fresh.',
        );
        expect(buttonWith('Delete permanently')?.disabled).toBe(true);
        expect(mounted?.emitted('deleted')).toBeUndefined();
    });
});

describe('a notebook that is already gone', () => {
    it('reports it missing instead of pretending to delete it', async () => {
        vi.mocked(fetchNotebookDeletePreview).mockResolvedValue({
            outcome: 'missing',
        });

        mounted = mount(NotebookDeleteDialog, {
            attachTo: document.body,
            props: {
                open: true,
                notebookId: 7,
                notebookName: 'Kitchen notes',
                notebookTopic: 'Cooking',
            },
        });

        await flushPromises();

        expect(mounted.emitted('missing')).toEqual([[7]]);
        expect(mounted.emitted('update:open')).toEqual([[false]]);
        expect(deleteNotebookPermanently).not.toHaveBeenCalled();
    });
});
