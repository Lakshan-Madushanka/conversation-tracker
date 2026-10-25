import { ref } from 'vue';
import { jsonRequest } from '@/lib/http';
import {
    isLibraryPage,
    mergeSessionCards,
    type LibraryPage,
    type LibrarySnapshot,
} from '@/lib/library';
import { index as sessionsIndex } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

/**
 * The Library list on screen: the cards loaded so far, where the next page
 * starts, and the states the page needs to explain what is happening.
 */
export function useLibrarySessions(initial: LibraryPage) {
    const sessions = ref<SessionCardData[]>([...initial.sessions]);
    const nextCursor = ref<string | null>(initial.next_cursor);
    const hasMore = ref(initial.has_more);
    const total = ref(initial.total);
    const loading = ref(false);
    const error = ref(false);

    /**
     * Asks the server for one page. The cursor is sent back exactly as it was
     * given; `perPage` is only used when a refresh needs a page bigger than
     * the Library's usual one.
     */
    async function requestPage(
        cursor: string | null,
        perPage?: number,
    ): Promise<LibraryPage | null> {
        const query: Record<string, string | number> = {};

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

        loading.value = true;
        error.value = false;

        const page = await requestPage(nextCursor.value);

        loading.value = false;

        if (page === null) {
            error.value = true;

            return false;
        }

        sessions.value = mergeSessionCards(sessions.value, page.sessions);
        nextCursor.value = page.next_cursor;
        hasMore.value = page.has_more;
        total.value = page.total;

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

        const pages: LibraryPage[] = [];
        let cursor: string | null = null;
        let loaded = 0;

        while (loaded < wanted) {
            const page = await requestPage(
                cursor,
                Math.min(100, wanted - loaded),
            );

            if (page === null) {
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
    }

    /** Puts a saved list back exactly as the learner left it. */
    function restore(snapshot: LibrarySnapshot): void {
        sessions.value = [...snapshot.sessions];
        nextCursor.value = snapshot.nextCursor;
        hasMore.value = snapshot.hasMore;
        total.value = snapshot.total;
    }

    return {
        sessions,
        nextCursor,
        hasMore,
        total,
        loading,
        error,
        loadMore,
        refresh,
        restore,
    };
}
