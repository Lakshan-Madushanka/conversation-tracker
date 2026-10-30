import { afterEach, describe, expect, it } from 'vite-plus/test';
import {
    cameFromCollections,
    clearCollectionSnapshot,
    collectionItemHref,
    collectionReturnUrl,
    consumeCollectionRestore,
    distinctExamples,
    distinctMeanings,
    distinctReasons,
    feedbackTypeFor,
    isCollectionItemsPage,
    isCollectionMistakeItem,
    isCollectionItem,
    isCollectionNoteItem,
    isCollectionPage,
    markCollectionRestore,
    mergeCollectionItems,
    mistakeSeenIn,
    readCollectionSnapshot,
    saveCollectionSnapshot,
    seenIn,
    seenLabel,
    type CollectionSnapshot,
} from '@/lib/collections';
import { collectionUrl } from '@/lib/collection-query';
import type {
    CollectionAppearance,
    CollectionItem,
    CollectionMistakeAppearance,
    CollectionMistakeItem,
    CollectionNoteItem,
} from '@/types';

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
        example_html: '',
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
        appearances: [appearance()],
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
        entry_id: 'note-a',
        entry_position: 0,
        ...overrides,
    };
}

function mistakeAppearance(
    overrides: Partial<CollectionMistakeAppearance> = {},
): CollectionMistakeAppearance {
    return {
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: 'entry-1',
        entry_position: 0,
        said: 'I am agree',
        correction: 'I agree',
        correction_html: '<strong>I agree</strong>',
        reason: 'verb tense',
        ...overrides,
    };
}

function mistake(
    overrides: Partial<CollectionMistakeItem> = {},
): CollectionMistakeItem {
    return {
        key: 'mistake-agree',
        title: 'I am agree',
        correction: 'I agree',
        correction_html: '<strong>I agree</strong>',
        reason: 'verb tense',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 2,
        worth_practicing: false,
        needs_attention: false,
        attention_reason: null,
        appearances: [mistakeAppearance()],
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
        openItemKey: 'soup',
        scrollY: 240,
        savedAt: Date.now(),
        ...overrides,
    };
}

afterEach(() => {
    window.sessionStorage.clear();
});

describe('merging loaded items', () => {
    it('adds new items after the ones already shown', () => {
        const merged = mergeCollectionItems(
            [item({ key: 'soup' })],
            [item({ key: 'relish', title: 'relish' })],
        );

        expect(merged.map((entry) => entry.key)).toEqual(['soup', 'relish']);
    });

    it('replaces an item in place when it comes back with fresh data', () => {
        const merged = mergeCollectionItems(
            [item({ key: 'soup' }), item({ key: 'relish' })],
            [item({ key: 'soup', seen_count: 4 })],
        );

        const first = merged[0];

        expect(merged.map((entry) => entry.key)).toEqual(['soup', 'relish']);
        expect(isCollectionItem(first) && first.seen_count).toBe(4);
    });

    it('keeps two identical notes as their own items', () => {
        const merged = mergeCollectionItems(
            [note({ key: 'note-7' })],
            [note({ key: 'note-9' })],
        );

        expect(merged.map((entry) => entry.key)).toEqual(['note-7', 'note-9']);
    });
});

describe('checking payloads', () => {
    it('accepts a collection page with an item open', () => {
        expect(
            isCollectionPage({
                tab: 'vocabulary',
                tabs: [
                    {
                        key: 'vocabulary',
                        label: 'Vocabulary',
                        count: 1,
                    },
                ],
                items: [item()],
                next_cursor: null,
                has_more: false,
                total: 1,
                has_finished_sessions: true,
                open_item: item(),
                open_item_missing: false,
            }),
        ).toBe(true);
    });

    it('accepts a collection page with a note open', () => {
        expect(
            isCollectionPage({
                tab: 'grammar',
                tabs: [
                    {
                        key: 'grammar',
                        label: 'Grammar notes',
                        count: 1,
                    },
                ],
                items: [note()],
                next_cursor: null,
                has_more: false,
                total: 1,
                has_finished_sessions: true,
                open_item: note(),
                open_item_missing: false,
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

    it('accepts a load-more page of notes', () => {
        expect(
            isCollectionItemsPage({
                items: [note()],
                next_cursor: null,
                has_more: false,
                total: 1,
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

    it('checks every field of a note', () => {
        expect(isCollectionNoteItem(note())).toBe(true);
        expect(isCollectionNoteItem({ ...note(), html: undefined })).toBe(
            false,
        );
        expect(isCollectionItem(note())).toBe(false);
        expect(isCollectionNoteItem(item())).toBe(false);
    });

    it('checks every field of a mistake', () => {
        expect(isCollectionMistakeItem(mistake())).toBe(true);
        expect(
            isCollectionMistakeItem({ ...mistake(), reason: undefined }),
        ).toBe(false);
        expect(
            isCollectionMistakeItem({ ...mistake(), worth_practicing: 1 }),
        ).toBe(false);
        expect(isCollectionMistakeItem(item())).toBe(false);
        expect(isCollectionItem(mistake())).toBe(false);
    });

    it('threads a mistake through the shared opened-item check', () => {
        const page = isCollectionPage({
            tab: 'mistakes',
            tabs: [{ key: 'mistakes', label: 'My mistakes', count: 1 }],
            items: [mistake()],
            next_cursor: null,
            has_more: false,
            total: 1,
            has_finished_sessions: true,
            open_item: mistake(),
            open_item_missing: false,
        });

        const loaded = isCollectionItemsPage({
            items: [mistake()],
            next_cursor: null,
            has_more: false,
            total: 1,
        });

        expect(page).toBe(true);
        expect(loaded).toBe(true);
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
    it('returns to the opened item of the remembered tab', () => {
        saveCollectionSnapshot(
            snapshot({ tab: 'phrases', openItemKey: 'catch up' }),
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

describe('the reasons, sightings and wording of a mistake', () => {
    it('shows each different reason once, newest first, with its appearances', () => {
        const chosen = mistake({
            appearances: [
                mistakeAppearance({ reason: 'Verb tense' }),
                mistakeAppearance({ reason: 'verb tense', entry_position: 1 }),
                mistakeAppearance({ reason: 'Word choice' }),
                mistakeAppearance({ reason: '   ' }),
            ],
        });

        const reasons = distinctReasons(chosen);

        expect(reasons.map((entry) => entry.reason)).toEqual([
            'Verb tense',
            'Word choice',
        ]);
        expect(reasons[0]?.appearances).toHaveLength(2);
        expect(reasons[1]?.appearances).toHaveLength(1);
    });

    it('counts a mistake sighting once per question, like a word', () => {
        const chosen = mistake({
            appearances: [
                mistakeAppearance({ session_id: 1, question_position: 1 }),
                mistakeAppearance({
                    session_id: 1,
                    question_position: 1,
                    entry_id: 'another',
                }),
                mistakeAppearance({ session_id: 1, question_position: 2 }),
                mistakeAppearance({ session_id: 2, question_position: 1 }),
            ],
        });

        expect(mistakeSeenIn(chosen)).toHaveLength(3);
    });

    it('keeps the seen wording singular and plural correct', () => {
        expect(seenLabel(1)).toBe('Seen 1 time');
        expect(seenLabel(2)).toBe('Seen 2 times');
        expect(seenLabel(30)).toBe('Seen 30 times');
    });

    it('keeps a mistake in the merged list when the next page arrives', () => {
        const merged = mergeCollectionItems(
            [mistake()],
            [mistake({ key: 'mistake-said', title: 'I are agree' })],
        );

        expect(merged.map((entry) => entry.key)).toEqual([
            'mistake-agree',
            'mistake-said',
        ]);
    });
});
