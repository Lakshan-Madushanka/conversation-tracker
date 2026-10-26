import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { jsonRequest } from '@/lib/http';
import {
    browserTimezone,
    copyLibraryFilters,
    clearLibraryFilters,
    filtersToQuery,
    normalizeLibraryFilters,
    withSearch,
    withSort,
    withView,
    type LibraryFilters,
    type LibrarySort,
    type LibraryView,
} from '@/lib/library-filters';
import {
    isLibraryPage,
    mergeSessionCards,
    type LibraryPage,
    type LibrarySnapshot,
} from '@/lib/library';
import { index as sessionsIndex } from '@/routes/sessions';
import type { SessionCardData, TopicSummary } from '@/types';

/** The page props a filter change asks the server to send back. */
const FILTER_PROPS = [
    'sessions',
    'next_cursor',
    'has_more',
    'total',
    'all_total',
    'archived_total',
    'filters',
    'topics',
    'timezone',
];

/**
 * The Library list on screen: the cards loaded so far, the filters and order
 * in force, and the states the page needs to explain what is happening.
 *
 * Filter changes travel through Inertia, so the address bar, the browser's
 * Back button and a refresh all keep the learner's place. Loading more stays a
 * small JSON request that carries the same filters.
 */
export function useLibrarySessions(initial: LibraryPage) {
    const sessions = ref<SessionCardData[]>([...initial.sessions]);
    const nextCursor = ref<string | null>(initial.next_cursor);
    const hasMore = ref(initial.has_more);
    const total = ref(initial.total);
    const allTotal = ref(initial.all_total);
    const archivedTotal = ref(initial.archived_total);
    const filters = ref<LibraryFilters>(
        normalizeLibraryFilters(initial.filters),
    );
    const topics = ref<TopicSummary[]>([...initial.topics]);
    // "Today" must mean the learner's today. The browser knows their zone; the
    // server prop is only a fallback for an environment without `Intl`.
    const timezone = ref(browserTimezone() || initial.timezone);

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

    /**
     * Asks the server for one page. The cursor is sent back exactly as it was
     * given; `perPage` is only used when a refresh needs a page bigger than
     * the Library's usual one.
     */
    async function requestPage(
        cursor: string | null,
        perPage: number | undefined,
        activeFilters: LibraryFilters,
        activeTimezone: string,
    ): Promise<LibraryPage | null> {
        const query: Record<string, string | number> = {
            ...filtersToQuery(activeFilters, activeTimezone),
        };

        if (cursor !== null) {
            query.cursor = cursor;
        }

        if (perPage !== undefined) {
            query.per_page = perPage;
        }

        try {
            const response = await jsonRequest(sessionsIndex.url({ query }));

            if (!response.ok) {
                return null;
            }

            const data: unknown = await response.json();

            return isLibraryPage(data) ? data : null;
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
        const activeFilters = copyLibraryFilters(filters.value);
        const activeTimezone = timezone.value;

        loading.value = true;
        error.value = false;

        const page = await requestPage(
            nextCursor.value,
            undefined,
            activeFilters,
            activeTimezone,
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

        sessions.value = mergeSessionCards(sessions.value, page.sessions);
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;
        allTotal.value = page.all_total;
        archivedTotal.value = page.archived_total;

        return true;
    }

    /**
     * Refreshes every card already loaded, not just the first page, so an
     * edit made while away shows on the card wherever it sits in the list.
     *
     * It asks for as many sessions as are on screen, a page of up to 100 at a
     * time, and swaps the list only once every request has succeeded. If the
     * network drops, the saved list stays exactly as it was.
     */
    async function refresh(): Promise<void> {
        const wanted = Math.max(sessions.value.length, initial.sessions.length);

        if (wanted === 0) {
            return;
        }

        const run = generation;
        const activeFilters = copyLibraryFilters(filters.value);
        const activeTimezone = timezone.value;

        const pages: LibraryPage[] = [];
        let cursor: string | null = null;
        let loaded = 0;

        while (loaded < wanted) {
            const page = await requestPage(
                cursor,
                Math.min(100, wanted - loaded),
                activeFilters,
                activeTimezone,
            );

            if (page === null || run !== generation) {
                return;
            }

            pages.push(page);
            loaded += page.sessions.length;

            if (!page.has_more || page.next_cursor === null) {
                break;
            }

            cursor = page.next_cursor;
        }

        const lastPage = pages[pages.length - 1];

        if (lastPage === undefined) {
            return;
        }

        let refreshed: SessionCardData[] = [];

        for (const page of pages) {
            refreshed = mergeSessionCards(refreshed, page.sessions);
        }

        sessions.value = refreshed;
        nextCursor.value = lastPage.next_cursor;
        hasMore.value = lastPage.has_more;
        total.value = lastPage.total;
        allTotal.value = lastPage.all_total;
        archivedTotal.value = lastPage.archived_total;
        topics.value = [...lastPage.topics];
        timezone.value = lastPage.timezone;
    }

    /**
     * Ask for a new filter state. The address changes by replacing the current
     * history entry, so browsing filters never fills the Back button with
     * every tick of a control.
     */
    function applyFilters(next: LibraryFilters): void {
        requestFilters(normalizeLibraryFilters(next));
    }

    /** Try the same filters again after a calm error. */
    function retryFilters(): void {
        requestFilters(copyLibraryFilters(filters.value), true);
    }

    function setSort(sort: LibrarySort): void {
        applyFilters(withSort(filters.value, sort));
    }

    /** Switch between the Library and the Archived view. */
    function setView(view: LibraryView): void {
        applyFilters(withView(filters.value, view));
    }

    function clearFilters(): void {
        applyFilters(clearLibraryFilters(filters.value));
    }

    /** Replace the search, keeping every filter and the chosen order. */
    function setSearch(search: string): void {
        applyFilters(withSearch(filters.value, search));
    }

    /** Clear only the search, keeping every filter and the chosen order. */
    function clearSearch(): void {
        applyFilters(withSearch(filters.value, ''));
    }

    function requestFilters(next: LibraryFilters, force = false): void {
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
            sessionsIndex.url({
                query: filtersToQuery(next, timezone.value),
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

                    if (isLibraryPage(page.props)) {
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

    function syncPage(page: LibraryPage): void {
        sessions.value = [...page.sessions];
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;
        allTotal.value = page.all_total;
        archivedTotal.value = page.archived_total;
        filters.value = normalizeLibraryFilters(page.filters);
        topics.value = [...page.topics];
        timezone.value = page.timezone;
        filterError.value = false;
        revision.value += 1;
    }

    /** Puts a saved list back exactly as the learner left it. */
    function restore(snapshot: LibrarySnapshot): void {
        sessions.value = [...snapshot.sessions];
        nextCursor.value = snapshot.nextCursor;
        hasMore.value = snapshot.hasMore;
        total.value = snapshot.total;
        allTotal.value = snapshot.allTotal;
        archivedTotal.value = snapshot.archivedTotal;
        filters.value = normalizeLibraryFilters(snapshot.filters);
        timezone.value = snapshot.timezone;
    }

    return {
        sessions,
        nextCursor,
        hasMore,
        total,
        allTotal,
        archivedTotal,
        filters,
        topics,
        timezone,
        loading,
        error,
        updating,
        filterError,
        revision,
        loadMore,
        refresh,
        restore,
        applyFilters,
        retryFilters,
        setSort,
        setView,
        clearFilters,
        setSearch,
        clearSearch,
    };
}

function sameFilters(left: LibraryFilters, right: LibraryFilters): boolean {
    return JSON.stringify(left) === JSON.stringify(right);
}
