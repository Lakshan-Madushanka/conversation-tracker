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
            source: 'all' as const,
            notebook: null,
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
                    source: 'all' as const,
                    notebook: null,
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
            source: 'all' as const,
            notebook: null,
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
                    source: 'all' as const,
                    notebook: null,
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
            source: 'all' as const,
            notebook: null,
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
                source: 'all' as const,
                notebook: null,
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

    it('keeps a notebook choice and its source in agreement', function () {
        expect(
            normalizeCollectionFilters(
                {
                    source: 'all' as const,
                    notebook: 5,
                    q: '',
                    starred: false,
                    status: 'all' as const,
                    attention: false,
                    topics: [],
                    sort: 'newest' as const,
                },
                'vocabulary',
            ),
        ).toMatchObject({ source: 'notebooks', notebook: 5 });

        expect(
            normalizeCollectionFilters(
                {
                    source: 'sessions' as const,
                    notebook: 5,
                    q: '',
                    starred: false,
                    status: 'all' as const,
                    attention: false,
                    topics: [],
                    sort: 'newest' as const,
                },
                'vocabulary',
            ),
        ).toMatchObject({ source: 'sessions', notebook: null });

        expect(
            normalizeCollectionFilters(
                {
                    source: 'elsewhere' as never,
                    notebook: -1,
                    q: '',
                    starred: false,
                    status: 'all' as const,
                    attention: false,
                    topics: [],
                    sort: 'newest' as const,
                },
                'vocabulary',
            ),
        ).toMatchObject({ source: 'all', notebook: null });
    });

    it('writes the source and notebook into the address', function () {
        expect(
            collectionFiltersToQuery(
                {
                    ...defaultCollectionFilters('vocabulary'),
                    source: 'notebooks',
                    notebook: 5,
                },
                'vocabulary',
            ),
        ).toEqual({ source: 'notebooks', notebook: '5' });

        expect(
            collectionFiltersToQuery(
                {
                    ...defaultCollectionFilters('vocabulary'),
                    source: 'sessions',
                },
                'vocabulary',
            ),
        ).toEqual({ source: 'sessions' });
    });

    it('labels the notebook source first and clears its partner', function () {
        const filters = {
            ...defaultCollectionFilters('vocabulary'),
            source: 'notebooks' as const,
            notebook: 5,
            starred: true,
        };
        const notebooks = [{ id: 5, name: 'Kitchen', topic: 'Food', count: 3 }];
        const chips = collectionFilterChips(filters, [], notebooks);

        expect(chips.map((chip) => chip.key)).toEqual([
            'source',
            'notebook',
            'starred',
        ]);
        expect(chips[0]?.label).toBe('Source: Notebooks');
        expect(chips[1]?.label).toBe('Notebook: Kitchen');
        expect(collectionActiveFilterCount(filters)).toBe(3);
        expect(hasCollectionFilters({ ...filters, starred: false })).toBe(true);

        const source = chips.find((chip) => chip.kind === 'source');

        expect(removeCollectionFilterChip(filters, source!)).toMatchObject({
            source: 'all',
            notebook: null,
        });

        const notebook = chips.find((chip) => chip.kind === 'notebook');

        expect(removeCollectionFilterChip(filters, notebook!)).toMatchObject({
            source: 'notebooks',
            notebook: null,
        });
    });

    it('removes one chip without touching the others', function () {
        const filters = {
            q: '',
            starred: true,
            status: 'learned' as const,
            attention: true,
            topics: [2, 5],
            source: 'all' as const,
            notebook: null,
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
            source: 'all' as const,
            notebook: null,
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
