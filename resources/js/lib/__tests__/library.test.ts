import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { defaultLibraryFilters } from '@/lib/library-filters';
import {
    clearLibrarySnapshot,
    consumeLibraryRestore,
    isLibraryPage,
    markLibraryRestore,
    mergeSessionCards,
    readLibrarySnapshot,
    saveLibrarySnapshot,
    type LibrarySnapshot,
} from '@/lib/library';
import type { SessionCardData } from '@/types';

function session(
    id: number,
    overrides: Partial<SessionCardData> = {},
): SessionCardData {
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
        ...overrides,
    };
}

function snapshot(overrides: Partial<LibrarySnapshot> = {}): LibrarySnapshot {
    return {
        sessions: [session(1), session(2)],
        nextCursor: 'next',
        hasMore: true,
        total: 4,
        allTotal: 4,
        archivedTotal: 0,
        filters: defaultLibraryFilters(),
        timezone: 'UTC',
        scrollY: 320,
        savedAt: Date.now(),
        ...overrides,
    };
}

beforeEach(() => {
    window.sessionStorage.clear();
});

describe('keeping the Library list together', () => {
    it('adds a new page without showing a session twice', () => {
        const merged = mergeSessionCards(
            [session(1), session(2)],
            [session(2, { topic: 'Updated' }), session(3)],
        );

        expect(merged.map((card) => card.id)).toEqual([1, 2, 3]);
        expect(merged[1]?.topic).toBe('Updated');
    });

    it('recognises a page the Library can use', () => {
        expect(
            isLibraryPage({
                sessions: [],
                next_cursor: null,
                has_more: false,
                total: 0,
                all_total: 0,
                archived_total: 0,
                filters: defaultLibraryFilters(),
                topics: [],
                timezone: 'UTC',
            }),
        ).toBe(true);

        expect(isLibraryPage({ sessions: [] })).toBe(false);
        expect(isLibraryPage(null)).toBe(false);
    });

    it('needs the archived count to trust a page', () => {
        expect(
            isLibraryPage({
                sessions: [],
                next_cursor: null,
                has_more: false,
                total: 0,
                all_total: 0,
                filters: defaultLibraryFilters(),
                topics: [],
                timezone: 'UTC',
            }),
        ).toBe(false);
    });
});

describe('bringing the list back from a session', () => {
    it('saves and reads the loaded list and scroll position', () => {
        const saved = snapshot();

        saveLibrarySnapshot(saved);

        expect(readLibrarySnapshot()).toEqual(saved);
    });

    it('keeps the Archived view and its count with the list', () => {
        saveLibrarySnapshot(
            snapshot({
                archivedTotal: 3,
                filters: {
                    ...defaultLibraryFilters(),
                    view: 'archived',
                },
            }),
        );

        const read = readLibrarySnapshot();

        expect(read?.archivedTotal).toBe(3);
        expect(read?.filters.view).toBe('archived');
    });

    it('starts an older saved list in the Library view', () => {
        window.sessionStorage.setItem(
            'library:snapshot',
            JSON.stringify({
                sessions: [session(1)],
                nextCursor: null,
                hasMore: false,
                total: 1,
                scrollY: 0,
                savedAt: Date.now(),
            }),
        );

        const read = readLibrarySnapshot();

        expect(read?.archivedTotal).toBe(0);
        expect(read?.allTotal).toBe(1);
        expect(read?.filters).toEqual(defaultLibraryFilters());
    });

    it('drops a list that has gone stale', () => {
        saveLibrarySnapshot(snapshot({ savedAt: Date.now() - 31 * 60 * 1000 }));

        expect(readLibrarySnapshot()).toBeNull();
    });

    it('drops a list it cannot read', () => {
        window.sessionStorage.setItem('library:snapshot', '{not json');

        expect(readLibrarySnapshot()).toBeNull();
    });

    it('forgets the list when asked', () => {
        saveLibrarySnapshot(snapshot());

        clearLibrarySnapshot();

        expect(readLibrarySnapshot()).toBeNull();
    });

    it('asks to restore only once', () => {
        markLibraryRestore();

        expect(consumeLibraryRestore()).toBe(true);
        expect(consumeLibraryRestore()).toBe(false);
    });
});
