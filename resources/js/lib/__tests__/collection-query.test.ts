import { describe, expect, it } from 'vite-plus/test';
import { readCollectionQuery } from '@/lib/collection-query';

describe('readCollectionQuery', () => {
    it('opens on vocabulary with no term when the url has no query', () => {
        expect(readCollectionQuery('/collections')).toEqual({
            tab: 'vocabulary',
            search: '',
        });
    });

    it('reads the term together with the tab it belongs to', () => {
        expect(
            readCollectionQuery('/collections?search=soup&forPage=phrases'),
        ).toEqual({ tab: 'phrases', search: 'soup' });
    });

    it('falls back to vocabulary for a tab that does not exist', () => {
        expect(
            readCollectionQuery('/collections?search=soup&forPage=banana'),
        ).toEqual({ tab: 'vocabulary', search: 'soup' });
    });

    it('keeps a term that looks like a wildcard', () => {
        expect(readCollectionQuery('/collections?search=%25')).toEqual({
            tab: 'vocabulary',
            search: '%',
        });
    });

    it('reads only the query part of a url that carries a hash', () => {
        expect(readCollectionQuery('/collections?search=soup#results')).toEqual(
            { tab: 'vocabulary', search: 'soup' },
        );
    });
});
