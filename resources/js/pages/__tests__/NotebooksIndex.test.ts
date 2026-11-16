import { nextTick } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import NotebookDeleteDialog from '@/components/NotebookDeleteDialog.vue';
import { jsonRequest } from '@/lib/http';
import Index from '@/pages/notebooks/Index.vue';
import type { NotebookCardData, NotebookCardMatch } from '@/types/notebook';
import type { TopicSummary } from '@/types/practice';

vi.mock('@/lib/http', () => ({
    jsonRequest: vi.fn(),
}));

const request = vi.mocked(jsonRequest);

function respond(body: unknown): Response {
    return {
        ok: true,
        status: 200,
        json: async () => body,
    } as unknown as Response;
}

function card(overrides: Partial<NotebookCardData> = {}): NotebookCardData {
    return {
        id: 1,
        name: 'Kitchen words',
        topic: 'Home',
        description: 'Things in the kitchen',
        last_updated_at: '2026-11-01T09:00:00.000000Z',
        counts: { words: 3, phrases: 1, grammar_notes: 0 },
        match: null,
        ...overrides,
    };
}

function matchedEntry(
    overrides: Partial<NotebookCardMatch> = {},
): NotebookCardMatch {
    return {
        label: 'Meaning',
        segments: [
            { text: 'a ', match: false },
            { text: 'financial', match: true },
            { text: ' ', match: false },
            { text: 'institution', match: true },
        ],
        type: 'vocabulary',
        entry: 'row-1',
        position: 2,
        ...overrides,
    };
}

function pageProps(overrides: Record<string, unknown> = {}) {
    return {
        notebooks: [card()],
        next_cursor: null,
        has_more: false,
        total: 1,
        topics: [] as TopicSummary[],
        filter_topics: [] as TopicSummary[],
        topic: null,
        search: '',
        ...overrides,
    };
}

const topics = [
    { id: 3, name: 'Reading' },
    { id: 5, name: 'Travel' },
];

function mountPage(overrides: Record<string, unknown> = {}) {
    return mount(Index, {
        attachTo: document.body,
        props: pageProps(overrides),
    });
}

/** Opens the delete warning for the named card, then confirms it is gone. */
async function deleteCard(
    wrapper: VueWrapper,
    deleted: { id: number; name: string },
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(`Delete ${deleted.name}`));

    await trigger?.trigger('click');
    await flushPromises();

    wrapper.findComponent(NotebookDeleteDialog).vm.$emit('deleted', deleted);

    await flushPromises();
    await nextTick();
}

afterEach(() => {
    document.body.innerHTML = '';
    vi.clearAllMocks();
    vi.restoreAllMocks();
});

describe('the Notebooks page', () => {
    it('shows the notebooks the last search sent', async () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Kitchen words');

        await wrapper.setProps(
            pageProps({
                notebooks: [card({ id: 7, name: 'Garden words' })],
                total: 1,
                search: 'garden',
            }),
        );

        expect(wrapper.text()).toContain('Garden words');
        expect(wrapper.text()).not.toContain('Kitchen words');
    });

    it('says when a search matches nothing and forgets the old count', async () => {
        const wrapper = mountPage();

        await wrapper.setProps(
            pageProps({ notebooks: [], total: 0, search: 'zzzz' }),
        );

        expect(wrapper.text()).toContain('No notebooks match your search.');
        expect(wrapper.text()).not.toContain('1 notebook');
    });

    it('keeps the search box in step with the address', async () => {
        const wrapper = mountPage();

        await wrapper.setProps(pageProps({ search: 'garden' }));

        expect(wrapper.find<HTMLInputElement>('input').element.value).toBe(
            'garden',
        );
    });

    it('takes the new count and next page from the last response', async () => {
        const wrapper = mountPage();

        await wrapper.setProps(
            pageProps({
                notebooks: [card(), card({ id: 2, name: 'Garden words' })],
                total: 9,
                next_cursor: 'abc',
                has_more: true,
            }),
        );

        expect(wrapper.text()).toContain('9 notebooks');

        const loadMore = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Load more'));

        expect(loadMore).toBeDefined();
    });

    it('drops a next page that arrives after a new search', async () => {
        let answer: (response: Response) => void = () => {};
        request.mockImplementation(
            () =>
                new Promise<Response>((resolve) => {
                    answer = resolve;
                }),
        );

        const wrapper = mountPage({ next_cursor: 'next', has_more: true });

        const loadMore = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Load more'));

        await loadMore?.trigger('click');

        await wrapper.setProps(
            pageProps({
                notebooks: [card({ id: 7, name: 'Garden words' })],
                search: 'garden',
            }),
        );

        answer(
            respond({
                notebooks: [card({ id: 99, name: 'Stale kitchen page' })],
                next_cursor: null,
                has_more: false,
            }),
        );

        await flushPromises();

        expect(wrapper.text()).toContain('Garden words');
        expect(wrapper.text()).not.toContain('Stale kitchen page');
    });

    it('offers the topics a notebook uses and follows the chosen one', async () => {
        const wrapper = mountPage({ filter_topics: topics, topic: 3 });

        const select = wrapper.get('select');

        expect(select.findAll('option').map((option) => option.text())).toEqual(
            ['All topics', 'Reading', 'Travel'],
        );
        expect((select.element as HTMLSelectElement).value).toBe('3');

        await wrapper.setProps(
            pageProps({ filter_topics: topics, topic: null }),
        );

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '',
        );
    });

    it('narrows by topic while keeping the current search', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mountPage({ filter_topics: topics, search: 'garden' });

        await wrapper.get('select').setValue('5');

        expect(get).toHaveBeenCalledWith(
            expect.any(String),
            { q: 'garden', topic: '5' },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('opens the whole list again from an empty topic', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mountPage({
            filter_topics: topics,
            topic: 3,
            notebooks: [],
            total: 0,
        });

        expect(wrapper.text()).toContain('No notebooks in this topic.');

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Show all topics'))
            ?.trigger('click');

        expect(get).toHaveBeenCalledWith(
            expect.any(String),
            {},
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('loads the next page within the chosen topic', async () => {
        request.mockResolvedValue(
            respond({ notebooks: [], next_cursor: null, has_more: false }),
        );

        const wrapper = mountPage({
            filter_topics: topics,
            topic: 3,
            search: 'bank',
            next_cursor: 'next',
            has_more: true,
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Load more'))
            ?.trigger('click');
        await flushPromises();

        const requested = new URL(
            String(request.mock.calls[0][0]),
            'http://localhost',
        );

        expect(requested.searchParams.get('cursor')).toBe('next');
        expect(requested.searchParams.get('q')).toBe('bank');
        expect(requested.searchParams.get('topic')).toBe('3');
    });

    it('shows where a search matched and opens that row on the board', async () => {
        const wrapper = mountPage({
            notebooks: [card({ match: matchedEntry() })],
            search: 'financial',
        });

        expect(wrapper.text()).toContain('Meaning:');
        expect(wrapper.text()).toContain('a financial institution');

        const href = wrapper.get('ul a').attributes('href') ?? '';
        const query = new URL(href, 'http://localhost').searchParams;

        expect(query.get('field')).toBe('entry');
        expect(query.get('type')).toBe('vocabulary');
        expect(query.get('entry')).toBe('row-1');
        expect(query.get('position')).toBe('2');
    });

    it('opens a card without a preview at the top of its notebook', async () => {
        const wrapper = mountPage();

        const href = wrapper.get('ul a').attributes('href') ?? '';
        const url = new URL(href, 'http://localhost');

        expect(url.searchParams.toString()).toBe('');
    });

    it('moves focus to the next card after a deleted one', async () => {
        const wrapper = mountPage({
            notebooks: [
                card({ id: 1, name: 'Kitchen words' }),
                card({ id: 2, name: 'Garden words' }),
                card({ id: 3, name: 'Travel words' }),
            ],
            total: 3,
        });

        await deleteCard(wrapper, { id: 2, name: 'Garden words' });

        expect(wrapper.text()).not.toContain('Garden words');
        expect(document.activeElement?.id).toBe('notebook-link-3');
    });

    it('falls back to the card above when the last one is deleted', async () => {
        const wrapper = mountPage({
            notebooks: [
                card({ id: 1, name: 'Kitchen words' }),
                card({ id: 2, name: 'Garden words' }),
            ],
            total: 2,
        });

        await deleteCard(wrapper, { id: 2, name: 'Garden words' });

        expect(document.activeElement?.id).toBe('notebook-link-1');
    });

    it('moves focus to the search box when the last card goes', async () => {
        const wrapper = mountPage({
            notebooks: [card({ id: 1, name: 'Kitchen words' })],
            total: 1,
        });

        await deleteCard(wrapper, { id: 1, name: 'Kitchen words' });

        expect(wrapper.text()).toContain('No notebooks yet.');
        expect(document.activeElement?.id).toBe('library-search');
    });
});
