import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    clearCollectionFilters,
    clearCollectionSearch,
    collectionFiltersToQuery,
    copyCollectionFilters,
    defaultCollectionFilters,
    normalizeCollectionFilters,
    withCollectionSearch,
    withCollectionSort,
    type CollectionFilters,
} from '@/lib/collection-filters';
import { collectionUrl } from '@/lib/collection-query';
import {
    isCollectionOpenedItem,
    isCollectionItemsPage,
    isCollectionPage,
    mergeCollectionItems,
    saveCollectionSnapshot,
    type CollectionSnapshot,
} from '@/lib/collections';
import { jsonRequest } from '@/lib/http';
import { index as collectionsIndex } from '@/routes/collections';
import type {
    CollectionItemsPage,
    CollectionOpenedItem,
    CollectionPage,
    CollectionSort,
    CollectionTabKey,
    CollectionTopicOption,
} from '@/types';

/** The page props a tab, search or filter change asks the server to send back. */
const LIST_PROPS = [
    'tab',
    'tabs',
    'items',
    'next_cursor',
    'has_more',
    'total',
    'all_total',
    'attention_count',
    'topic_options',
    'filters',
    'has_finished_sessions',
];

/** The page props that open or close the item panel. */
const PANEL_PROPS = ['open_item', 'open_item_missing'];

/**
 * The collection on screen: the items loaded so far, the search, filters and
 * order in force, the open tab and the panel, plus the states the page needs
 * to explain loading and failure.
 *
 * Search, filter, sort, tab and panel changes travel through Inertia, so the
 * address bar, the browser's Back button and a refresh keep the learner's
 * place. Loading more is a small JSON request that carries the same state.
 */
export function useCollection(initial: CollectionPage) {
    const tab = ref<CollectionTabKey>(initial.tab);
    const items = ref<CollectionOpenedItem[]>([...initial.items]);
    const nextCursor = ref<string | null>(initial.next_cursor);
    const hasMore = ref(initial.has_more);
    const total = ref(initial.total);
    const allTotal = ref(initial.all_total ?? initial.total);
    const attentionCount = ref(initial.attention_count ?? 0);
    const filters = ref<CollectionFilters>(
        normalizeCollectionFilters(initial.filters, initial.tab),
    );
    const topicOptions = ref<CollectionTopicOption[]>([
        ...(initial.topic_options ?? []),
    ]);
    const hasFinishedSessions = ref(initial.has_finished_sessions);
    const openItem = ref<CollectionOpenedItem | null>(initial.open_item);
    const openItemMissing = ref(initial.open_item_missing);

    const loading = ref(false);
    const error = ref(false);
    const filterError = ref(false);
    const updating = ref(false);

    /**
     * Guards against a slow request for an old state replacing a newer one.
     * Only the latest request may change the list.
     */
    let generation = 0;

    /**
     * Asks the server for one page of a tab with the same search and filters.
     * The cursor is sent back exactly as it was given, and `perPage` is only
     * used when restoring a longer list.
     */
    async function requestPage(
        activeTab: CollectionTabKey,
        cursor: string | null,
        activeFilters: CollectionFilters,
        perPage?: number,
    ): Promise<CollectionItemsPage | null> {
        const query: Record<string, string | number> = {
            tab: activeTab,
            ...collectionFiltersToQuery(activeFilters, activeTab),
        };

        if (cursor !== null) {
            query.cursor = cursor;
        }

        if (perPage !== undefined) {
            query.per_page = perPage;
        }

        try {
            const response = await jsonRequest(collectionsIndex.url({ query }));

            if (!response.ok) {
                return null;
            }

            const data: unknown = await response.json();

            return isCollectionItemsPage(data) ? data : null;
        } catch {
            return null;
        }
    }

    /** Loads the next page, keeping every item already shown. */
    async function loadMore(): Promise<boolean> {
        if (loading.value || !hasMore.value || nextCursor.value === null) {
            return false;
        }

        const run = generation;
        const activeTab = tab.value;
        const activeFilters = copyCollectionFilters(filters.value);

        loading.value = true;
        error.value = false;

        const page = await requestPage(
            activeTab,
            nextCursor.value,
            activeFilters,
        );

        if (run !== generation) {
            return false;
        }

        loading.value = false;

        if (page === null) {
            error.value = true;

            return false;
        }

        items.value = mergeCollectionItems(items.value, page.items);
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;

        return true;
    }

    /**
     * Refreshes every item already loaded after a trip to a session, so an
     * edit, archive or delete made there is reflected on return. If the
     * network drops, the restored list stays exactly as it was.
     */
    async function refresh(): Promise<void> {
        const wanted = Math.max(items.value.length, initial.items.length);

        if (wanted === 0) {
            return;
        }

        const run = generation;
        const activeTab = tab.value;
        const activeFilters = copyCollectionFilters(filters.value);
        const pages: CollectionItemsPage[] = [];
        let cursor: string | null = null;
        let loaded = 0;

        while (loaded < wanted) {
            const page = await requestPage(
                activeTab,
                cursor,
                activeFilters,
                Math.min(100, wanted - loaded),
            );

            if (page === null || run !== generation) {
                return;
            }

            pages.push(page);
            loaded += page.items.length;

            if (!page.has_more || page.next_cursor === null) {
                break;
            }

            cursor = page.next_cursor;
        }

        const lastPage = pages[pages.length - 1];

        if (lastPage === undefined) {
            return;
        }

        let refreshed: CollectionOpenedItem[] = [];

        for (const page of pages) {
            refreshed = mergeCollectionItems(refreshed, page.items);
        }

        items.value = refreshed;
        nextCursor.value = lastPage.next_cursor;
        hasMore.value = lastPage.has_more;
        total.value = lastPage.total;
    }

    /**
     * Ask for another tab. A new tab starts with its own quiet defaults: no
     * search and no filters. The address changes with a push, so the Back
     * button returns to the previous tab.
     */
    function setTab(next: CollectionTabKey): void {
        if (next === tab.value) {
            return;
        }

        const run = ++generation;
        const defaults = defaultCollectionFilters(next);

        tab.value = next;
        filters.value = defaults;
        topicOptions.value = [];
        items.value = [];
        nextCursor.value = null;
        hasMore.value = false;
        total.value = 0;
        allTotal.value = 0;
        attentionCount.value = 0;
        openItem.value = null;
        openItemMissing.value = false;
        loading.value = false;
        updating.value = true;
        error.value = false;
        filterError.value = false;

        router.get(
            collectionUrl(next, null, defaults),
            {},
            {
                preserveState: true,
                preserveScroll: false,
                only: LIST_PROPS,
                onSuccess: (page) => {
                    if (run !== generation) {
                        return;
                    }

                    if (!isCollectionPage(page.props)) {
                        error.value = true;

                        return;
                    }

                    syncPage(page.props);
                },
                onError: () => {
                    if (run === generation) {
                        error.value = true;
                    }
                },
                onHttpException: () => {
                    if (run === generation) {
                        error.value = true;
                    }
                },
                onNetworkError: () => {
                    if (run === generation) {
                        error.value = true;
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

    /** Ask for a new search, filter or order state. */
    function applyFilters(next: CollectionFilters): void {
        requestFilters(normalizeCollectionFilters(next, tab.value));
    }

    /** Try the same state again after a calm error. */
    function retryFilters(): void {
        requestFilters(copyCollectionFilters(filters.value), true);
    }

    /** Replace the search, keeping every filter and the chosen order. */
    function setSearch(search: string): void {
        applyFilters(withCollectionSearch(filters.value, search));
    }

    /** Clear only the search, keeping every filter and the chosen order. */
    function clearSearch(): void {
        applyFilters(clearCollectionSearch(filters.value));
    }

    /** Clear every filter, keeping the search and the chosen order. */
    function clearFilters(): void {
        applyFilters(clearCollectionFilters(filters.value));
    }

    /** Choose another order, keeping the search and every filter. */
    function setSort(sort: CollectionSort): void {
        applyFilters(withCollectionSort(filters.value, sort));
    }

    function requestFilters(next: CollectionFilters, force = false): void {
        if (!force && sameFilters(next, filters.value)) {
            return;
        }

        const run = ++generation;

        filters.value = next;
        updating.value = true;
        filterError.value = false;
        error.value = false;
        loading.value = false;
        openItemMissing.value = false;

        router.get(
            collectionUrl(tab.value, openItem.value?.key ?? null, next),
            {},
            {
                replace: true,
                preserveState: true,
                preserveScroll: true,
                only: LIST_PROPS,
                onSuccess: (page) => {
                    if (run !== generation) {
                        return;
                    }

                    if (!isCollectionPage(page.props)) {
                        filterError.value = true;

                        return;
                    }

                    syncPage(page.props);
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

    /**
     * Open an item's panel. The address gains `item`, with a push, so Back
     * closes the panel and leaves the loaded list untouched.
     */
    function openItemPanel(item: CollectionOpenedItem): void {
        if (openItem.value?.key === item.key) {
            return;
        }

        openItem.value = item;
        openItemMissing.value = false;

        router.get(
            collectionUrl(tab.value, item.key, filters.value),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: PANEL_PROPS,
                onSuccess: (page) => syncPanel(page.props),
                onError: () => {
                    openItem.value = null;
                },
                onHttpException: () => {
                    openItem.value = null;
                },
                onNetworkError: () => {
                    openItem.value = null;
                },
            },
        );
    }

    /** Close the panel by replacing the address, so no extra history is left. */
    function closeItemPanel(): void {
        if (openItem.value === null && !openItemMissing.value) {
            return;
        }

        openItem.value = null;
        openItemMissing.value = false;

        router.get(
            collectionUrl(tab.value, null, filters.value),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: PANEL_PROPS,
            },
        );
    }

    /** Dismiss the calm note about an item that is no longer there. */
    function dismissMissingItem(): void {
        closeItemPanel();
    }

    /**
     * Remember everything on screen just before the learner follows an item
     * back to its session. The marker makes the next visit restore it once.
     */
    function rememberForReturn(): void {
        saveCollectionSnapshot(snapshot());
    }

    /**
     * Put a saved list back, before the quiet refresh that makes it current.
     * The open item is taken from the address the learner returned to, so the
     * Back button can close a panel even when the saved list still names it.
     */
    function restore(
        saved: CollectionSnapshot,
        openItemKey: string | null = saved.openItemKey,
    ): void {
        generation += 1;
        loading.value = false;
        updating.value = false;
        error.value = false;
        filterError.value = false;
        tab.value = saved.tab;
        items.value = [...saved.items];
        nextCursor.value = saved.nextCursor;
        hasMore.value = saved.hasMore;
        total.value = saved.total;
        allTotal.value = saved.allTotal ?? saved.total;
        attentionCount.value = saved.attentionCount ?? 0;
        filters.value = normalizeCollectionFilters(saved.filters, saved.tab);
        topicOptions.value = [...(saved.topicOptions ?? [])];
        openItemMissing.value = false;
        openItem.value =
            openItemKey === null
                ? null
                : (saved.items.find((item) => item.key === openItemKey) ??
                  (initial.open_item?.key === openItemKey
                      ? initial.open_item
                      : null));
    }

    /**
     * Adopt a page the browser restored from history, such as the Back button
     * moving between tabs or closing an open item. The address decides whether
     * a panel should be open: a partial history entry can still carry the
     * item from the page it was left on.
     */
    function syncFromPage(
        page: CollectionPage,
        openItemKey: string | null = page.open_item?.key ?? null,
    ): void {
        generation += 1;
        loading.value = false;
        updating.value = false;
        filterError.value = false;
        syncPage(page);

        const found =
            openItemKey !== null && page.open_item?.key === openItemKey
                ? page.open_item
                : null;

        openItem.value = found;
        openItemMissing.value =
            openItemKey !== null && found === null
                ? page.open_item_missing
                : false;
    }

    function snapshot(): CollectionSnapshot {
        return {
            tab: tab.value,
            items: [...items.value],
            nextCursor: nextCursor.value,
            hasMore: hasMore.value,
            total: total.value,
            openItemKey: openItem.value?.key ?? null,
            scrollY: typeof window === 'undefined' ? 0 : window.scrollY,
            savedAt: Date.now(),
            filters: copyCollectionFilters(filters.value),
            topicOptions: [...topicOptions.value],
            allTotal: allTotal.value,
            attentionCount: attentionCount.value,
        };
    }

    function syncPage(page: CollectionPage): void {
        tab.value = page.tab;
        items.value = [...page.items];
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;
        allTotal.value = page.all_total ?? page.total;
        attentionCount.value = page.attention_count ?? attentionCount.value;
        topicOptions.value = [...(page.topic_options ?? topicOptions.value)];
        filters.value = normalizeCollectionFilters(page.filters, page.tab);
        hasFinishedSessions.value = page.has_finished_sessions;
        error.value = false;
        filterError.value = false;
    }

    function syncPanel(props: Record<string, unknown>): void {
        openItem.value = isCollectionOpenedItem(props.open_item)
            ? props.open_item
            : null;
        openItemMissing.value = props.open_item_missing === true;
    }

    return {
        tab,
        items,
        nextCursor,
        hasMore,
        total,
        allTotal,
        attentionCount,
        filters,
        topicOptions,
        hasFinishedSessions,
        openItem,
        openItemMissing,
        loading,
        error,
        filterError,
        updating,
        loadMore,
        refresh,
        setTab,
        applyFilters,
        retryFilters,
        setSearch,
        clearSearch,
        clearFilters,
        setSort,
        openItemPanel,
        closeItemPanel,
        dismissMissingItem,
        rememberForReturn,
        restore,
        syncFromPage,
    };
}

function sameFilters(
    left: CollectionFilters,
    right: CollectionFilters,
): boolean {
    return JSON.stringify(left) === JSON.stringify(right);
}
