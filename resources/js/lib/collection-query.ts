/**
 * Reading and writing the Collections page's own address: the open tab and
 * the opened word live in the query string (`/collections?tab=vocabulary&word=relish`),
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
 * The tab and opened word a Collections url asks for. An unknown tab (a
 * hand-edited url, or one left by an older version) falls back to vocabulary
 * rather than breaking the page. A blank word means no panel is open.
 */
export function readCollectionQuery(url: string): {
    tab: CollectionTabKey;
    word: string | null;
} {
    const [, query = ''] = url.split('?');
    const parsed = new URLSearchParams(query.split('#')[0]);

    const tab = parsed.get('tab');
    const word = parsed.get('word');

    return {
        tab: isTabKey(tab) ? tab : FALLBACK_TAB,
        word: word === null || word === '' ? null : word,
    };
}

/**
 * The address for one tab, optionally with a word panel open. A word is left
 * out when there is none, so closing the panel has no empty `word=`.
 */
export function collectionUrl(
    tab: CollectionTabKey,
    word: string | null = null,
): string {
    return collectionsIndex.url({
        query: word === null ? { tab } : { tab, word },
    });
}

function isTabKey(value: string | null): value is CollectionTabKey {
    return value !== null && (TAB_KEYS as string[]).includes(value);
}
