import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import LibrarySessionList from '@/components/LibrarySessionList.vue';
import SessionCard from '@/components/SessionCard.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

function session(id: number): SessionCardData {
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
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
        },
    };
}

describe('the Library list', () => {
    it('shows a card for every session loaded so far', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1), session(2)],
                total: 2,
            },
        });

        expect(wrapper.text()).toContain('Topic 1');
        expect(wrapper.text()).toContain('Topic 2');
        expect(wrapper.text()).toContain('That is every session — 2 in total.');
    });

    it('offers the next page and says how much is shown', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1)],
                hasMore: true,
                total: 30,
            },
        });

        expect(wrapper.text()).toContain('Showing 1 of 30');

        const button = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Load more sessions'));

        await button?.trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('shows placeholders while the next page arrives', () => {
        const wrapper = mount(LibrarySessionList, {
            props: { sessions: [], loading: true },
        });

        expect(wrapper.text()).not.toContain('No sessions yet');
        expect(
            wrapper.findAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });

    it('explains a failed load and offers to try again', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1)],
                error: true,
                hasMore: true,
                total: 30,
            },
        });

        expect(wrapper.text()).toContain('Could not load more sessions');

        const retry = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Try again'));

        await retry?.trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('starts the first session from the empty state', () => {
        const wrapper = mount(LibrarySessionList, {
            props: { sessions: [], total: 0 },
        });

        expect(wrapper.text()).toContain('No sessions yet');

        const start = wrapper
            .findAll('a')
            .find((element) =>
                element.text().includes('Start your first session'),
            );

        expect(start?.attributes('href')).toBe(sessionsCreate.url());
    });

    it('explains filters that match nothing and offers Clear all', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [],
                total: 0,
                allTotal: 12,
                hasFilters: true,
            },
        });

        expect(wrapper.text()).toContain('No sessions match these filters');
        expect(wrapper.text()).toContain('Your other sessions are still here.');

        const clear = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Clear all filters'));

        await clear?.trigger('click');

        expect(wrapper.emitted('clear-filters')).toHaveLength(1);
    });

    it('shows placeholders instead of a stale list while filters change', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1)],
                updating: true,
                total: 1,
            },
        });

        expect(wrapper.text()).not.toContain('Topic 1');
        expect(wrapper.find('[aria-label="Updating the list"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.findAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });

    it('explains a failed filter change and offers to try again', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1)],
                filterError: true,
                hasFilters: true,
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('Could not update the list');

        const retry = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Try again'));

        await retry?.trigger('click');

        expect(wrapper.emitted('retry-filters')).toHaveLength(1);
    });

    it('says how many sessions match when filters are on', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1)],
                total: 1,
                allTotal: 12,
                hasFilters: true,
            },
        });

        expect(wrapper.text()).toContain(
            'That is every matching session — 1 in total.',
        );
    });

    it('announces the result count once a search settles', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1), session(2)],
                total: 2,
                allTotal: 12,
                hasFilters: true,
                hasSearch: true,
            },
        });

        const status = wrapper.get('[role="status"]');

        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.text()).toBe('2 sessions match.');
    });

    it('stays quiet while a search is on its way', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [],
                total: 0,
                allTotal: 12,
                hasFilters: true,
                hasSearch: true,
                updating: true,
            },
        });

        expect(wrapper.get('[role="status"]').text()).toBe('');
    });

    it('announces that nothing matched', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [],
                total: 0,
                allTotal: 12,
                hasFilters: true,
                hasSearch: true,
            },
        });

        expect(wrapper.get('[role="status"]').text()).toBe(
            'No sessions match.',
        );
    });

    it('explains a search that matches nothing and offers to clear it', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [],
                total: 0,
                allTotal: 12,
                hasFilters: true,
                hasSearch: true,
            },
        });

        expect(wrapper.text()).toContain('No sessions match your search');
        expect(wrapper.text()).toContain('Try different words');

        const clear = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Clear search'));

        await clear?.trigger('click');

        expect(wrapper.emitted('clear-search')).toHaveLength(1);
        expect(wrapper.emitted('clear-filters')).toBeUndefined();
    });

    it('offers both ways out when a search and filters are both on', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [],
                total: 0,
                allTotal: 12,
                hasFilters: true,
                hasSearch: true,
                hasFilterChips: true,
            },
        });

        const clearSearch = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Clear search'));
        const clearFilters = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Clear all filters'));

        await clearFilters?.trigger('click');

        expect(clearSearch).toBeDefined();
        expect(wrapper.emitted('clear-filters')).toHaveLength(1);
    });
});

describe('the archived list', () => {
    it('explains an empty Archived view without offering a new session', () => {
        const wrapper = mount(LibrarySessionList, {
            props: { sessions: [], total: 0, archivedView: true },
        });

        expect(wrapper.text()).toContain('No archived sessions');
        expect(wrapper.text()).toContain(
            'Finished sessions you archive will appear here.',
        );
        expect(
            wrapper
                .findAll('a')
                .some((link) =>
                    link.text().includes('Start your first session'),
                ),
        ).toBe(false);
    });

    it('counts archived sessions in its own words', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [session(1), session(2)],
                total: 2,
                allTotal: 2,
                archivedView: true,
            },
        });

        expect(wrapper.text()).toContain(
            'That is every archived session — 2 in total.',
        );
    });

    it('narrows an archived filter to other archived sessions', () => {
        const wrapper = mount(LibrarySessionList, {
            props: {
                sessions: [],
                total: 0,
                allTotal: 2,
                hasFilters: true,
                archivedView: true,
            },
        });

        expect(wrapper.text()).toContain('No sessions match these filters');
        expect(wrapper.text()).toContain(
            'Your other archived sessions are still here.',
        );
        expect(wrapper.text()).not.toContain('or status');
    });

    it('forwards an archive, restore or delete from a card', async () => {
        const wrapper = mount(LibrarySessionList, {
            props: { sessions: [session(1)], total: 1 },
        });

        wrapper.findComponent(SessionCard).vm.$emit('archive', session(1));
        wrapper.findComponent(SessionCard).vm.$emit('restore', session(1));
        wrapper.findComponent(SessionCard).vm.$emit('delete', session(1));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('archive')?.[0]).toEqual([session(1)]);
        expect(wrapper.emitted('restore')?.[0]).toEqual([session(1)]);
        expect(wrapper.emitted('delete')?.[0]).toEqual([session(1)]);
    });
});
