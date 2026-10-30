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
    CollectionMistakeItem,
    CollectionNoteItem,
    CollectionPage,
    CollectionTabData,
} from '@/types';

const tabs: CollectionTabData[] = [
    { key: 'vocabulary', label: 'Vocabulary', count: 1 },
    { key: 'phrases', label: 'Phrases', count: 4 },
    { key: 'grammar', label: 'Grammar notes', count: 2 },
    { key: 'mistakes', label: 'My mistakes', count: 3 },
];

function mistakeItem(
    overrides: Partial<CollectionMistakeItem> = {},
): CollectionMistakeItem {
    return {
        key: 'mistake-agree',
        title: 'I am agree',
        correction: 'I agree',
        correction_html: '',
        reason: 'Verb tense',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 2,
        worth_practicing: false,
        needs_attention: false,
        attention_reason: null,
        appearances: [],
        ...overrides,
    };
}

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

function noteItem(
    overrides: Partial<CollectionNoteItem> = {},
): CollectionNoteItem {
    return {
        key: 'note-7',
        text: 'Use the past tense here.',
        preview: 'Use the past tense here.',
        html: '<p>Use the <strong>past tense</strong> here.</p>',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: null,
        entry_position: 0,
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
        open_item: null,
        open_item_missing: false,
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
        expect(mounted?.text()).toContain('Grammar notes');
        expect(mounted?.text()).toContain('My mistakes');
        expect(mounted?.text()).toContain('soup');
        expect(mounted?.text()).toContain('a liquid food');
    });

    it('reads every collection count on its tab', () => {
        mountPage();

        const triggers = mounted?.findAll('[role="tab"]') ?? [];

        expect(triggers.find((t) => t.text().includes('Phrases'))?.text()).toBe(
            'Phrases (4)',
        );
        expect(
            triggers.find((t) => t.text().includes('Vocabulary'))?.text(),
        ).toBe('Vocabulary (1)');
        expect(triggers.find((t) => t.text().includes('Grammar'))?.text()).toBe(
            'Grammar notes (2)',
        );
        expect(
            triggers.find((t) => t.text().includes('My mistakes'))?.text(),
        ).toBe('My mistakes (3)');
    });

    it('names a tab for screen readers with its item count', async () => {
        vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        const phrases = mounted
            ?.findAll('[role="tab"]')
            .find((t) => t.text().includes('Phrases'));

        await phrases?.trigger('mouseenter');
        await phrases?.trigger('focus');

        expect(
            document
                .querySelector('[role="tab"][aria-label*="Phrases"]')
                ?.getAttribute('aria-label'),
        ).toBe('Phrases, 4 phrases');
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

    it('welcomes the mistakes tab with its intro and friendly empty state', async () => {
        mountPage({
            tab: 'mistakes',
            items: [],
            total: 0,
            has_finished_sessions: true,
        });

        expect(mounted?.text()).toContain(
            'These are the mistakes you see most often.',
        );
        expect(mounted?.text()).toContain('Nothing here yet');
        expect(mounted?.text()).toContain(
            'Mistakes you save in finished sessions will appear here',
        );
        expect(mounted?.text()).not.toContain('on their way');
    });

    it('names the mistakes tab for screen readers with its mistake count', async () => {
        vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        await tab('My mistakes')?.trigger('focus');
        await tab('My mistakes')?.trigger('mouseenter');

        expect(
            document
                .querySelector('[role="tab"][aria-label*="My mistakes"]')
                ?.getAttribute('aria-label'),
        ).toBe('My mistakes, 3 mistakes');
    });

    it('opens a mistake in its own panel', async () => {
        mountPage({
            tab: 'mistakes',
            items: [mistakeItem()],
            total: 1,
            open_item: mistakeItem(),
        });
        await flushPromises();
        await flushPromises();

        expect(document.body.textContent).toContain(
            'Everything about this mistake',
        );
        expect(document.body.textContent).toContain('What I said');
        expect(document.body.textContent).toContain('I am agree');
        expect(document.body.textContent).toContain('I agree');
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

    it('says calmly when an item named in the address is gone', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage({ open_item_missing: true });

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
        mountPage({ open_item: item() });
        await flushPromises();

        expect(document.body.textContent).toContain(
            'Everything you saved for this word',
        );
    });

    it('lists grammar notes as their own items and opens one as a note', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage({
            tab: 'grammar',
            items: [
                noteItem(),
                noteItem({ key: 'note-9', preview: 'Second note preview.' }),
            ],
            total: 2,
        });
        await flushPromises();

        // Every item keeps its own voice: no merging, no "seen N times".
        expect(mounted?.text()).toContain('Use the past tense here.');
        expect(mounted?.text()).toContain('Second note preview.');
        expect(mounted?.text()).not.toContain('Seen');

        const noteRow = mounted
            ?.findAll('button')
            .find((button) =>
                button.element.textContent?.trim().startsWith('Use the'),
            );

        await noteRow?.trigger('click');

        expect(get.mock.calls[0]?.[0]).toBe(collectionUrl('grammar', 'note-7'));

        await mounted?.setProps(pageProps({ open_item: noteItem() }));
        await flushPromises();

        expect(document.body.textContent).toContain('Grammar note');
        expect(document.body.textContent).toContain(
            'Everything this note says, as you saved it in its session.',
        );
        expect(document.body.textContent).toContain('Where it came from');
    });
});

describe('coming back to the Collections page', () => {
    it('puts the remembered list, item and scroll position back', async () => {
        const saved = item({ key: 'zest', title: 'zest' });

        window.history.replaceState(
            null,
            '',
            '/collections?tab=vocabulary&item=zest',
        );

        saveCollectionSnapshot({
            tab: 'vocabulary',
            items: [item(), saved],
            nextCursor: null,
            hasMore: false,
            total: 2,
            openItemKey: 'zest',
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

        expect(mounted?.text()).toContain('No phrases yet');
    });
});
