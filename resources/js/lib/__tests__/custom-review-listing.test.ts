import { describe, expect, it } from 'vite-plus/test';
import {
    CUSTOM_REVIEW_PAGE_SIZE,
    clearCustomReviewSearch,
    clearCustomReviewStatuses,
    copyCustomReviewFilters,
    customReviewCountLabel,
    customReviewCreatedLabel,
    customReviewFilterChips,
    customReviewFiltersToQuery,
    customReviewResultAnnouncement,
    customReviewSortLabel,
    defaultCustomReviewFilters,
    hasCustomReviewFilters,
    hasCustomReviewSearch,
    hasCustomReviewStatuses,
    isCustomReviewsPage,
    mergeCustomReviewCards,
    normalizeCustomReviewFilters,
    removeCustomReviewFilterChip,
    toggleCustomReviewStatus,
    withCustomReviewSearch,
    withCustomReviewSort,
    withCustomReviewStatuses,
    type CustomReviewFilterChip,
} from '@/lib/custom-review-listing';
import type { CustomReviewCardData, CustomReviewFiltersState } from '@/types';

function filters(
    overrides: Partial<CustomReviewFiltersState> = {},
): CustomReviewFiltersState {
    return { ...defaultCustomReviewFilters(), ...overrides };
}

function card(
    overrides: Partial<CustomReviewCardData> = {},
): CustomReviewCardData {
    return {
        id: 1,
        name: 'Morning set',
        description: null,
        description_excerpt: null,
        status: 'draft',
        status_label: 'Draft',
        item_count: 3,
        item_count_label: '3 items',
        too_many_items: false,
        too_many_label: null,
        created_at: '2026-10-12T08:00:00.000000Z',
        attempt_count: 0,
        has_attempts: false,
        attempt_number_label: 'No attempts yet',
        previous_attempt_status: null,
        previous_attempt_label: null,
        attempt_summary_label: 'No attempts yet',
        view_history_url: null,
        can_start: false,
        practice_note: 'Mark this review Finished to practise it.',
        ...overrides,
    };
}

describe('a safe custom review filter state', () => {
    it('starts with no search, no statuses and the usual order', () => {
        expect(defaultCustomReviewFilters()).toEqual({
            q: '',
            statuses: [],
            sort: 'recently_attempted',
        });
    });

    it('ignores values the app does not know', () => {
        const normalized = normalizeCustomReviewFilters({
            q: '  cafe   au  ',
            statuses: [
                'finished',
                'nonsense',
                'empty',
            ] as CustomReviewFiltersState['statuses'],
            sort: 'oldest' as CustomReviewFiltersState['sort'],
        });

        expect(normalized).toEqual({
            q: 'cafe au',
            statuses: ['empty', 'finished'],
            sort: 'recently_attempted',
        });
    });

    it('drops a search shorter than two letters', () => {
        expect(normalizeCustomReviewFilters({ q: ' a ' }).q).toBe('');
    });

    it('copies a state without sharing its statuses', () => {
        const original = filters({ statuses: ['draft'] });
        const copy = copyCustomReviewFilters(original);

        copy.statuses.push('finished');

        expect(original.statuses).toEqual(['draft']);
    });
});

describe('narrowing the list', () => {
    it('counts only a search or statuses, never the order', () => {
        expect(hasCustomReviewFilters(filters())).toBe(false);
        expect(hasCustomReviewFilters(filters({ sort: 'name' }))).toBe(false);
        expect(hasCustomReviewFilters(filters({ q: 'cafe' }))).toBe(true);
        expect(hasCustomReviewFilters(filters({ statuses: ['draft'] }))).toBe(
            true,
        );
        expect(hasCustomReviewSearch(filters({ q: 'cafe' }))).toBe(true);
        expect(hasCustomReviewStatuses(filters({ statuses: ['draft'] }))).toBe(
            true,
        );
    });

    it('changes only the search and keeps statuses and order', () => {
        const next = withCustomReviewSearch(
            filters({ statuses: ['draft'], sort: 'name' }),
            '  cafe  ',
        );

        expect(next).toEqual({
            q: 'cafe',
            statuses: ['draft'],
            sort: 'name',
        });

        expect(clearCustomReviewSearch(next)).toEqual({
            q: '',
            statuses: ['draft'],
            sort: 'name',
        });
    });

    it('toggles one status without touching the rest', () => {
        const one = toggleCustomReviewStatus(filters({ q: 'cafe' }), 'draft');

        expect(one).toEqual({
            q: 'cafe',
            statuses: ['draft'],
            sort: 'recently_attempted',
        });

        expect(toggleCustomReviewStatus(one, 'draft')).toEqual({
            q: 'cafe',
            statuses: [],
            sort: 'recently_attempted',
        });
    });

    it('keeps statuses in the filter’s own order however they arrive', () => {
        expect(
            withCustomReviewStatuses(filters(), ['finished', 'empty']).statuses,
        ).toEqual(['empty', 'finished']);
    });

    it('clears every status and keeps the search and order', () => {
        expect(
            clearCustomReviewStatuses(
                filters({ q: 'cafe', statuses: ['draft'], sort: 'created' }),
            ),
        ).toEqual({ q: 'cafe', statuses: [], sort: 'created' });
    });

    it('stores the order without counting it as a filter', () => {
        expect(withCustomReviewSort(filters(), 'created')).toEqual({
            q: '',
            statuses: [],
            sort: 'created',
        });
    });
});

describe('the listing address', () => {
    it('leaves defaults out and keeps a short address', () => {
        expect(customReviewFiltersToQuery(filters(), 20)).toEqual({});
    });

    it('writes the search, statuses and order it was given', () => {
        expect(
            customReviewFiltersToQuery(
                filters({
                    q: 'cafe au',
                    statuses: ['empty', 'finished'],
                    sort: 'name',
                }),
                20,
            ),
        ).toEqual({ q: 'cafe au', status: 'empty,finished', sort: 'name' });
    });

    it('keeps how far the learner has loaded, within the limit', () => {
        expect(customReviewFiltersToQuery(filters(), 40)).toEqual({
            per_page: '40',
        });
        expect(customReviewFiltersToQuery(filters(), 500)).toEqual({
            per_page: '100',
        });
        expect(customReviewFiltersToQuery(filters(), 0)).toEqual({
            per_page: '1',
        });
        expect(
            customReviewFiltersToQuery(filters(), CUSTOM_REVIEW_PAGE_SIZE),
        ).toEqual({});
    });
});

describe('the chips and the count', () => {
    it('shows one chip per chosen status, in the filter’s order', () => {
        const chips = customReviewFilterChips(
            filters({ statuses: ['finished', 'empty'] }),
        );

        expect(chips.map((chip) => chip.label)).toEqual(['Empty', 'Finished']);
    });

    it('removes only the chip that was chosen', () => {
        const chips = customReviewFilterChips(
            filters({ statuses: ['empty', 'draft'] }),
        );
        const draft = chips.find(
            (chip) => chip.value === 'draft',
        ) as CustomReviewFilterChip;
        const next = removeCustomReviewFilterChip(
            filters({ q: 'cafe', statuses: ['empty', 'draft'], sort: 'name' }),
            draft,
        );

        expect(next).toEqual({
            q: 'cafe',
            statuses: ['empty'],
            sort: 'name',
        });
    });

    it('says how many of the whole list are showing when narrowed', () => {
        expect(customReviewCountLabel(3, 12, true)).toBe(
            '3 of 12 custom reviews',
        );
        expect(customReviewCountLabel(12, 12, false)).toBe('12 custom reviews');
        expect(customReviewCountLabel(1, 1, false)).toBe('1 custom review');
        expect(customReviewCountLabel(1, 1, true)).toBe('1 of 1 custom review');
    });

    it('announces the settled count and the singular', () => {
        expect(customReviewResultAnnouncement(12, false)).toBe(
            '12 custom reviews.',
        );
        expect(customReviewResultAnnouncement(1, false)).toBe(
            '1 custom review.',
        );
        expect(customReviewResultAnnouncement(3, true)).toBe(
            '3 custom reviews match.',
        );
        expect(customReviewResultAnnouncement(1, true)).toBe(
            '1 custom review matches.',
        );
        expect(customReviewResultAnnouncement(0, true)).toBe(
            'No custom reviews match.',
        );
    });

    it('names the order on screen', () => {
        expect(customReviewSortLabel('recently_attempted')).toBe(
            'Most recently attempted',
        );
        expect(customReviewSortLabel('name')).toBe('Name (A to Z)');
        expect(customReviewSortLabel('created')).toBe('Created (newest first)');
    });

    it('writes a created date in the learner’s own time', () => {
        const created = new Date(new Date().getFullYear(), 9, 12, 12);
        const label = customReviewCreatedLabel(created.toISOString());

        expect(label.startsWith('Created ')).toBe(true);
        expect(label).toContain('12');
        expect(customReviewCreatedLabel(null)).toBe('');
        expect(customReviewCreatedLabel('not a date')).toBe('');
    });
});

describe('guarding a loaded page', () => {
    it('accepts the shape the listing sends', () => {
        expect(
            isCustomReviewsPage({
                reviews: [],
                next_cursor: null,
                has_more: false,
                total: 0,
                all_total: 0,
                per_page: 20,
                filters: defaultCustomReviewFilters(),
            }),
        ).toBe(true);
    });

    it('turns down anything the listing cannot use', () => {
        expect(isCustomReviewsPage(null)).toBe(false);
        expect(isCustomReviewsPage({ reviews: [] })).toBe(false);
        expect(
            isCustomReviewsPage({
                reviews: [],
                next_cursor: null,
                has_more: false,
                total: 0,
                all_total: 0,
                per_page: 20,
                filters: { q: 1, statuses: [], sort: 'name' },
            }),
        ).toBe(false);
    });

    it('adds loaded cards without ever showing one twice', () => {
        const merged = mergeCustomReviewCards(
            [card({ id: 1, name: 'One' }), card({ id: 2, name: 'Two' })],
            [
                card({ id: 2, name: 'Two again' }),
                card({ id: 3, name: 'Three' }),
            ],
        );

        expect(merged.map((review) => review.id)).toEqual([1, 2, 3]);
        expect(merged[1]?.name).toBe('Two again');
    });
});
