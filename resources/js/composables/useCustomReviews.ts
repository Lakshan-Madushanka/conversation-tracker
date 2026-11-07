import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    CUSTOM_REVIEW_MAX_PAGE_SIZE,
    CUSTOM_REVIEW_PAGE_SIZE,
    clearCustomReviewSearch,
    clearCustomReviewStatuses,
    copyCustomReviewFilters,
    customReviewFiltersToQuery,
    isCustomReviewsPage,
    mergeCustomReviewCards,
    normalizeCustomReviewFilters,
    removeCustomReviewFilterChip,
    toggleCustomReviewStatus,
    withCustomReviewSearch,
    withCustomReviewSort,
    withCustomReviewStatuses,
    type CustomReviewFilterChip,
} from '@/lib/custom-review-listing';
import { jsonRequest } from '@/lib/http';
import { index as customReviewsIndex } from '@/routes/customReviews';
import type {
    CustomReviewCardData,
    CustomReviewFiltersState,
    CustomReviewSortValue,
    CustomReviewStatusValue,
    CustomReviewsPageData,
} from '@/types';

/** The page props a filter change asks the server to send back. */
const FILTER_PROPS = [
    'reviews',
    'next_cursor',
    'has_more',
    'total',
    'all_total',
    'per_page',
    'filters',
];

/**
 * The Custom reviews list on screen: the cards loaded so far, the search,
 * statuses and order in force, and the states the page explains.
 *
 * Filter changes travel through Inertia, so the address bar, the browser's
 * Back button and a refresh all keep the learner's place. Loading more stays a
 * small JSON request carrying the same filters.
 */
export function useCustomReviews(initial: CustomReviewsPageData) {
    const reviews = ref<CustomReviewCardData[]>([...initial.reviews]);
    const nextCursor = ref<string | null>(initial.next_cursor);
    const hasMore = ref(initial.has_more);
    const total = ref(initial.total);
    const allTotal = ref(initial.all_total);
    const perPage = ref(
        Math.min(CUSTOM_REVIEW_MAX_PAGE_SIZE, Math.max(1, initial.per_page)),
    );
    const filters = ref<CustomReviewFiltersState>(
        normalizeCustomReviewFilters(initial.filters),
    );

    const loading = ref(false);
    const error = ref(false);
    const updating = ref(false);
    const filterError = ref(false);

    /** Bumped whenever a fresh list replaces the old one, for announcements. */
    const revision = ref(0);

    /**
     * Guards against a slow request for an old filter state replacing a newer
     * one. Only the latest request may change the list.
     */
    let generation = 0;

    /** Asks the server for one page and hands back the shape it sent. */
    async function requestPage(
        cursor: string | null,
        requestedPerPage: number,
        activeFilters: CustomReviewFiltersState,
    ): Promise<CustomReviewsPageData | null> {
        const query: Record<string, string> = customReviewFiltersToQuery(
            activeFilters,
            requestedPerPage,
        );

        if (cursor !== null) {
            query.cursor = cursor;
        }

        try {
            const response = await jsonRequest(
                customReviewsIndex.url({ query }),
            );

            if (!response.ok) {
                return null;
            }

            const data: unknown = await response.json();

            return isCustomReviewsPage(data) ? data : null;
        } catch {
            return null;
        }
    }

    /** Loads the next page, keeping every card that is already shown. */
    async function loadMore(): Promise<boolean> {
        if (loading.value || !hasMore.value || nextCursor.value === null) {
            return false;
        }

        const run = generation;
        const activeFilters = copyCustomReviewFilters(filters.value);

        loading.value = true;
        error.value = false;

        const page = await requestPage(
            nextCursor.value,
            CUSTOM_REVIEW_PAGE_SIZE,
            activeFilters,
        );

        // The filters changed while this page was on its way, so it no longer
        // belongs on screen.
        if (run !== generation) {
            return false;
        }

        loading.value = false;

        if (page === null) {
            error.value = true;

            return false;
        }

        reviews.value = mergeCustomReviewCards(reviews.value, page.reviews);
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;
        allTotal.value = page.all_total;
        perPage.value = Math.min(
            CUSTOM_REVIEW_MAX_PAGE_SIZE,
            reviews.value.length,
        );
        replaceAddressPageSize(perPage.value);

        return true;
    }

    /**
     * Refreshes every card already loaded, not just the first page, so a
     * status change made on a card is reflected where the card sits, and an
     * active status filter stays truthful. If the network drops, the saved
     * list stays exactly as it was.
     */
    async function refresh(): Promise<void> {
        const wanted = Math.max(reviews.value.length, initial.reviews.length);

        if (wanted === 0) {
            return;
        }

        const run = generation;
        const activeFilters = copyCustomReviewFilters(filters.value);
        const pages: CustomReviewsPageData[] = [];
        let cursor: string | null = null;
        let loaded = 0;

        while (loaded < wanted) {
            const page = await requestPage(
                cursor,
                Math.min(CUSTOM_REVIEW_MAX_PAGE_SIZE, wanted - loaded),
                activeFilters,
            );

            if (page === null || run !== generation) {
                return;
            }

            pages.push(page);
            loaded += page.reviews.length;

            if (!page.has_more || page.next_cursor === null) {
                break;
            }

            cursor = page.next_cursor;
        }

        const lastPage = pages[pages.length - 1];

        if (lastPage === undefined) {
            return;
        }

        let refreshed: CustomReviewCardData[] = [];

        for (const page of pages) {
            refreshed = mergeCustomReviewCards(refreshed, page.reviews);
        }

        reviews.value = refreshed;
        nextCursor.value = lastPage.next_cursor;
        hasMore.value = lastPage.has_more;
        total.value = lastPage.total;
        allTotal.value = lastPage.all_total;
        filters.value = normalizeCustomReviewFilters(lastPage.filters);
    }

    /**
     * Ask for a new filter state. The address changes by replacing the current
     * history entry, so browsing filters never fills the Back button.
     */
    function applyFilters(next: CustomReviewFiltersState): void {
        requestFilters(normalizeCustomReviewFilters(next));
    }

    /** Try the same filters again after a calm error. */
    function retryFilters(): void {
        requestFilters(copyCustomReviewFilters(filters.value), true);
    }

    function setSort(sort: CustomReviewSortValue): void {
        applyFilters(withCustomReviewSort(filters.value, sort));
    }

    function setSearch(search: string): void {
        applyFilters(withCustomReviewSearch(filters.value, search));
    }

    /** Clear only the search, keeping every status and the chosen order. */
    function clearSearch(): void {
        applyFilters(clearCustomReviewSearch(filters.value));
    }

    /** Clear every status only, keeping the search and the chosen order. */
    function clearStatuses(): void {
        applyFilters(clearCustomReviewStatuses(filters.value));
    }

    function setStatuses(statuses: CustomReviewStatusValue[]): void {
        applyFilters(withCustomReviewStatuses(filters.value, statuses));
    }

    function toggleStatus(status: CustomReviewStatusValue): void {
        applyFilters(toggleCustomReviewStatus(filters.value, status));
    }

    function removeChip(chip: CustomReviewFilterChip): void {
        applyFilters(removeCustomReviewFilterChip(filters.value, chip));
    }

    function requestFilters(
        next: CustomReviewFiltersState,
        force = false,
    ): void {
        if (!force && sameFilters(next, filters.value)) {
            return;
        }

        const run = ++generation;

        filters.value = next;
        updating.value = true;
        filterError.value = false;
        error.value = false;
        loading.value = false;

        router.get(
            customReviewsIndex.url({
                query: customReviewFiltersToQuery(next, perPage.value),
            }),
            {},
            {
                replace: true,
                preserveState: true,
                preserveScroll: true,
                only: FILTER_PROPS,
                onSuccess: (page) => {
                    if (run !== generation) {
                        return;
                    }

                    if (isCustomReviewsPage(page.props)) {
                        syncPage(page.props);

                        return;
                    }

                    filterError.value = true;
                },
                onError: () => {
                    if (run === generation) {
                        filterError.value = true;
                    }
                },
                onHttpException: () => {
                    if (run === generation) {
                        filterError.value = true;
                    }
                },
                onNetworkError: () => {
                    if (run === generation) {
                        filterError.value = true;
                    }
                },
                onFinish: () => {
                    if (run === generation) {
                        updating.value = false;
                    }
                },
            },
        );
    }

    function syncPage(page: CustomReviewsPageData): void {
        reviews.value = [...page.reviews];
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;
        allTotal.value = page.all_total;
        perPage.value = Math.min(
            CUSTOM_REVIEW_MAX_PAGE_SIZE,
            Math.max(1, page.per_page),
        );
        filters.value = normalizeCustomReviewFilters(page.filters);
        filterError.value = false;
        revision.value += 1;
    }

    return {
        reviews,
        nextCursor,
        hasMore,
        total,
        allTotal,
        perPage,
        filters,
        loading,
        error,
        updating,
        filterError,
        revision,
        loadMore,
        refresh,
        applyFilters,
        retryFilters,
        setSort,
        setSearch,
        clearSearch,
        clearStatuses,
        setStatuses,
        toggleStatus,
        removeChip,
    };
}

/**
 * Keep the address in step with the cards on screen without a page visit.
 * `per_page` is the only thing Load more changes, and it never adds a history
 * entry.
 */
function replaceAddressPageSize(size: number): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    for (const key of ['q', 'status', 'sort', 'per_page', 'cursor']) {
        url.searchParams.delete(key);
    }

    const query = customReviewFiltersToQuery(
        normalizeCustomReviewFilters(readFiltersFromAddress()),
        size,
    );

    for (const [key, value] of Object.entries(query)) {
        url.searchParams.set(key, value);
    }

    window.history.replaceState(window.history.state, '', url);
}

/** The listing's own address, read back so a page size change keeps it. */
function readFiltersFromAddress(): Partial<CustomReviewFiltersState> {
    if (typeof window === 'undefined') {
        return {};
    }

    const params = new URLSearchParams(window.location.search);
    const statuses = (params.get('status') ?? '')
        .split(',')
        .filter((value) => value !== '');

    return {
        q: params.get('q') ?? '',
        statuses: statuses as CustomReviewStatusValue[],
        sort: (params.get('sort') ??
            'recently_attempted') as CustomReviewSortValue,
    };
}

function sameFilters(
    left: CustomReviewFiltersState,
    right: CustomReviewFiltersState,
): boolean {
    return JSON.stringify(left) === JSON.stringify(right);
}
