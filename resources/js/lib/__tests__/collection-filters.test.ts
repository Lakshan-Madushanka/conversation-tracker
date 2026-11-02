import { describe, expect, it } from 'vite-plus/test';
import {
    attentionCountLabel,
    clearCollectionFilters,
    clearCollectionSearch,
    collectionActiveFilterCount,
    collectionCountLabel,
    collectionFilterChips,
    collectionFiltersToQuery,
    collectionSortChoices,
    collectionSortLabel,
    defaultCollectionFilters,
    defaultCollectionSort,
    hasCollectionFilters,
    normalizeCollectionFilters,
    removeCollectionFilterChip,
    withCollectionSearch,
    withCollectionSort,
} from '@/lib/collection-filters';
import type { CollectionTopicOption } from '@/types';

const topics: CollectionTopicOption[] = [
    { id: 2, name: 'Cooking', count: 4 },
    { id: 5, name: 'Travel', count: 2 },
];

describe('collection filters', function () {
    it('starts a tab with its own quiet order', function () {
        expect(defaultCollectionFilters('vocabulary')).toEqual({
            q: '',
            starred: false,
            status: 'all',
            attention: false,
            topics: [],
            sort: 'newest',
        });
        expect(defaultCollectionSort('mistakes')).toBe('most_seen');
        expect(defaultCollectionSort('grammar')).toBe('newest');
    });

    it('offers every order except most seen where it would say nothing', function () {
        expect(collectionSortChoices('vocabulary').map((c) => c.value)).toEqual(
            ['newest', 'az', 'most_seen'],
        );
        expect(collectionSortChoices('grammar').map((c) => c.value)).toEqual([
            'newest',
            'az',
        ]);
        expect(collectionSortLabel('az')).toBe('A to Z');
    });

    it('keeps known values and quietly replaces unknown ones', function () {
        expect(
            normalizeCollectionFilters(
                {
                    q: '  cafe   ',
                    starred: true,
                    status: 'banana' as never,
                    attention: true,
                    topics: [5, 2, 2, -1, 0],
                    sort: 'most_seen',
                },
                'grammar',
            ),
        ).toEqual({
            q: 'cafe',
            starred: true,
            status: 'all',
            attention: false,
            topics: [2, 5],
            sort: 'newest',
        });
    });

    it('drops "needs attention" on grammar, which never needs it', function () {
        expect(
            normalizeCollectionFilters({ attention: true }, 'grammar')
                .attention,
        ).toBe(false);
        expect(
            normalizeCollectionFilters({ attention: true }, 'vocabulary')
                .attention,
        ).toBe(true);
    });

    it('leaves defaults out of the address', function () {
        expect(
            collectionFiltersToQuery(
                defaultCollectionFilters('vocabulary'),
                'vocabulary',
            ),
        ).toEqual({});

        expect(
            collectionFiltersToQuery(
                {
                    q: 'cafe',
                    starred: true,
                    status: 'learned',
                    attention: true,
                    topics: [5, 2],
                    sort: 'az',
                },
                'vocabulary',
            ),
        ).toEqual({
            q: 'cafe',
            starred: '1',
            status: 'learned',
            attention: '1',
            topics: '5,2',
            sort: 'az',
        });
    });

    it('counts chips and search separately', function () {
        const filters = {
            q: 'cafe',
            starred: true,
            status: 'learned' as const,
            attention: true,
            topics: [2],
            sort: 'az' as const,
        };

        expect(collectionActiveFilterCount(filters)).toBe(4);
        expect(hasCollectionFilters(filters)).toBe(true);
        expect(hasCollectionFilters({ ...filters, q: '' })).toBe(true);
        expect(
            hasCollectionFilters({
                ...defaultCollectionFilters('vocabulary'),
                sort: 'az',
            }),
        ).toBe(false);
    });

    it('builds one labelled chip per choice, topics in picker order', function () {
        const chips = collectionFilterChips(
            {
                q: '',
                starred: true,
                status: 'still_difficult',
                attention: true,
                topics: [5, 2],
                sort: 'newest',
            },
            topics,
        );

        expect(chips.map((chip) => chip.key)).toEqual([
            'starred',
            'status',
            'attention',
            'topic:2',
            'topic:5',
        ]);
        expect(chips[1]?.label).toBe('Still difficult');
        expect(chips[3]?.label).toBe('Topic: Cooking');
    });

    it('removes one chip without touching the others', function () {
        const filters = {
            q: '',
            starred: true,
            status: 'learned' as const,
            attention: true,
            topics: [2, 5],
            sort: 'az' as const,
        };
        const chip = collectionFilterChips(filters, topics).find(
            (candidate) => candidate.key === 'topic:2',
        );

        expect(chip).toBeDefined();
        expect(removeCollectionFilterChip(filters, chip!)).toEqual({
            ...filters,
            topics: [5],
        });
    });

    it('clears filters and search independently', function () {
        const filters = {
            q: 'cafe',
            starred: true,
            status: 'learned' as const,
            attention: true,
            topics: [2],
            sort: 'az' as const,
        };

        expect(clearCollectionFilters(filters)).toEqual({
            ...defaultCollectionFilters('vocabulary'),
            q: 'cafe',
            sort: 'az',
        });
        expect(clearCollectionSearch(filters)).toEqual({
            ...filters,
            q: '',
        });
        expect(withCollectionSearch(filters, 'tea').q).toBe('tea');
        expect(withCollectionSort(filters, 'newest').sort).toBe('newest');
    });

    it('ignores a search too short to find anything', function () {
        const filters = defaultCollectionFilters('vocabulary');

        expect(withCollectionSearch(filters, 't').q).toBe('');
        expect(withCollectionSearch({ ...filters, q: 'coffee' }, 't').q).toBe(
            '',
        );
        expect(withCollectionSearch(filters, '  t  ').q).toBe('');
        expect(withCollectionSearch(filters, ' te ').q).toBe('te');
    });

    it('says how many match out of how many, in the collection’s own words', function () {
        expect(collectionCountLabel(12, 180, 'word', 'words', true)).toBe(
            '12 of 180 words',
        );
        expect(collectionCountLabel(1, 1, 'word', 'words', false)).toBe(
            '1 word',
        );
        expect(collectionCountLabel(1, 2, 'word', 'words', false)).toBe(
            '2 words',
        );
        expect(attentionCountLabel(1)).toBe('1 needs attention');
        expect(attentionCountLabel(3)).toBe('3 need attention');
    });
});
