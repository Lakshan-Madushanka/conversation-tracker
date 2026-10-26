import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import LibrarySessionList from '@/components/LibrarySessionList.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

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
});
