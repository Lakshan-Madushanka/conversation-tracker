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
import BackToCollections from '@/components/BackToCollections.vue';
import { collectionUrl } from '@/lib/collection-query';
import {
    consumeCollectionRestore,
    saveCollectionSnapshot,
} from '@/lib/collections';
import type { CollectionItem } from '@/types';

function item(overrides: Partial<CollectionItem> = {}): CollectionItem {
    return {
        key: 'soup',
        title: 'soup',
        meaning: 'a liquid food',
        example: 'I want a soup.',
        example_html: '',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 1,
        needs_attention: false,
        attention_reason: null,
        appearances: [],
        ...overrides,
    };
}

function link(wrapper: ReturnType<typeof mount>) {
    return wrapper.get('a');
}

beforeEach(() => {
    vi.spyOn(router, 'visit').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
    window.sessionStorage.clear();
});

describe('the way back to Collections', () => {
    it('starts on vocabulary when nothing was remembered', () => {
        const wrapper = mount(BackToCollections);

        expect(link(wrapper).text()).toBe('Back to Collections');
        expect(link(wrapper).attributes('href')).toBe(
            collectionUrl('vocabulary'),
        );
    });

    it('returns to the tab and item the learner left behind', () => {
        saveCollectionSnapshot({
            tab: 'phrases',
            items: [item({ key: 'catch up' })],
            nextCursor: null,
            hasMore: false,
            total: 1,
            openItemKey: 'catch up',
            scrollY: 120,
            savedAt: Date.now(),
        });

        const wrapper = mount(BackToCollections);

        expect(link(wrapper).attributes('href')).toBe(
            collectionUrl('phrases', 'catch up'),
        );
    });

    it('marks the next visit so the list comes back', async () => {
        const wrapper = mount(BackToCollections);

        await link(wrapper).trigger('click');

        expect(consumeCollectionRestore()).toBe(true);
    });

    it('leaves the saved list alone for a modified click', () => {
        const wrapper = mount(BackToCollections);
        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            ctrlKey: true,
        });

        // A modified click opens a new tab, which jsdom cannot do; preventing
        // its default keeps the test on this document.
        click.preventDefault();
        link(wrapper).element.dispatchEvent(click);

        expect(consumeCollectionRestore()).toBe(false);
    });
});
