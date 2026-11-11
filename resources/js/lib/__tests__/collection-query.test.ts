import { describe, expect, it } from 'vite-plus/test';
import {
    collectionUrl,
    readCollectionFilters,
    readCollectionQuery,
} from '@/lib/collection-query';

describe('readCollectionQuery', () => {
    it('opens on vocabulary with no item when the url has no query', () => {
        expect(readCollectionQuery('/collections')).toEqual({
            tab: 'vocabulary',
            item: null,
        });
    });

    it('reads the tab together with the item it belongs to', () => {
        expect(
            readCollectionQuery('/collections?tab=phrases&item=catch%20up'),
        ).toEqual({ tab: 'phrases', item: 'catch up' });
    });

    it('falls back to vocabulary for a tab that does not exist', () => {
        expect(
            readCollectionQuery('/collections?tab=banana&item=soup'),
        ).toEqual({ tab: 'vocabulary', item: 'soup' });
    });

    it('treats a blank item as no panel', () => {
        expect(readCollectionQuery('/collections?tab=grammar&item=')).toEqual({
            tab: 'grammar',
            item: null,
        });
    });

    it('keeps an item that looks like a wildcard', () => {
        expect(readCollectionQuery('/collections?item=%25')).toEqual({
            tab: 'vocabulary',
            item: '%',
        });
    });

    it('reads only the query part of a url that carries a hash', () => {
        expect(
            readCollectionQuery('/collections?tab=mistakes&item=soup#results'),
        ).toEqual({ tab: 'mistakes', item: 'soup' });
    });

    it('opens nothing for the ?word= an older version of the page once wrote', () => {
        expect(
            readCollectionQuery('/collections?tab=phrases&word=catch%20up'),
        ).toEqual({ tab: 'phrases', item: null });
    });
});

describe('collectionUrl', () => {
    it('names the tab on its own when no item is open', () => {
        expect(collectionUrl('vocabulary')).toBe('/collections?tab=vocabulary');
    });

    it('adds the opened item beside the tab', () => {
        expect(collectionUrl('phrases', 'catch up')).toBe(
            '/collections?tab=phrases&item=catch+up',
        );
    });

    it('keeps the search, filters and order beside the tab', () => {
        expect(
            collectionUrl('vocabulary', 'soup', {
                q: 'cafe',
                starred: true,
                status: 'learned',
                attention: true,
                topics: [2, 5],
                source: 'all' as const,
                notebook: null,
                sort: 'az',
            }),
        ).toBe(
            '/collections?tab=vocabulary&q=cafe&starred=1&status=learned&attention=1&topics=2%2C5&sort=az&item=soup',
        );
    });
});

describe('readCollectionFilters', () => {
    it('opens a tab with its quiet defaults when the url says nothing', () => {
        expect(
            readCollectionFilters('/collections?tab=mistakes', 'mistakes'),
        ).toEqual({
            q: '',
            starred: false,
            status: 'all',
            attention: false,
            topics: [],
            source: 'all' as const,
            notebook: null,
            sort: 'most_seen',
        });
    });

    it('reads every choice back', () => {
        expect(
            readCollectionFilters(
                '/collections?tab=phrases&q=catch&starred=1&status=learned&attention=1&topics=2%2C5&sort=az',
                'phrases',
            ),
        ).toEqual({
            q: 'catch',
            starred: true,
            status: 'learned',
            attention: true,
            topics: [2, 5],
            source: 'all' as const,
            notebook: null,
            sort: 'az',
        });
    });

    it('ignores values it cannot honour', () => {
        expect(
            readCollectionFilters(
                '/collections?tab=grammar&q=a&status=banana&starred=maybe&attention=sometimes&topics=2,nope,-1&sort=most_seen',
                'grammar',
            ),
        ).toEqual({
            q: '',
            starred: false,
            status: 'all',
            attention: false,
            topics: [2],
            source: 'all' as const,
            notebook: null,
            sort: 'newest',
        });
    });
});
