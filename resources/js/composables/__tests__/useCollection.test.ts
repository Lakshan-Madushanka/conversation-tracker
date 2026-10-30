import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import { useCollection } from '@/composables/useCollection';
import type {
    CollectionItem,
    CollectionNoteItem,
    CollectionPage,
} from '@/types';

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

function note(overrides: Partial<CollectionNoteItem> = {}): CollectionNoteItem {
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

function page(overrides: Partial<CollectionPage> = {}): CollectionPage {
    return {
        tab: 'vocabulary',
        tabs: [
            { key: 'vocabulary', label: 'Vocabulary', count: 1 },
            { key: 'phrases', label: 'Phrases', count: 4 },
            { key: 'grammar', label: 'Grammar notes', count: 2 },
            { key: 'mistakes', label: 'My mistakes', count: 3 },
        ],
        items: [item()],
        next_cursor: 'next-page',
        has_more: true,
        total: 1,
        has_finished_sessions: true,
        open_item: null,
        open_item_missing: false,
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
                    items: [item({ key: 'catch up', title: 'catch up' })],
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

    it('loads a page of grammar notes and keeps each note its own item', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    new Response(
                        JSON.stringify({
                            items: [note(), note({ key: 'note-9' })],
                            next_cursor: null,
                            has_more: false,
                            total: 2,
                        }),
                        {
                            status: 200,
                            headers: { 'Content-Type': 'application/json' },
                        },
                    ),
            ),
        );
        vi.spyOn(router, 'get').mockImplementation(() => {});

        const collection = useCollection(
            page({ tab: 'grammar', items: [], has_more: true }),
        );
        collection.nextCursor.value = 'notes-cursor';

        const loaded = await collection.loadMore();

        expect(loaded).toBe(true);
        expect(collection.items.value.map((entry) => entry.key)).toEqual([
            'note-7',
            'note-9',
        ]);
        expect(collection.total.value).toBe(2);
        expect(collection.hasMore.value).toBe(false);
    });
});
