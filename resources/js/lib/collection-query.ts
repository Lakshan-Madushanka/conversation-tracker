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
    CollectionSource,
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
            source:
                (parsed.get('source') as CollectionSource | null) ?? undefined,
            notebook: positiveId(parsed.get('notebook')),
            sort: (parsed.get('sort') as CollectionSort | null) ?? undefined,
        },
        tab,
    );
}

/**
 * The review a Collections visit is topping up, when its own + Add items
 * opened the page. `eligible` says the list is narrowed to items the review
 * can use.
 */
export interface CollectionReviewQuery {
    review: number | null;
    eligible: boolean;
}

/**
 * The review an address asks for. Only a positive whole number counts, so a
 * stale or hand-edited address falls back to the ordinary Collections view.
 */
export function readCollectionReview(url: string): CollectionReviewQuery {
    const [, query = ''] = url.split('?');
    const parsed = new URLSearchParams(query.split('#')[0]);
    const review = Number(parsed.get('review'));

    return {
        review: Number.isInteger(review) && review > 0 ? review : null,
        eligible: isTruthy(parsed.get('eligible')),
    };
}

/**
 * The query pieces that keep a review context on every Collections address.
 * A page without a review writes nothing.
 */
export function collectionReviewQuery(
    review: CollectionReviewQuery | null | undefined,
): Record<string, string> {
    if (review === null || review === undefined || review.review === null) {
        return {};
    }

    return {
        review: String(review.review),
        ...(review.eligible ? { eligible: '1' } : {}),
    };
}

/**
 * The address for one tab, optionally with an opened panel, its search and
 * filters, and the review it is topping up. The item is left out when there
 * is none, so closing the panel has no empty `item=`. Defaults are left out
 * too, so an untouched collection keeps the short address it always had.
 */
export function collectionUrl(
    tab: CollectionTabKey,
    item: string | null = null,
    filters: CollectionFilters | null = null,
    review: CollectionReviewQuery | null = null,
): string {
    const query: Record<string, string> = {
        tab,
        ...(filters === null ? {} : collectionFiltersToQuery(filters, tab)),
    };

    if (item !== null) {
        query.item = item;
    }

    Object.assign(query, collectionReviewQuery(review));

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

/** One positive whole number from the address, or none. */
function positiveId(value: string | null): number | null {
    const id = Number(value);

    return Number.isInteger(id) && id > 0 ? id : null;
}
