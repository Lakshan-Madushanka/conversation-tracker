/**
 * Reading the collections page's own address bar: the search box and the open
 * tab live in the query string (`/collections?search=soup&forPage=phrases`),
 * so a refresh, the back button or a shared link opens the same view the
 * learner left behind.
 *
 * The helper is a pure function of the url, kept free of Inertia so it can be
 * tested without an app.
 */

import type { CollectionKey } from '@/lib/sample-data';

/** The tab shown when the url asks for one this page does not have. */
const FALLBACK_TAB: CollectionKey = 'vocabulary';

/** Every tab the collections page knows about. */
const TAB_KEYS: CollectionKey[] = [
    'vocabulary',
    'phrases',
    'grammar',
    'mistakes',
];

/**
 * The tab and search term a collections url asks for. An unknown `forPage`
 * value (a hand-edited url, or one left by an older version) falls back to
 * vocabulary rather than breaking the page.
 */
export function readCollectionQuery(url: string): {
    tab: CollectionKey;
    search: string;
} {
    const [, query = ''] = url.split('?');
    const parsed = new URLSearchParams(query.split('#')[0]);

    const tab = parsed.get('forPage');
    const search = parsed.get('search') ?? '';

    return {
        tab: isTabKey(tab) ? tab : FALLBACK_TAB,
        search,
    };
}

function isTabKey(value: string | null): value is CollectionKey {
    return value !== null && (TAB_KEYS as string[]).includes(value);
}
