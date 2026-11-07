import { normalizeSearch } from '@/lib/library-filters';
import { shortDate } from '@/lib/format';
import type {
    CustomReviewCardData,
    CustomReviewFiltersState,
    CustomReviewSortValue,
    CustomReviewStatusValue,
    CustomReviewsPageData,
} from '@/types';

/** How many reviews the listing loads at a time. */
export const CUSTOM_REVIEW_PAGE_SIZE = 20;

/** The furthest the listing will restore after a refresh. */
export const CUSTOM_REVIEW_MAX_PAGE_SIZE = 100;

/** The statuses a review can be filtered by, in the order shown. */
export const customReviewStatusChoices: ReadonlyArray<{
    value: CustomReviewStatusValue;
    label: string;
}> = [
    { value: 'empty', label: 'Empty' },
    { value: 'draft', label: 'Draft' },
    { value: 'finished', label: 'Finished' },
];

/** The orders the listing offers, in the order shown. */
export const customReviewSortChoices: ReadonlyArray<{
    value: CustomReviewSortValue;
    label: string;
}> = [
    { value: 'recently_attempted', label: 'Most recently attempted' },
    { value: 'name', label: 'Name (A to Z)' },
    { value: 'created', label: 'Created (newest first)' },
];

/** A fresh state: no search or statuses, and the usual order. */
export function defaultCustomReviewFilters(): CustomReviewFiltersState {
    return { q: '', statuses: [], sort: 'recently_attempted' };
}

/**
 * Keep a state safe to send and to show. Values this app does not know fall
 * back to their default, and statuses always follow the filter's own order.
 */
export function normalizeCustomReviewFilters(
    filters: Partial<CustomReviewFiltersState> | null | undefined,
): CustomReviewFiltersState {
    if (!filters) {
        return defaultCustomReviewFilters();
    }

    return {
        q: normalizeSearch(filters.q),
        statuses: customReviewStatusChoices
            .map((choice) => choice.value)
            .filter((status) => filters.statuses?.includes(status)),
        sort: customReviewSortChoices.some(
            (choice) => choice.value === filters.sort,
        )
            ? (filters.sort as CustomReviewSortValue)
            : 'recently_attempted',
    };
}

/** A stable copy, so a component can pass a state around without sharing it. */
export function copyCustomReviewFilters(
    filters: CustomReviewFiltersState,
): CustomReviewFiltersState {
    return { ...filters, statuses: [...filters.statuses] };
}

/** Whether anything narrows the list. Sorting never counts as a filter. */
export function hasCustomReviewFilters(
    filters: CustomReviewFiltersState,
): boolean {
    return filters.q !== '' || filters.statuses.length > 0;
}

/** Whether a search is narrowing the list. */
export function hasCustomReviewSearch(
    filters: CustomReviewFiltersState,
): boolean {
    return filters.q !== '';
}

/** Whether a status chip is on. */
export function hasCustomReviewStatuses(
    filters: CustomReviewFiltersState,
): boolean {
    return filters.statuses.length > 0;
}

export function withCustomReviewSearch(
    filters: CustomReviewFiltersState,
    search: string,
): CustomReviewFiltersState {
    return { ...copyCustomReviewFilters(filters), q: normalizeSearch(search) };
}

/** Clear only the search, keeping every status and the chosen order. */
export function clearCustomReviewSearch(
    filters: CustomReviewFiltersState,
): CustomReviewFiltersState {
    return withCustomReviewSearch(filters, '');
}

export function withCustomReviewStatuses(
    filters: CustomReviewFiltersState,
    statuses: CustomReviewStatusValue[],
): CustomReviewFiltersState {
    return normalizeCustomReviewFilters({
        ...copyCustomReviewFilters(filters),
        statuses,
    });
}

/** Add or remove one status, leaving the search and the others untouched. */
export function toggleCustomReviewStatus(
    filters: CustomReviewFiltersState,
    status: CustomReviewStatusValue,
): CustomReviewFiltersState {
    const statuses = filters.statuses.includes(status)
        ? filters.statuses.filter((chosen) => chosen !== status)
        : [...filters.statuses, status];

    return withCustomReviewStatuses(filters, statuses);
}

/** Clear all statuses only, keeping the search and the chosen order. */
export function clearCustomReviewStatuses(
    filters: CustomReviewFiltersState,
): CustomReviewFiltersState {
    return withCustomReviewStatuses(filters, []);
}

export function withCustomReviewSort(
    filters: CustomReviewFiltersState,
    sort: CustomReviewSortValue,
): CustomReviewFiltersState {
    return { ...copyCustomReviewFilters(filters), sort };
}

/**
 * The address for one listing state. Defaults are left out, so an untouched
 * listing keeps a short address. `per_page` travels in the address too, so a
 * refresh restores as many loaded cards as it can.
 */
export function customReviewFiltersToQuery(
    filters: CustomReviewFiltersState,
    perPage: number,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.q !== '') {
        query.q = filters.q;
    }

    if (filters.statuses.length > 0) {
        query.status = filters.statuses.join(',');
    }

    if (filters.sort !== 'recently_attempted') {
        query.sort = filters.sort;
    }

    if (perPage !== CUSTOM_REVIEW_PAGE_SIZE) {
        query.per_page = String(
            Math.min(
                CUSTOM_REVIEW_MAX_PAGE_SIZE,
                Math.max(1, Math.floor(perPage)),
            ),
        );
    }

    return query;
}

export interface CustomReviewFilterChip {
    kind: 'status';
    key: string;
    label: string;
    value: CustomReviewStatusValue;
}

/** One chip per chosen status, in the filter's own order. */
export function customReviewFilterChips(
    filters: CustomReviewFiltersState,
): CustomReviewFilterChip[] {
    const chips: CustomReviewFilterChip[] = [];

    for (const choice of customReviewStatusChoices) {
        if (filters.statuses.includes(choice.value)) {
            chips.push({
                kind: 'status',
                key: `status:${choice.value}`,
                label: choice.label,
                value: choice.value,
            });
        }
    }

    return chips;
}

/** Remove one chip's status, leaving every other choice untouched. */
export function removeCustomReviewFilterChip(
    filters: CustomReviewFiltersState,
    chip: CustomReviewFilterChip,
): CustomReviewFiltersState {
    return withCustomReviewStatuses(
        filters,
        filters.statuses.filter((status) => status !== chip.value),
    );
}

/**
 * The count shown in the header: "3 of 12 custom reviews" when narrowed, and
 * "12 custom reviews" otherwise.
 */
export function customReviewCountLabel(
    total: number,
    allTotal: number,
    narrowed: boolean,
): string {
    if (narrowed) {
        return `${total} of ${allTotal} ${allTotal === 1 ? 'custom review' : 'custom reviews'}`;
    }

    return `${allTotal} ${allTotal === 1 ? 'custom review' : 'custom reviews'}`;
}

/** What a screen reader hears once the list has settled. */
export function customReviewResultAnnouncement(
    total: number,
    narrowed: boolean,
): string {
    if (narrowed) {
        if (total === 0) {
            return 'No custom reviews match.';
        }

        return `${total} ${total === 1 ? 'custom review matches' : 'custom reviews match'}.`;
    }

    return `${total} ${total === 1 ? 'custom review' : 'custom reviews'}.`;
}

export function customReviewSortLabel(sort: CustomReviewSortValue): string {
    return (
        customReviewSortChoices.find((choice) => choice.value === sort)
            ?.label ?? 'Most recently attempted'
    );
}

/** A review's created date, in the learner's own time: "Created 12 Oct". */
export function customReviewCreatedLabel(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const date = shortDate(iso);

    return date === '' ? '' : `Created ${date}`;
}

/** Whether a value looks like a listing page this app can use. */
export function isCustomReviewsPage(
    value: unknown,
): value is CustomReviewsPageData {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const page = value as Record<string, unknown>;

    return (
        Array.isArray(page.reviews) &&
        (typeof page.next_cursor === 'string' || page.next_cursor === null) &&
        typeof page.has_more === 'boolean' &&
        typeof page.total === 'number' &&
        typeof page.all_total === 'number' &&
        typeof page.per_page === 'number' &&
        isCustomReviewFilters(page.filters)
    );
}

function isCustomReviewFilters(
    value: unknown,
): value is CustomReviewFiltersState {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const filters = value as Record<string, unknown>;

    return (
        typeof filters.q === 'string' &&
        Array.isArray(filters.statuses) &&
        typeof filters.sort === 'string'
    );
}

/** Adds newly loaded cards without ever showing one twice. */
export function mergeCustomReviewCards(
    existing: CustomReviewCardData[],
    incoming: CustomReviewCardData[],
): CustomReviewCardData[] {
    const byId = new Map<number, CustomReviewCardData>();

    for (const review of existing) {
        byId.set(review.id, review);
    }

    for (const review of incoming) {
        byId.set(review.id, review);
    }

    return [...byId.values()];
}
