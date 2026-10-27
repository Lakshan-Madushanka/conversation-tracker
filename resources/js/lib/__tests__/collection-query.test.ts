import { describe, expect, it } from 'vite-plus/test';
import { collectionUrl, readCollectionQuery } from '@/lib/collection-query';

describe('readCollectionQuery', () => {
    it('opens on vocabulary with no word when the url has no query', () => {
        expect(readCollectionQuery('/collections')).toEqual({
            tab: 'vocabulary',
            word: null,
        });
    });

    it('reads the tab together with the word it belongs to', () => {
        expect(
            readCollectionQuery('/collections?tab=phrases&word=catch%20up'),
        ).toEqual({ tab: 'phrases', word: 'catch up' });
    });

    it('falls back to vocabulary for a tab that does not exist', () => {
        expect(
            readCollectionQuery('/collections?tab=banana&word=soup'),
        ).toEqual({ tab: 'vocabulary', word: 'soup' });
    });

    it('treats a blank word as no panel', () => {
        expect(readCollectionQuery('/collections?tab=grammar&word=')).toEqual({
            tab: 'grammar',
            word: null,
        });
    });

    it('keeps a word that looks like a wildcard', () => {
        expect(readCollectionQuery('/collections?word=%25')).toEqual({
            tab: 'vocabulary',
            word: '%',
        });
    });

    it('reads only the query part of a url that carries a hash', () => {
        expect(
            readCollectionQuery('/collections?tab=mistakes&word=soup#results'),
        ).toEqual({ tab: 'mistakes', word: 'soup' });
    });
});

describe('collectionUrl', () => {
    it('names the tab on its own when no word is open', () => {
        expect(collectionUrl('vocabulary')).toBe('/collections?tab=vocabulary');
    });

    it('adds the opened word beside the tab', () => {
        expect(collectionUrl('phrases', 'catch up')).toBe(
            '/collections?tab=phrases&word=catch+up',
        );
    });
});
