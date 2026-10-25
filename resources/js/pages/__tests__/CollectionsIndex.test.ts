import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CollectionsIndex from '@/pages/collections/Index.vue';
import { show as sessionsShow } from '@/routes/sessions';
import type { LaravelPaginator, PracticeFeedbackEntry } from '@/types/types';

const page = vi.hoisted(() => ({ url: '/collections' }));

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { name: 'Head', render: () => null },
    usePage: () => ({ url: page.url }),
}));

beforeEach(() => {
    page.url = '/collections';
});

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
        grammar?: PracticeFeedbackEntry[];
        mistakes?: PracticeFeedbackEntry[];
        fetchNext?: ReturnType<typeof vi.fn>;
    } = {},
) {
    const {
        entries = [entry()],
        grammar = [],
        mistakes = [],
        fetchNext = vi.fn(),
    } = options;
    const vocabulary = paginator(entries);

    return mount(CollectionsIndex, {
        props: {
            vocabulary,
            phrases: paginator([]),
            grammar: paginator(grammar),
            mistakes: paginator(mistakes),
        },
        global: {
            stubs: {
                Head: true,
                InfiniteScroll: {
                    name: 'InfiniteScroll',
                    props: ['data', 'params'],
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

async function switchToTab(
    wrapper: ReturnType<typeof mountPage>,
    label: string,
) {
    const trigger = tabTriggers(wrapper).find((tab) =>
        tab.text().includes(label),
    );

    await trigger?.trigger('focus');
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

    it('shows every example and keeps their formatting', () => {
        const wrapper = mountPage({
            entries: [
                entry({
                    example_html: [
                        {
                            id: 'e-1',
                            text: 'I relish a quiet evening.',
                            html: 'I relish a <strong>quiet</strong> evening.',
                        },
                        {
                            id: 'e-2',
                            text: 'She relishes the soup.',
                            html: 'She <em>relishes</em> the soup.',
                        },
                    ],
                }),
            ],
        });

        expect(wrapper.text()).toContain('I relish a quiet evening.');
        expect(wrapper.text()).toContain('She relishes the soup.');
        expect(wrapper.html()).toContain('<strong>quiet</strong>');
        expect(wrapper.html()).toContain('<em>relishes</em>');
    });

    it('shows the why behind a mistake', async () => {
        const wrapper = mountPage({
            mistakes: [
                entry({
                    type: 'mistake',
                    content_text: 'I want a soup',
                    correction_text: "I'd like the soup, please",
                    reason: 'Sounds more polite when ordering.',
                }),
            ],
        });

        await switchToTab(wrapper, 'My mistakes');

        expect(wrapper.text()).toContain('I want a soup');
        expect(wrapper.text()).toContain("I'd like the soup, please");
        expect(wrapper.text()).toContain('Sounds more polite when ordering.');
    });

    it('shows a mistake correction with its formatting', async () => {
        const wrapper = mountPage({
            mistakes: [
                entry({
                    type: 'mistake',
                    content_text: 'I want a soup',
                    correction_html:
                        "I'd like the <strong>soup</strong>, please",
                    correction_text: "I'd like the soup, please",
                }),
            ],
        });

        await switchToTab(wrapper, 'My mistakes');

        const correction = wrapper
            .findAll('p')
            .find((paragraph) => paragraph.text().includes("I'd like"));

        expect(correction?.html()).toContain('<strong>soup</strong>');
    });

    it('shows a grammar note with its formatting', async () => {
        const wrapper = mountPage({
            grammar: [
                entry({
                    type: 'grammar_note',
                    content_html: 'Use the <strong>past tense</strong> here.',
                    content_text: 'Use the past tense here.',
                    correction_text: '',
                }),
            ],
        });

        await switchToTab(wrapper, 'Grammar');

        const note = wrapper
            .findAll('p')
            .find((paragraph) => paragraph.text().includes('past tense'));

        expect(note?.html()).toContain('<strong>past tense</strong>');
    });

    it('gives a grammar note no correction or example line', async () => {
        const wrapper = mountPage({
            entries: [],
            grammar: [
                entry({
                    type: 'grammar_note',
                    content_html: '<p>Use the past tense here.</p>',
                    content_text: 'Use the past tense here.',
                    correction_text: '',
                    example: '',
                }),
            ],
        });

        await switchToTab(wrapper, 'Grammar');

        const card = wrapper.find('.rounded-xl.border.bg-card');

        expect(card.text()).not.toContain('“');
        expect(card.findAll('p.text-sm.text-muted-foreground')).toHaveLength(0);
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

    it('sends each tab its own search term when the next page is loaded', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountPage();

            await wrapper.find('input').setValue('soup');

            const [vocabulary] = wrapper.findAllComponents({
                name: 'InfiniteScroll',
            });

            expect(vocabulary.props('params')).toEqual({
                data: { search: 'soup', forPage: 'vocabulary' },
            });

            await tabTriggers(wrapper)[1].trigger('focus');

            const [phrases] = wrapper.findAllComponents({
                name: 'InfiniteScroll',
            });

            expect(phrases.props('params')).toEqual({
                data: { search: '', forPage: 'phrases' },
            });
        } finally {
            vi.useRealTimers();
        }
    });

    it('opens on the tab and term the address bar asks for', () => {
        page.url = '/collections?search=soup&forPage=phrases';

        const wrapper = mountPage();

        expect(wrapper.find('input').element.value).toBe('soup');
        expect(tabTriggers(wrapper)[1].attributes('data-state')).toBe('active');
    });

    it('does not search again when the page opens from a shared link', () => {
        vi.useFakeTimers();
        const visit = vi.spyOn(router, 'visit');

        try {
            page.url = '/collections?search=soup&forPage=phrases';

            mountPage();
            vi.advanceTimersByTime(400);

            expect(visit).not.toHaveBeenCalled();
        } finally {
            visit.mockRestore();
            vi.useRealTimers();
        }
    });
});
