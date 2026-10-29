/**
 * Reading and writing the Collections page's own address: the open tab and
 * the opened item live in the query string (`/collections?tab=phrases&item=…`),
 * so a refresh, the back button or a shared link opens the same view.
 *
 * The reader is a pure function of the url, kept free of Inertia so it can be
 * tested without an app.
 */

import { index as collectionsIndex } from '@/routes/collections';
import type { CollectionTabKey } from '@/types';

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
 * The address for one tab, optionally with an opened panel. The item is left
 * out when there is none, so closing the panel has no empty `item=`.
 */
export function collectionUrl(
    tab: CollectionTabKey,
    item: string | null = null,
): string {
    return collectionsIndex.url({
        query: item === null ? { tab } : { tab, item },
    });
}

function isTabKey(value: string | null): value is CollectionTabKey {
    return value !== null && (TAB_KEYS as string[]).includes(value);
}
