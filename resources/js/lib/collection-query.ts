/**
 * Reading and writing the Collections page's own address: the open tab and
 * the opened item live in the query string (`/collections?tab=phrases&item=…`),
 * so a refresh, the back button or a shared link opens the same view.
 *
 * The reader is a pure function of the url, kept free of Inertia so it can be
 * tested without an app.
 */

import {
    collectionFiltersToQuery,
    normalizeCollectionFilters,
} from '@/lib/collection-filters';
import { index as collectionsIndex } from '@/routes/collections';
import type {
    CollectionFilters,
    CollectionSort,
    CollectionStatusFilter,
    CollectionTabKey,
} from '@/types';

/** The tab shown when the url asks for one this page does not have. */
const FALLBACK_TAB: CollectionTabKey = 'vocabulary';

/** Every tab the Collections page knows about. */
const TAB_KEYS: CollectionTabKey[] = [
    'vocabulary',
    'phrases',
    'grammar',
    'mistakes',
];

/**
 * The tab and opened item a Collections url asks for. An unknown tab (a
 * hand-edited url, or one left by an older version) falls back to vocabulary
 * rather than breaking the page. A blank item means no panel is open. The
 * `?word=` the page once wrote opens nothing: the panel now opens by its
 * own key in `item`.
 */
export function readCollectionQuery(url: string): {
    tab: CollectionTabKey;
    item: string | null;
} {
    const [, query = ''] = url.split('?');
    const parsed = new URLSearchParams(query.split('#')[0]);

    const tab = parsed.get('tab');
    const item = parsed.get('item');

    return {
        tab: isTabKey(tab) ? tab : FALLBACK_TAB,
        item: item === null || item === '' ? null : item,
    };
}

/**
 * The search, filters and order a Collections url asks for. Unknown values
 * are ignored quietly, so a stale bookmark opens a useful list.
 */
export function readCollectionFilters(
    url: string,
    tab: CollectionTabKey,
): CollectionFilters {
    const [, query = ''] = url.split('?');
    const parsed = new URLSearchParams(query.split('#')[0]);

    return normalizeCollectionFilters(
        {
            q: parsed.get('q') ?? '',
            starred: isTruthy(parsed.get('starred')),
            status:
                (parsed.get('status') as CollectionStatusFilter | null) ??
                undefined,
            attention: isTruthy(parsed.get('attention')),
            topics: topicIds(parsed.get('topics')),
            sort: (parsed.get('sort') as CollectionSort | null) ?? undefined,
        },
        tab,
    );
}

/**
 * The address for one tab, optionally with an opened panel and its search and
 * filters. The item is left out when there is none, so closing the panel has
 * no empty `item=`. Defaults are left out too, so an untouched collection
 * keeps the short address it always had.
 */
export function collectionUrl(
    tab: CollectionTabKey,
    item: string | null = null,
    filters: CollectionFilters | null = null,
): string {
    const query: Record<string, string> = {
        tab,
        ...(filters === null ? {} : collectionFiltersToQuery(filters, tab)),
    };

    if (item !== null) {
        query.item = item;
    }

    return collectionsIndex.url({ query });
}

function isTabKey(value: string | null): value is CollectionTabKey {
    return value !== null && (TAB_KEYS as string[]).includes(value);
}

function isTruthy(value: string | null): boolean {
    return value === '1' || value === 'true' || value === 'on';
}

/** `1,2` from the address, with anything a topic id cannot be left out. */
function topicIds(value: string | null): number[] {
    if (value === null || value === '') {
        return [];
    }

    return value
        .split(',')
        .map((piece) => Number(piece.trim()))
        .filter((id) => Number.isInteger(id) && id > 0);
}
