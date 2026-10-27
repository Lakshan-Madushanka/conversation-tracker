import { afterEach, describe, expect, it } from 'vite-plus/test';
import {
    cameFromCollections,
    clearCollectionSnapshot,
    collectionItemHref,
    collectionReturnUrl,
    consumeCollectionRestore,
    distinctExamples,
    distinctMeanings,
    feedbackTypeFor,
    isCollectionItem,
    isCollectionItemsPage,
    isCollectionPage,
    markCollectionRestore,
    mergeCollectionItems,
    readCollectionSnapshot,
    saveCollectionSnapshot,
    seenIn,
    type CollectionSnapshot,
} from '@/lib/collections';
import { collectionUrl } from '@/lib/collection-query';
import type { CollectionAppearance, CollectionItem } from '@/types';

function appearance(
    overrides: Partial<CollectionAppearance> = {},
): CollectionAppearance {
    return {
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: 'entry-1',
        entry_position: 0,
        meaning: 'a liquid food',
        example: 'I want a soup.',
        ...overrides,
    };
}

function item(overrides: Partial<CollectionItem> = {}): CollectionItem {
    return {
        key: 'soup',
        word: 'soup',
        meaning: 'a liquid food',
        example: 'I want a soup.',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 1,
        needs_attention: false,
        appearances: [appearance()],
        ...overrides,
    };
}

function snapshot(
    overrides: Partial<CollectionSnapshot> = {},
): CollectionSnapshot {
    return {
        tab: 'vocabulary',
        items: [item()],
        nextCursor: null,
        hasMore: false,
        total: 1,
        openWordKey: 'soup',
        scrollY: 240,
        savedAt: Date.now(),
        ...overrides,
    };
}

afterEach(() => {
    window.sessionStorage.clear();
});

describe('merging loaded words', () => {
    it('adds new words after the ones already shown', () => {
        const merged = mergeCollectionItems(
            [item({ key: 'soup', word: 'soup' })],
            [item({ key: 'relish', word: 'relish' })],
        );

        expect(merged.map((word) => word.key)).toEqual(['soup', 'relish']);
    });

    it('replaces a word in place when it comes back with fresh data', () => {
        const merged = mergeCollectionItems(
            [item({ key: 'soup' }), item({ key: 'relish' })],
            [item({ key: 'soup', seen_count: 4 })],
        );

        expect(merged.map((word) => word.key)).toEqual(['soup', 'relish']);
        expect(merged[0]?.seen_count).toBe(4);
    });
});

describe('checking payloads', () => {
    it('accepts a collection page with a word open', () => {
        expect(
            isCollectionPage({
                tab: 'vocabulary',
                tabs: [{ key: 'vocabulary', label: 'Vocabulary', built: true }],
                items: [item()],
                next_cursor: null,
                has_more: false,
                total: 1,
                has_finished_sessions: true,
                open_word: item(),
                open_word_missing: false,
            }),
        ).toBe(true);
    });

    it('refuses a page without the fields the list needs', () => {
        expect(isCollectionPage({ tab: 'vocabulary' })).toBe(false);
    });

    it('accepts a load-more page', () => {
        expect(
            isCollectionItemsPage({
                items: [item()],
                next_cursor: 'abc',
                has_more: true,
                total: 31,
            }),
        ).toBe(true);
    });

    it('refuses a load-more page with a broken item', () => {
        expect(
            isCollectionItemsPage({
                items: [{ key: 'soup' }],
                next_cursor: null,
                has_more: false,
                total: 1,
            }),
        ).toBe(false);
    });

    it('checks every field of a word', () => {
        expect(isCollectionItem(item())).toBe(true);
        expect(isCollectionItem({ ...item(), appearances: 'none' })).toBe(
            false,
        );
    });
});

describe('remembering the list for the trip back', () => {
    it('reads a snapshot exactly as it was saved', () => {
        const saved = snapshot();

        saveCollectionSnapshot(saved);

        expect(readCollectionSnapshot()).toEqual(saved);
    });

    it('drops a snapshot that is too old to trust', () => {
        saveCollectionSnapshot(
            snapshot({ savedAt: Date.now() - 31 * 60 * 1000 }),
        );

        expect(readCollectionSnapshot()).toBeNull();
        expect(
            window.sessionStorage.getItem('collections:snapshot'),
        ).toBeNull();
    });

    it('drops a snapshot whose shape is broken', () => {
        window.sessionStorage.setItem(
            'collections:snapshot',
            JSON.stringify({ tab: 'vocabulary' }),
        );

        expect(readCollectionSnapshot()).toBeNull();
    });

    it('survives unreadable storage', () => {
        window.sessionStorage.setItem('collections:snapshot', 'not json');

        expect(readCollectionSnapshot()).toBeNull();
    });

    it('forgets the list on request', () => {
        saveCollectionSnapshot(snapshot());
        clearCollectionSnapshot();

        expect(readCollectionSnapshot()).toBeNull();
    });

    it('uses the restore marker once', () => {
        markCollectionRestore();

        expect(consumeCollectionRestore()).toBe(true);
        expect(consumeCollectionRestore()).toBe(false);
    });
});

describe('the way back to Collections', () => {
    it('returns to the opened word of the remembered tab', () => {
        saveCollectionSnapshot(
            snapshot({ tab: 'phrases', openWordKey: 'catch up' }),
        );

        expect(collectionReturnUrl()).toBe(
            collectionUrl('phrases', 'catch up'),
        );
    });

    it('starts on vocabulary when nothing was remembered', () => {
        expect(collectionReturnUrl()).toBe(collectionUrl('vocabulary'));
    });

    it('recognises a session address that came from a collection', () => {
        expect(
            cameFromCollections(
                '/sessions/7?from=collections&question=1&field=entry',
            ),
        ).toBe(true);
        expect(cameFromCollections('/sessions/7?question=1')).toBe(false);
    });
});

describe('the link to the original session', () => {
    it('names the question, box, row and entry', () => {
        const href = collectionItemHref(appearance());

        expect(href).toContain('/sessions/1?');
        expect(href).toContain('from=collections');
        expect(href).toContain('question=1');
        expect(href).toContain('field=entry');
        expect(href).toContain('type=vocabulary');
        expect(href).toContain('entry=entry-1');
        expect(href).toContain('position=0');
    });

    it('falls back to the row position when an entry has no id', () => {
        const href = collectionItemHref(
            appearance({ entry_id: null, entry_position: 2 }),
        );

        expect(href).not.toContain('entry=');
        expect(href).toContain('position=2');
    });

    it('names the feedback box of the tab it came from', () => {
        expect(feedbackTypeFor('phrases')).toBe('phrase');
        expect(feedbackTypeFor('grammar')).toBe('grammar_note');
        expect(feedbackTypeFor('mistakes')).toBe('mistake');
        expect(feedbackTypeFor('vocabulary')).toBe('vocabulary');
    });
});

describe('the meanings, examples and sightings of a word', () => {
    it('shows each different meaning once, newest first', () => {
        const chosen = item({
            appearances: [
                appearance({ meaning: 'a liquid food' }),
                appearance({ meaning: 'A LIQUID FOOD' }),
                appearance({ meaning: 'a bowl of soup' }),
                appearance({ meaning: '' }),
            ],
        });

        expect(distinctMeanings(chosen).map((entry) => entry.meaning)).toEqual([
            'a liquid food',
            'a bowl of soup',
        ]);
    });

    it('shows each different example once, newest first', () => {
        const chosen = item({
            appearances: [
                appearance({ example: 'I want a soup.' }),
                appearance({ example: 'I want a soup.' }),
                appearance({ example: 'The soup was hot.' }),
                appearance({ example: '' }),
            ],
        });

        expect(distinctExamples(chosen).map((entry) => entry.example)).toEqual([
            'I want a soup.',
            'The soup was hot.',
        ]);
    });

    it('counts a sighting once per question, across every row', () => {
        const chosen = item({
            appearances: [
                appearance({ session_id: 1, question_position: 1 }),
                appearance({ session_id: 1, question_position: 1 }),
                appearance({ session_id: 1, question_position: 2 }),
                appearance({ session_id: 2, question_position: 1 }),
            ],
        });

        expect(seenIn(chosen)).toHaveLength(3);
    });
});
