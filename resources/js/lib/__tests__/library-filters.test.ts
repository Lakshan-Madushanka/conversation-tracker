import { describe, expect, it } from 'vite-plus/test';
import {
    activeFilterCount,
    clearLibraryFilters,
    defaultLibraryFilters,
    filtersToQuery,
    hasActiveFilters,
    libraryFilterChips,
    normalizeLibraryFilters,
    removeFilterChip,
    sessionCountLabel,
    sortLabel,
    withSort,
    type LibraryFilters,
} from '@/lib/library-filters';
import type { TopicSummary } from '@/types';

const topics: TopicSummary[] = [
    { id: 2, name: 'Travel' },
    { id: 1, name: 'Work' },
];

function filters(overrides: Partial<LibraryFilters> = {}): LibraryFilters {
    return { ...defaultLibraryFilters(), ...overrides };
}

describe('a safe filter state', () => {
    it('starts with no filters and newest first', () => {
        expect(defaultLibraryFilters()).toEqual({
            topics: [],
            types: [],
            date: 'any',
            from: null,
            to: null,
            status: 'all',
            sort: 'newest',
        });
    });

    it('ignores values the app does not know', () => {
        const normalized = normalizeLibraryFilters({
            topics: [3, 3, -1, 0, Number.NaN],
            types: ['mistake', 'nonsense'] as LibraryFilters['types'],
            date: 'yesterday' as LibraryFilters['date'],
            status: 'paused' as LibraryFilters['status'],
            sort: 'random' as LibraryFilters['sort'],
        });

        expect(normalized.topics).toEqual([3]);
        expect(normalized.types).toEqual(['mistake']);
        expect(normalized.date).toBe('any');
        expect(normalized.status).toBe('all');
        expect(normalized.sort).toBe('newest');
    });

    it('keeps content types in the order the controls show them', () => {
        const normalized = normalizeLibraryFilters({
            types: ['grammar_note', 'mistake', 'phrase'],
        });

        expect(normalized.types).toEqual(['mistake', 'phrase', 'grammar_note']);
    });

    it('corrects a custom range whose end comes before its start', () => {
        const normalized = normalizeLibraryFilters({
            date: 'custom',
            from: '2026-10-20',
            to: '2026-10-14',
        });

        expect(normalized.from).toBe('2026-10-14');
        expect(normalized.to).toBe('2026-10-20');
    });

    it('drops a custom range with no days', () => {
        const normalized = normalizeLibraryFilters({
            date: 'custom',
            from: null,
            to: null,
        });

        expect(normalized.date).toBe('any');
        expect(normalized.from).toBeNull();
        expect(normalized.to).toBeNull();
    });

    it('keeps a quick date choice without a range', () => {
        const normalized = normalizeLibraryFilters({ date: 'last7' });

        expect(normalized.date).toBe('last7');
        expect(normalized.from).toBeNull();
        expect(normalized.to).toBeNull();
    });
});

describe('the address a filter state travels in', () => {
    it('leaves defaults out and keeps the timezone', () => {
        expect(
            filtersToQuery(defaultLibraryFilters(), 'Europe/London'),
        ).toEqual({
            tz: 'Europe/London',
        });
    });

    it('writes every active choice', () => {
        expect(
            filtersToQuery(
                filters({
                    topics: [1, 2],
                    types: ['mistake', 'vocabulary'],
                    date: 'custom',
                    from: '2026-10-01',
                    to: '2026-10-14',
                    status: 'finished',
                    sort: 'oldest',
                }),
                'UTC',
            ),
        ).toEqual({
            topics: '1,2',
            types: 'mistake,vocabulary',
            date: 'custom',
            from: '2026-10-01',
            to: '2026-10-14',
            status: 'finished',
            sort: 'oldest',
            tz: 'UTC',
        });
    });

    it('does not send a custom range for a quick choice', () => {
        const query = filtersToQuery(
            filters({
                date: 'today',
                from: '2026-10-01',
                to: '2026-10-14',
            }),
            '',
        );

        expect(query).toEqual({ date: 'today' });
    });
});

describe('knowing whether filters are active', () => {
    it('counts every choice but never the sort', () => {
        expect(hasActiveFilters(defaultLibraryFilters())).toBe(false);

        const active = filters({
            topics: [1, 2],
            types: ['mistake'],
            date: 'today',
            status: 'unfinished',
            sort: 'oldest',
        });

        expect(activeFilterCount(active)).toBe(5);
        expect(hasActiveFilters(active)).toBe(true);
    });

    it('changes only the order', () => {
        expect(withSort(defaultLibraryFilters(), 'oldest').sort).toBe('oldest');
    });

    it('clears the filters but keeps the chosen order', () => {
        const cleared = clearLibraryFilters(
            filters({
                topics: [1],
                types: ['mistake'],
                date: 'today',
                status: 'finished',
                sort: 'recently_edited',
            }),
        );

        expect(cleared).toEqual({
            ...defaultLibraryFilters(),
            sort: 'recently_edited',
        });
    });
});

describe('the chips that show active filters', () => {
    it('gives one chip per choice, topics in the picker order', () => {
        const chips = libraryFilterChips(
            filters({
                topics: [1, 2],
                types: ['vocabulary', 'mistake'],
                date: 'today',
                status: 'unfinished',
            }),
            topics,
        );

        expect(chips.map((chip) => chip.label)).toEqual([
            'Topic: Travel',
            'Topic: Work',
            'Mistakes',
            'Vocabulary',
            'Today',
            'Unfinished',
        ]);
    });

    it('removes only the chosen chip', () => {
        const filtersInForce = filters({
            topics: [1, 2],
            types: ['mistake', 'vocabulary'],
            date: 'last30',
            status: 'finished',
            sort: 'oldest',
        });

        const chips = libraryFilterChips(filtersInForce, topics);

        expect(removeFilterChip(filtersInForce, chips[0]!)).toMatchObject({
            topics: [1],
        });

        expect(removeFilterChip(filtersInForce, chips[3]!)).toMatchObject({
            types: ['mistake'],
        });

        expect(removeFilterChip(filtersInForce, chips[4]!)).toMatchObject({
            date: 'any',
            from: null,
            to: null,
        });

        expect(removeFilterChip(filtersInForce, chips[5]!)).toMatchObject({
            status: 'all',
        });

        expect(removeFilterChip(filtersInForce, chips[1]!).sort).toBe('oldest');
    });

    it('names a custom range with its days', () => {
        const chips = libraryFilterChips(
            filters({
                date: 'custom',
                from: '2026-10-01',
                to: '2026-10-14',
            }),
            [],
        );

        expect(chips).toHaveLength(1);
        expect(chips[0]?.label).toContain('2026');
        expect(chips[0]?.label).toContain('–');
    });

    it('shows no chips when nothing narrows the list', () => {
        expect(libraryFilterChips(defaultLibraryFilters(), topics)).toEqual([]);
    });
});

describe('the words the page shows', () => {
    it('counts matching sessions out of all of them', () => {
        expect(sessionCountLabel(12, 42, true)).toBe('12 of 42 sessions');
        expect(sessionCountLabel(1, 42, true)).toBe('1 of 42 sessions');
        expect(sessionCountLabel(1, 1, true)).toBe('1 of 1 session');
    });

    it('counts every session when nothing is filtered', () => {
        expect(sessionCountLabel(42, 42, false)).toBe('42 sessions');
        expect(sessionCountLabel(1, 1, false)).toBe('1 session');
        expect(sessionCountLabel(0, 0, false)).toBe('0 sessions');
    });

    it('describes the order in friendly words', () => {
        expect(sortLabel('newest')).toBe('newest first');
        expect(sortLabel('oldest')).toBe('oldest first');
        expect(sortLabel('recently_edited')).toBe('recently edited');
    });
});
