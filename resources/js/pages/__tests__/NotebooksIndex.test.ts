import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { jsonRequest } from '@/lib/http';
import Index from '@/pages/notebooks/Index.vue';
import type { NotebookCardData } from '@/types/notebook';
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
        search: '',
        ...overrides,
    };
}

function mountPage(overrides: Record<string, unknown> = {}) {
    return mount(Index, {
        attachTo: document.body,
        props: pageProps(overrides),
    });
}

afterEach(() => {
    document.body.innerHTML = '';
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
});
