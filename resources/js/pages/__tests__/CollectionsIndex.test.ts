import { nextTick } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import Index from '@/pages/collections/Index.vue';
import { collectionUrl } from '@/lib/collection-query';
import {
    markCollectionRestore,
    saveCollectionSnapshot,
} from '@/lib/collections';
import { index as collectionsIndex } from '@/routes/collections';
import type {
    CollectionItem,
    CollectionItemsPage,
    CollectionPage,
    CollectionTabData,
} from '@/types';

const tabs: CollectionTabData[] = [
    { key: 'vocabulary', label: 'Vocabulary', built: true },
    { key: 'phrases', label: 'Phrases', built: false },
    { key: 'grammar', label: 'Grammar', built: false },
    { key: 'mistakes', label: 'My mistakes', built: false },
];

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

function pageProps(overrides: Partial<CollectionPage> = {}): CollectionPage {
    const items = overrides.items ?? [item()];

    return {
        tab: 'vocabulary',
        tabs,
        items,
        next_cursor: null,
        has_more: false,
        total: items.length,
        has_finished_sessions: true,
        open_word: null,
        open_word_missing: false,
        ...overrides,
    };
}

let mounted: VueWrapper | undefined;

function mountPage(overrides: Partial<CollectionPage> = {}): VueWrapper {
    mounted = mount(Index, {
        attachTo: document.body,
        props: pageProps(overrides),
    });

    return mounted;
}

function tab(label: string) {
    return mounted
        ?.findAll('[role="tab"]')
        .find((trigger) => trigger.text().includes(label));
}

function stubFetch(page: CollectionItemsPage): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                new Response(JSON.stringify(page), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
        ),
    );
}

beforeEach(() => {
    window.sessionStorage.clear();
});

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
    window.sessionStorage.clear();
    window.history.replaceState(null, '', '/');
});

describe('the Collections page', () => {
    it('lists the gathered words under the four tabs', () => {
        mountPage();

        expect(mounted?.text()).toContain('Vocabulary');
        expect(mounted?.text()).toContain('Phrases');
        expect(mounted?.text()).toContain('Grammar');
        expect(mounted?.text()).toContain('My mistakes');
        expect(mounted?.text()).toContain('soup');
        expect(mounted?.text()).toContain('a liquid food');
    });

    it('asks the server for another tab and keeps the page in place', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        await tab('Phrases')?.trigger('focus');

        expect(get).toHaveBeenCalledWith(
            collectionUrl('phrases'),
            {},
            expect.objectContaining({
                preserveState: true,
                preserveScroll: false,
            }),
        );
    });

    it('keeps an unbuilt tab calm and points back to vocabulary', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage({
            tab: 'phrases',
            items: [],
            total: 0,
            has_finished_sessions: true,
        });

        expect(mounted?.text()).toContain('Phrases are on their way');

        const browse = mounted
            ?.findAll('button')
            .find((button) => button.text().includes('Browse vocabulary'));

        await browse?.trigger('click');

        expect(get).toHaveBeenCalledWith(
            collectionUrl('vocabulary'),
            {},
            expect.anything(),
        );
    });

    it('opens a word in the address and asks for its panel', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        const word = mounted
            ?.findAll('button')
            .find((button) => button.text() === 'soup');

        await word?.trigger('click');

        expect(get).toHaveBeenCalledWith(
            collectionUrl('vocabulary', 'soup'),
            {},
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('says calmly when a word named in the address is gone', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage({ open_word_missing: true });

        expect(mounted?.text()).toContain('That word is no longer here');

        const dismiss = mounted
            ?.findAll('button')
            .find((button) => button.text().includes('Dismiss'));

        await dismiss?.trigger('click');

        expect(mounted?.text()).not.toContain('That word is no longer here');
        expect(get).toHaveBeenCalledWith(
            collectionUrl('vocabulary'),
            {},
            expect.objectContaining({ replace: true }),
        );
    });

    it('shows the opened word in its panel', async () => {
        mountPage({ open_word: item() });
        await flushPromises();

        expect(document.body.textContent).toContain(
            'Everything you saved for this word',
        );
    });
});

describe('coming back to the Collections page', () => {
    it('puts the remembered list, word and scroll position back', async () => {
        const saved = item({ key: 'zest', word: 'zest' });

        window.history.replaceState(
            null,
            '',
            '/collections?tab=vocabulary&word=zest',
        );

        saveCollectionSnapshot({
            tab: 'vocabulary',
            items: [item(), saved],
            nextCursor: null,
            hasMore: false,
            total: 2,
            openWordKey: 'zest',
            scrollY: 320,
            savedAt: Date.now(),
        });
        markCollectionRestore();

        stubFetch({
            items: [item(), saved],
            next_cursor: null,
            has_more: false,
            total: 2,
        });

        const scrollTo = vi
            .spyOn(window, 'scrollTo')
            .mockImplementation(() => {});

        mountPage({ items: [], total: 0 });
        await flushPromises();
        await nextTick();

        expect(mounted?.text()).toContain('zest');
        expect(document.body.textContent).toContain(
            'Everything you saved for this word',
        );
        expect(scrollTo).toHaveBeenCalledWith(0, 320);
    });

    it('remembers the list for the next visit when the page is left', async () => {
        mountPage();
        mounted?.unmount();
        mounted = undefined;

        const stored = JSON.parse(
            window.sessionStorage.getItem('collections:snapshot') ?? '{}',
        );

        expect(stored.tab).toBe('vocabulary');
        expect(stored.items[0].key).toBe('soup');
        expect(typeof stored.savedAt).toBe('number');
    });

    it('follows the tab a browser Back restored from history', async () => {
        const on = vi.spyOn(router, 'on');
        mountPage();

        const navigate = on.mock.calls.find(
            ([event]) => event === 'navigate',
        )?.[1] as ((event: unknown) => void) | undefined;

        navigate?.({
            detail: {
                type: 'history',
                page: {
                    url: collectionsIndex.url({ query: { tab: 'phrases' } }),
                    props: pageProps({
                        tab: 'phrases',
                        items: [],
                        total: 0,
                    }),
                },
            },
        });

        await nextTick();

        expect(mounted?.text()).toContain('Phrases are on their way');
    });
});
