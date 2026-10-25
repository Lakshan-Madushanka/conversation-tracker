import { beforeEach, describe, expect, it } from 'vite-plus/test';
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
            }),
        ).toBe(true);

        expect(isLibraryPage({ sessions: [] })).toBe(false);
        expect(isLibraryPage(null)).toBe(false);
    });
});

describe('bringing the list back from a session', () => {
    it('saves and reads the loaded list and scroll position', () => {
        const saved = snapshot();

        saveLibrarySnapshot(saved);

        expect(readLibrarySnapshot()).toEqual(saved);
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
