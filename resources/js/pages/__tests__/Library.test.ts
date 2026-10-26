import { mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import {
    defaultLibraryFilters,
    type LibraryFilters,
} from '@/lib/library-filters';
import Index from '@/pages/sessions/Index.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { SessionCardData, TopicSummary } from '@/types';

function session(id: number): SessionCardData {
    return {
        id,
        topic: `Topic ${id}`,
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
        status: 'finished',
        counts: {
            questions: 1,
            words: 1,
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
        },
    };
}

function mountLibrary(
    sessions: SessionCardData[] = [session(1)],
    total = sessions.length,
    options: {
        allTotal?: number;
        filters?: LibraryFilters;
        topics?: TopicSummary[];
    } = {},
) {
    return mount(Index, {
        props: {
            sessions,
            next_cursor: null,
            has_more: false,
            total,
            all_total: options.allTotal ?? total,
            filters: options.filters ?? defaultLibraryFilters(),
            topics: options.topics ?? [],
            timezone: 'UTC',
        },
    });
}

beforeEach(() => {
    window.sessionStorage.clear();
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('the Library page', () => {
    it('says how many sessions there are and lists them', () => {
        const wrapper = mountLibrary([session(1), session(2)]);

        expect(wrapper.text()).toContain('2 sessions, newest first.');
        expect(wrapper.text()).toContain('Topic 1');
        expect(wrapper.text()).toContain('Topic 2');
        expect(wrapper.text()).toContain('That is every session — 2 in total.');
    });

    it('uses the singular when there is one session', () => {
        const wrapper = mountLibrary();

        expect(wrapper.text()).toContain('1 session, newest first.');
    });

    it('offers a fresh start when the Library is empty', () => {
        const wrapper = mountLibrary([], 0);

        expect(wrapper.text()).toContain('No sessions yet');

        const start = wrapper
            .findAll('a')
            .find((element) =>
                element.text().includes('Start your first session'),
            );

        expect(start?.attributes('href')).toBe(sessionsCreate.url());
    });
});

describe('the Library page with filters', () => {
    it('counts matching sessions out of every session', () => {
        const wrapper = mountLibrary([session(1), session(2)], 12, {
            allTotal: 42,
            filters: { ...defaultLibraryFilters(), topics: [1] },
            topics: [{ id: 1, name: 'Work' }],
        });

        expect(wrapper.text()).toContain('12 of 42 sessions, newest first.');
        expect(wrapper.text()).toContain('Topic: Work');
    });

    it('clears the filters from the no-results state and keeps focus useful', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});

        const wrapper = mountLibrary([], 0, {
            allTotal: 42,
            filters: { ...defaultLibraryFilters(), status: 'finished' },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Clear all filters'))
            ?.trigger('click');

        await wrapper.vm.$nextTick();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0]?.[0] as string).not.toContain('status=');

        wrapper.unmount();
    });

    it('keeps the chosen order in the description', () => {
        const wrapper = mountLibrary([session(1)], 1, {
            filters: { ...defaultLibraryFilters(), sort: 'oldest' },
        });

        expect(wrapper.text()).toContain('1 session, oldest first.');
    });
});

describe('the Library page with a search', () => {
    it('searches after a pause and keeps the filters in force', async () => {
        vi.useFakeTimers();

        const get = vi.spyOn(router, 'get').mockImplementation(() => {});

        const wrapper = mountLibrary([], 0, {
            allTotal: 12,
            filters: { ...defaultLibraryFilters(), status: 'finished' },
        });

        await wrapper.get('#library-search').setValue('beach');
        vi.advanceTimersByTime(350);

        expect(get).toHaveBeenCalledTimes(1);

        const url = get.mock.calls[0]?.[0] as string;

        expect(url).toContain('q=beach');
        expect(url).toContain('status=finished');

        wrapper.unmount();
    });

    it('clears only the search from the no-results state', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});

        const wrapper = mountLibrary([], 0, {
            allTotal: 12,
            filters: { ...defaultLibraryFilters(), q: 'zzzz' },
        });

        expect(wrapper.text()).toContain('No sessions match your search');

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Clear search'))
            ?.trigger('click');

        await wrapper.vm.$nextTick();

        const url = get.mock.calls[0]?.[0] as string;

        expect(url).not.toContain('q=');
        expect(wrapper.text()).not.toContain('No sessions match your search');

        wrapper.unmount();
    });
});
