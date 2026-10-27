import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CollectionList from '@/components/CollectionList.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { CollectionItem } from '@/types';

function item(overrides: Partial<CollectionItem> = {}): CollectionItem {
    return {
        key: 'soup',
        word: 'soup',
        meaning: 'a liquid food',
        example: 'I want a soup.',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 1,
        needs_attention: false,
        appearances: [],
        ...overrides,
    };
}

function startLink(wrapper: ReturnType<typeof mount>) {
    return wrapper
        .findAll('a')
        .find((anchor) => anchor.text().includes('Start a session'));
}

describe('the collection list', () => {
    it('shows a word with its meaning and example', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [item()], total: 1 },
        });

        expect(wrapper.text()).toContain('soup');
        expect(wrapper.text()).toContain('a liquid food');
        expect(wrapper.text()).toContain('“I want a soup.”');
    });

    it('says how often a word was seen only when it was seen more than once', () => {
        const once = mount(CollectionList, {
            props: { items: [item()], total: 1 },
        });

        expect(once.text()).not.toContain('Seen 1 times');

        const many = mount(CollectionList, {
            props: { items: [item({ seen_count: 3 })], total: 1 },
        });

        expect(many.text()).toContain('Seen 3 times');
    });

    it('asks for a meaning when none was saved', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [
                    item({
                        meaning: '',
                        example: '',
                        needs_attention: true,
                    }),
                ],
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('Needs attention');
        expect(wrapper.text()).toContain('Add the meaning');
    });

    it('opens the word that was chosen', async () => {
        const chosen = item();

        const wrapper = mount(CollectionList, {
            props: { items: [chosen], total: 1 },
        });

        const word = wrapper
            .findAll('button')
            .find((button) => button.text() === 'soup');

        await word?.trigger('click');

        expect(wrapper.emitted('open')?.[0]).toEqual([chosen]);
    });

    it('offers another page and says how much is shown', async () => {
        const wrapper = mount(CollectionList, {
            props: { items: [item()], hasMore: true, total: 30 },
        });

        expect(wrapper.text()).toContain('Showing 1 of 30');

        const button = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Load more words'));

        await button?.trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('says when there is nothing left to load', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [item()], total: 1 },
        });

        expect(wrapper.text()).toContain('That is every word — 1 in total.');
    });

    it('shows placeholders while the next page arrives', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], loading: true },
        });

        expect(wrapper.text()).not.toContain('No words yet');
        expect(
            wrapper.findAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });

    it('shows placeholders while another tab loads', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], updating: true },
        });

        expect(wrapper.text()).not.toContain('No words yet');
        expect(
            wrapper.findAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });

    it('explains a failed load and offers to try again', async () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [item()],
                error: true,
                hasMore: true,
                total: 30,
            },
        });

        expect(wrapper.text()).toContain('Could not load the collection');

        const retry = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Try again'));

        await retry?.trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('offers a fresh start when no session has been finished', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], total: 0, hasFinishedSessions: false },
        });

        expect(wrapper.text()).toContain('Your collections are waiting');
        expect(startLink(wrapper)?.attributes('href')).toBe(
            sessionsCreate.url(),
        );
    });

    it('says no words yet when there are finished sessions without words', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], total: 0, hasFinishedSessions: true },
        });

        expect(wrapper.text()).toContain('No words yet');
        expect(startLink(wrapper)?.attributes('href')).toBe(
            sessionsCreate.url(),
        );
    });
});
