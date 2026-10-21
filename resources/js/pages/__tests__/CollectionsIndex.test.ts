import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vite-plus/test';
import CollectionsIndex from '@/pages/collections/Index.vue';
import { show as sessionsShow } from '@/routes/sessions';
import type { LaravelPaginator, PracticeFeedbackEntry } from '@/types/types';

function entry(
    overrides: Partial<PracticeFeedbackEntry> = {},
): PracticeFeedbackEntry {
    return {
        id: 1,
        practice_question_id: 11,
        type: 'vocabulary',
        position: 0,
        client_id: null,
        content_html: '',
        content_text: 'relish',
        correction_html: '',
        correction_text: 'to enjoy something',
        reason: '',
        example: '',
        example_html: '',
        is_split: true,
        created_at: '2026-10-19T10:14:55.000000Z',
        updated_at: '2026-10-19T12:24:34.000000Z',
        practice_question: {
            id: 11,
            practice_session: {
                id: 42,
                topic: { id: 7, name: 'Ordering at a restaurant' },
            },
        },
        ...overrides,
    };
}

function paginator(
    data: PracticeFeedbackEntry[],
): LaravelPaginator<PracticeFeedbackEntry> {
    return {
        current_page: 1,
        data,
        first_page_url: '/collections',
        from: data.length ? 1 : null,
        last_page: 1,
        last_page_url: '/collections',
        links: [],
        next_page_url: null,
        path: '/collections',
        per_page: 4,
        prev_page_url: null,
        to: data.length,
        total: data.length,
    };
}

function mountPage(
    options: {
        entries?: PracticeFeedbackEntry[];
        fetchNext?: ReturnType<typeof vi.fn>;
    } = {},
) {
    const { entries = [entry()], fetchNext = vi.fn() } = options;
    const vocabulary = paginator(entries);

    return mount(CollectionsIndex, {
        props: {
            vocabulary,
            phrases: paginator([]),
            grammar: paginator([]),
            mistakes: paginator([]),
        },
        global: {
            stubs: {
                InfiniteScroll: {
                    template: '<div><slot /></div>',
                    methods: { fetchNext },
                },
            },
        },
    });
}

function tabTriggers(wrapper: ReturnType<typeof mountPage>) {
    return wrapper.findAll('[role="tab"]');
}

function sessionLink(wrapper: ReturnType<typeof mountPage>) {
    return wrapper
        .findAll('a')
        .find((anchor) => anchor.text().includes('See original session'));
}

describe('a collection of saved entries', () => {
    it('links to the session an entry came from, not its topic', () => {
        const wrapper = mountPage();

        expect(sessionLink(wrapper)?.attributes('href')).toBe(
            sessionsShow.url(42),
        );
    });

    it('shows the topic an entry came from', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Ordering at a restaurant');
    });

    it('says nothing when an entry has no example', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).not.toContain('“');
    });

    it('shows the example an entry was saved with', () => {
        const wrapper = mountPage({
            entries: [entry({ example: 'I relish a quiet evening.' })],
        });

        expect(wrapper.text()).toContain('I relish a quiet evening.');
    });

    it('searches the tab the learner is looking at, even with the keyboard', async () => {
        const wrapper = mountPage();
        const [, phrases] = tabTriggers(wrapper);

        await phrases.trigger('focus');

        expect(wrapper.find('input').attributes('placeholder')).toBe(
            'Search Phrases',
        );
    });

    it('does not load another page when the open tab is clicked again', async () => {
        const fetchNext = vi.fn();
        const wrapper = mountPage({ fetchNext });
        const [vocabulary] = tabTriggers(wrapper);

        await vocabulary.trigger('click');

        expect(fetchNext).not.toHaveBeenCalled();
    });
});
