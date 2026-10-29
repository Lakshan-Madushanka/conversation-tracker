import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { collectionUrl } from '@/lib/collection-query';
import {
    isCollectionOpenedItem,
    isCollectionItemsPage,
    isCollectionPage,
    markCollectionRestore,
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
    CollectionTabKey,
} from '@/types';

/** The page props a tab change asks the server to send back. */
const LIST_PROPS = [
    'tab',
    'tabs',
    'items',
    'next_cursor',
    'has_more',
    'total',
    'has_finished_sessions',
];

/** The page props that open or close the item panel. */
const PANEL_PROPS = ['open_item', 'open_item_missing'];

/**
 * The collection on screen: the items loaded so far, the open tab and the
 * panel, plus the states the page needs to explain loading and failure.
 *
 * Tab changes and the panel travel through Inertia, so the address bar, the
 * browser's Back button and a refresh keep the learner's place. Loading more
 * is a small JSON request that carries the same tab.
 */
export function useCollection(initial: CollectionPage) {
    const tab = ref<CollectionTabKey>(initial.tab);
    const items = ref<CollectionOpenedItem[]>([...initial.items]);
    const nextCursor = ref<string | null>(initial.next_cursor);
    const hasMore = ref(initial.has_more);
    const total = ref(initial.total);
    const hasFinishedSessions = ref(initial.has_finished_sessions);
    const openItem = ref<CollectionOpenedItem | null>(initial.open_item);
    const openItemMissing = ref(initial.open_item_missing);

    const loading = ref(false);
    const error = ref(false);
    const updating = ref(false);

    /**
     * Guards against a slow request for an old tab replacing a newer one.
     * Only the latest request may change the list.
     */
    let generation = 0;
    /**
     * Asks the server for one page of a tab. The cursor is sent back exactly
     * as it was given, and `perPage` is only used when restoring a longer list.
     */
    async function requestPage(
        activeTab: CollectionTabKey,
        cursor: string | null,
        perPage?: number,
    ): Promise<CollectionItemsPage | null> {
        const query: Record<string, string | number> = { tab: activeTab };

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

        loading.value = true;
        error.value = false;

        const page = await requestPage(activeTab, nextCursor.value);

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
        const pages: CollectionItemsPage[] = [];
        let cursor: string | null = null;
        let loaded = 0;

        while (loaded < wanted) {
            const page = await requestPage(
                activeTab,
                cursor,
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
     * Ask for another tab. The address changes with a push, so the Back button
     * returns to the previous tab. Any open item belongs to the old tab and
     * closes with it.
     */
    function setTab(next: CollectionTabKey): void {
        if (next === tab.value) {
            return;
        }

        const run = ++generation;

        tab.value = next;
        items.value = [];
        nextCursor.value = null;
        hasMore.value = false;
        total.value = 0;
        openItem.value = null;
        openItemMissing.value = false;
        loading.value = false;
        updating.value = true;
        error.value = false;

        router.get(
            collectionUrl(next),
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
            collectionUrl(tab.value, item.key),
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
            collectionUrl(tab.value),
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
        markCollectionRestore();
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
        tab.value = saved.tab;
        items.value = [...saved.items];
        nextCursor.value = saved.nextCursor;
        hasMore.value = saved.hasMore;
        total.value = saved.total;
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
        };
    }

    function syncPage(page: CollectionPage): void {
        tab.value = page.tab;
        items.value = [...page.items];
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;
        hasFinishedSessions.value = page.has_finished_sessions;
        error.value = false;
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
        hasFinishedSessions,
        openItem,
        openItemMissing,
        loading,
        error,
        updating,
        loadMore,
        refresh,
        setTab,
        openItemPanel,
        closeItemPanel,
        dismissMissingItem,
        rememberForReturn,
        restore,
        syncFromPage,
    };
}
