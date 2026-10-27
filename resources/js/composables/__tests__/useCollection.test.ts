import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import { useCollection } from '@/composables/useCollection';
import type { CollectionItem, CollectionPage } from '@/types';

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

function page(overrides: Partial<CollectionPage> = {}): CollectionPage {
    return {
        tab: 'vocabulary',
        tabs: [
            { key: 'vocabulary', label: 'Vocabulary', built: true },
            { key: 'phrases', label: 'Phrases', built: false },
            { key: 'grammar', label: 'Grammar', built: false },
            { key: 'mistakes', label: 'My mistakes', built: false },
        ],
        items: [item()],
        next_cursor: 'next-page',
        has_more: true,
        total: 1,
        has_finished_sessions: true,
        open_word: null,
        open_word_missing: false,
        ...overrides,
    };
}

describe('the collection on screen', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('stops loading when another tab is opened mid-request', async () => {
        let answer: ((response: Response) => void) | undefined;

        vi.stubGlobal(
            'fetch',
            vi.fn(
                () =>
                    new Promise<Response>((resolve) => {
                        answer = resolve;
                    }),
            ),
        );
        vi.spyOn(router, 'get').mockImplementation(() => {});

        const collection = useCollection(page());

        const loadingMore = collection.loadMore();

        expect(collection.loading.value).toBe(true);

        collection.setTab('phrases');

        expect(collection.loading.value).toBe(false);

        answer?.(
            new Response(
                JSON.stringify({
                    items: [item({ key: 'catch up', word: 'catch up' })],
                    next_cursor: null,
                    has_more: false,
                    total: 1,
                }),
                {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                },
            ),
        );

        await loadingMore;

        // The old tab's answer must not land on the new tab.
        expect(collection.items.value).toEqual([]);
        expect(collection.loading.value).toBe(false);
    });
});
