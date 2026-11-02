import { normalizeSearch } from '@/lib/library-filters';
import type {
    CollectionFilters,
    CollectionSort,
    CollectionStatusFilter,
    CollectionTabKey,
    CollectionTopicOption,
} from '@/types';

export type {
    CollectionFilters,
    CollectionSort,
    CollectionStatusFilter,
} from '@/types';

/**
 * The choices that search, narrow and order one collection, shared by the
 * controls, the chips, the address and the composable so they can never
 * disagree.
 */

/** How many non-space characters a search needs before it runs. */
export const MIN_COLLECTION_SEARCH_LENGTH = 2;

/** The longest search a collection keeps. */
export const MAX_COLLECTION_SEARCH_LENGTH = 100;

export const collectionStatusChoices: ReadonlyArray<{
    value: CollectionStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'All statuses' },
    { value: 'none', label: 'Not marked' },
    { value: 'learned', label: 'Learned' },
    { value: 'still_difficult', label: 'Still difficult' },
];

const statusValues: CollectionStatusFilter[] = collectionStatusChoices.map(
    (choice) => choice.value,
);

/** The order a tab uses before the learner chooses one. */
export function defaultCollectionSort(tab: CollectionTabKey): CollectionSort {
    return tab === 'mistakes' ? 'most_seen' : 'newest';
}

/**
 * The orders a tab can honour. Grammar notes are never seen more than once,
 * so "most often seen" would say nothing there.
 */
export function collectionSortChoices(
    tab: CollectionTabKey,
): ReadonlyArray<{ value: CollectionSort; label: string }> {
    const choices: Array<{ value: CollectionSort; label: string }> = [
        { value: 'newest', label: 'Newest first' },
        { value: 'az', label: 'A to Z' },
    ];

    if (tab !== 'grammar') {
        choices.push({ value: 'most_seen', label: 'Most often seen' });
    }

    return choices;
}

/** A fresh state: no search, no filters, and the tab's usual order. */
export function defaultCollectionFilters(
    tab: CollectionTabKey,
): CollectionFilters {
    return {
        q: '',
        starred: false,
        status: 'all',
        attention: false,
        topics: [],
        sort: defaultCollectionSort(tab),
    };
}

/**
 * Keep a state safe to show and to send. Values this page does not know fall
 * back to their default, topics are whole positive numbers in a stable order,
 * and a sort the tab cannot honour becomes its usual order.
 */
export function normalizeCollectionFilters(
    filters: Partial<CollectionFilters> | null | undefined,
    tab: CollectionTabKey,
): CollectionFilters {
    const defaults = defaultCollectionFilters(tab);

    if (!filters) {
        return defaults;
    }

    const topics = Array.isArray(filters.topics)
        ? [...new Set(filters.topics.filter(isTopicId))].sort((a, b) => a - b)
        : [];

    const status = statusValues.includes(
        filters.status as CollectionStatusFilter,
    )
        ? (filters.status as CollectionStatusFilter)
        : 'all';

    return {
        q: normalizeSearch(filters.q),
        starred: filters.starred === true,
        status,
        // Grammar notes never ask for attention, so the choice is dropped
        // there instead of showing a filter that cannot change anything.
        attention: tab !== 'grammar' && filters.attention === true,
        topics,
        sort: normalizeCollectionSort(filters.sort, tab),
    };
}

export function normalizeCollectionSort(
    value: unknown,
    tab: CollectionTabKey,
): CollectionSort {
    const allowed = collectionSortChoices(tab).map((choice) => choice.value);

    return allowed.includes(value as CollectionSort)
        ? (value as CollectionSort)
        : defaultCollectionSort(tab);
}

/** A stable copy, so a component can pass a state around without sharing it. */
export function copyCollectionFilters(
    filters: CollectionFilters,
): CollectionFilters {
    return { ...filters, topics: [...filters.topics] };
}

/**
 * The address for one search and filter state. Defaults are left out, so an
 * untouched collection keeps a short address.
 */
export function collectionFiltersToQuery(
    filters: CollectionFilters,
    tab: CollectionTabKey,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.q !== '') {
        query.q = filters.q;
    }

    if (filters.starred) {
        query.starred = '1';
    }

    if (filters.status !== 'all') {
        query.status = filters.status;
    }

    if (filters.attention) {
        query.attention = '1';
    }

    if (filters.topics.length > 0) {
        query.topics = filters.topics.join(',');
    }

    if (filters.sort !== defaultCollectionSort(tab)) {
        query.sort = filters.sort;
    }

    return query;
}

/** Whether any filter chip (not the search or the order) is narrowing the list. */
export function collectionActiveFilterCount(
    filters: CollectionFilters,
): number {
    return (
        filters.topics.length +
        (filters.starred ? 1 : 0) +
        (filters.status === 'all' ? 0 : 1) +
        (filters.attention ? 1 : 0)
    );
}

export function hasCollectionSearch(filters: CollectionFilters): boolean {
    return filters.q !== '';
}

/** Whether a chip or the search narrows the list. The order never counts. */
export function hasCollectionFilters(filters: CollectionFilters): boolean {
    return (
        collectionActiveFilterCount(filters) > 0 || hasCollectionSearch(filters)
    );
}

/** Replace the search, keeping every filter and the chosen order. */
export function withCollectionSearch(
    filters: CollectionFilters,
    search: string,
): CollectionFilters {
    return {
        ...copyCollectionFilters(filters),
        q: usableCollectionSearch(normalizeSearch(search)),
    };
}

/**
 * A search the server can honour. A single letter is ignored the moment it is
 * typed, exactly as the server ignores it, so the address and the list never
 * disagree about whether a search is on.
 */
function usableCollectionSearch(search: string): string {
    const meaningful = search.replace(/\s+/gu, '');

    return meaningful.length >= MIN_COLLECTION_SEARCH_LENGTH ? search : '';
}

/** Replace the order, keeping the search and every filter. */
export function withCollectionSort(
    filters: CollectionFilters,
    sort: CollectionSort,
): CollectionFilters {
    return { ...copyCollectionFilters(filters), sort };
}

/**
 * Clear every filter while keeping the order the learner chose and the words
 * they typed. Search and filters are cleared separately, so removing filters
 * never throws away the search.
 */
export function clearCollectionFilters(
    filters: CollectionFilters,
): CollectionFilters {
    return {
        ...defaultCollectionFilters('vocabulary'),
        sort: filters.sort,
        q: filters.q,
    };
}

/** Clear only the search, keeping every filter and the chosen order. */
export function clearCollectionSearch(
    filters: CollectionFilters,
): CollectionFilters {
    return { ...copyCollectionFilters(filters), q: '' };
}

export type CollectionFilterChip =
    | { kind: 'starred'; key: string; label: string }
    | {
          kind: 'status';
          key: string;
          label: string;
          value: CollectionStatusFilter;
      }
    | { kind: 'attention'; key: string; label: string }
    | { kind: 'topic'; key: string; label: string; value: number };

/**
 * One chip per active choice. Topics follow the picker's order, so the chips
 * stay predictable even when they were chosen in another order.
 */
export function collectionFilterChips(
    filters: CollectionFilters,
    topics: CollectionTopicOption[],
): CollectionFilterChip[] {
    const chips: CollectionFilterChip[] = [];

    if (filters.starred) {
        chips.push({ kind: 'starred', key: 'starred', label: 'Starred only' });
    }

    if (filters.status !== 'all') {
        const choice = collectionStatusChoices.find(
            (candidate) => candidate.value === filters.status,
        );

        chips.push({
            kind: 'status',
            key: 'status',
            label: choice?.label ?? 'Status',
            value: filters.status,
        });
    }

    if (filters.attention) {
        chips.push({
            kind: 'attention',
            key: 'attention',
            label: 'Needs attention only',
        });
    }

    for (const topic of topics) {
        if (filters.topics.includes(topic.id)) {
            chips.push({
                kind: 'topic',
                key: `topic:${topic.id}`,
                label: `Topic: ${topic.name}`,
                value: topic.id,
            });
        }
    }

    return chips;
}

/** Remove one chip's choice, leaving every other filter untouched. */
export function removeCollectionFilterChip(
    filters: CollectionFilters,
    chip: CollectionFilterChip,
): CollectionFilters {
    const next = copyCollectionFilters(filters);

    if (chip.kind === 'starred') {
        next.starred = false;
    }

    if (chip.kind === 'status') {
        next.status = 'all';
    }

    if (chip.kind === 'attention') {
        next.attention = false;
    }

    if (chip.kind === 'topic') {
        next.topics = next.topics.filter((id) => id !== chip.value);
    }

    return next;
}

/**
 * The result count the page shows, for example "12 of 180 words" while a
 * search or filter is on, or "180 words" when nothing narrows the list.
 */
export function collectionCountLabel(
    total: number,
    allTotal: number,
    unit: string,
    unitPlural: string,
    filtered: boolean,
): string {
    const plural = allTotal === 1 ? unit : unitPlural;

    return filtered
        ? `${total} of ${allTotal} ${plural}`
        : `${allTotal} ${plural}`;
}

/** How many items of the whole collection need attention. */
export function attentionCountLabel(count: number): string {
    return count === 1 ? '1 needs attention' : `${count} need attention`;
}

export function collectionSortLabel(sort: CollectionSort): string {
    switch (sort) {
        case 'az':
            return 'A to Z';
        case 'most_seen':
            return 'Most often seen';
        default:
            return 'Newest first';
    }
}

function isTopicId(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value > 0;
}
