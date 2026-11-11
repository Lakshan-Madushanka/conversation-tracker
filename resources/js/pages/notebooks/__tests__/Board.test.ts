import { nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import Board from '@/pages/notebooks/Board.vue';
import type { NotebookData } from '@/types/notebook';

vi.mock('@/lib/notebook-autosave', () => ({
    saveNotebook: vi.fn(async () => ({
        status: 'saved',
        notebook: null,
    })),
}));

function notebook(overrides: Partial<NotebookData> = {}): NotebookData {
    return {
        id: 5,
        name: 'Kitchen',
        topic: { id: 2, name: 'Food' },
        description_html: '',
        version: 1,
        last_changed_at: null,
        feedback: {
            vocabulary: [
                {
                    id: 'row-a',
                    text: 'soup',
                    meaning: 'a liquid food',
                    example_html: '',
                },
            ],
            phrase: [
                {
                    id: 'row-b',
                    text: 'catch up',
                    meaning: 'talk about the news',
                    example_html: '',
                },
            ],
            grammar_note: [
                { id: 'note-1', html: '<p>Use the past tense here.</p>' },
            ],
        },
        ...overrides,
    };
}

let mounted: ReturnType<typeof mount> | undefined;

async function openBoard(): Promise<void> {
    mounted = mount(Board, {
        attachTo: document.body,
        props: {
            notebook: notebook(),
            topics: [{ id: 2, name: 'Food' }],
        },
        global: { stubs: { RichTextEditor: true } },
    });

    await flushPromises();
    await nextTick();
    await nextTick();
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    window.history.replaceState(null, '', '/');
});

function address(query: string): void {
    window.history.replaceState(null, '', `/notebooks/5?${query}`);
}

beforeEach(() => {
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => {
        callback();

        return 0;
    });
    vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
});

describe('opening a notebook at a collection item', () => {
    it('takes the learner to the vocabulary row the address names', async () => {
        address(
            'from=collections&field=entry&type=vocabulary&entry=row-a&position=0',
        );

        await openBoard();

        expect(document.activeElement?.id).toBe('nb-vocabulary-row-row-a');
    });

    it('finds a row by its place when the row no longer has an id', async () => {
        address('from=collections&field=entry&type=phrase&position=0');

        await openBoard();

        expect(document.activeElement?.id).toBe('nb-phrase-row-row-b');
    });

    it('takes the learner to the grammar note the address names', async () => {
        address(
            'from=collections&field=entry&type=grammar_note&entry=note-1&position=0',
        );

        await openBoard();

        expect(document.activeElement?.id).toBe('nb-grammar-note-1');
    });

    it('says calmly when the named entry has changed since it was saved', async () => {
        address(
            'from=collections&field=entry&type=vocabulary&entry=gone&position=9',
        );

        await openBoard();

        expect(mounted?.text()).toContain(
            'The entry you came from has changed since it was saved.',
        );
        expect(window.scrollTo).toHaveBeenCalledWith({ top: 0 });
    });

    it('leaves a board opened without a target alone', async () => {
        await openBoard();

        expect(mounted?.text()).not.toContain(
            'The entry you came from has changed',
        );
    });
});
