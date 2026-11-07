import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CustomReviewCard from '@/components/CustomReviewCard.vue';
import CustomReviewCardSkeleton from '@/components/CustomReviewCardSkeleton.vue';
import CustomReviewList from '@/components/CustomReviewList.vue';
import type { CustomReviewCardData } from '@/types';

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
        item_count: 2,
        item_count_label: '2 items',
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

function mountList(
    props: Partial<InstanceType<typeof CustomReviewList>['$props']> = {},
) {
    return mount(CustomReviewList, {
        props: {
            reviews: [card()],
            total: 1,
            allTotal: 1,
            ...props,
        },
    });
}

function buttonNamed(wrapper: ReturnType<typeof mountList>, label: string) {
    return wrapper.findAll('button').find((button) => button.text() === label);
}

describe('the custom review list', () => {
    it('welcomes a learner with no reviews and offers the first one', () => {
        const wrapper = mountList({ reviews: [], total: 0, allTotal: 0 });
        const button = wrapper
            .findAll('button')
            .find((candidate) =>
                candidate.text().includes('New custom review'),
            );

        expect(wrapper.text()).toContain('No custom reviews yet');
        expect(wrapper.text()).toContain(
            'Build one from the items you choose, and practise it whenever you like.',
        );
        expect(button).toBeDefined();
    });

    it('offers Clear search alone when only the search matches nothing', () => {
        const wrapper = mountList({
            reviews: [],
            total: 0,
            allTotal: 4,
            hasFilters: true,
            hasSearch: true,
            hasFilterChips: false,
        });

        expect(wrapper.text()).toContain('No custom reviews match your search');
        expect(buttonNamed(wrapper, 'Clear search')).toBeDefined();
        expect(buttonNamed(wrapper, 'Clear all filters')).toBeUndefined();
    });

    it('offers Clear all filters when a status chip is on', () => {
        const wrapper = mountList({
            reviews: [],
            total: 0,
            allTotal: 4,
            hasFilters: true,
            hasSearch: false,
            hasFilterChips: true,
        });

        expect(wrapper.text()).toContain(
            'No custom reviews match these filters',
        );
        expect(buttonNamed(wrapper, 'Clear search')).toBeUndefined();
        expect(buttonNamed(wrapper, 'Clear all filters')).toBeDefined();
    });

    it('offers both ways out when search and statuses narrow together', () => {
        const wrapper = mountList({
            reviews: [],
            total: 0,
            allTotal: 4,
            hasFilters: true,
            hasSearch: true,
            hasFilterChips: true,
        });

        expect(buttonNamed(wrapper, 'Clear search')).toBeDefined();
        expect(buttonNamed(wrapper, 'Clear all filters')).toBeDefined();
    });

    it('emits the way out the learner chose', async () => {
        const wrapper = mountList({
            reviews: [],
            total: 0,
            allTotal: 4,
            hasFilters: true,
            hasSearch: true,
            hasFilterChips: true,
        });

        await buttonNamed(wrapper, 'Clear search')?.trigger('click');
        await buttonNamed(wrapper, 'Clear all filters')?.trigger('click');

        expect(wrapper.emitted('clear-search')).toHaveLength(1);
        expect(wrapper.emitted('clear-filters')).toHaveLength(1);
    });

    it('keeps the cards on screen when Load more fails', () => {
        const wrapper = mountList({
            reviews: [card()],
            total: 40,
            allTotal: 40,
            hasMore: true,
            error: true,
        });

        expect(wrapper.findAllComponents(CustomReviewCard)).toHaveLength(1);
        expect(wrapper.text()).toContain('Morning set');
        expect(wrapper.text()).toContain('Could not load more custom reviews');
        expect(wrapper.text()).toContain(
            'The reviews already on screen are still here.',
        );
        expect(buttonNamed(wrapper, 'Try again')).toBeDefined();
    });

    it('asks for the next page and shows placeholders while it comes', () => {
        const wrapper = mountList({
            reviews: [card()],
            total: 40,
            allTotal: 40,
            hasMore: true,
            loading: true,
        });

        expect(wrapper.findAllComponents(CustomReviewCard)).toHaveLength(1);
        expect(
            wrapper.findAllComponents(CustomReviewCardSkeleton).length,
        ).toBeGreaterThan(0);
        expect(wrapper.text()).toContain('Loading…');
    });

    it('emits Load more when the learner asks', async () => {
        const wrapper = mountList({
            reviews: [card()],
            total: 40,
            allTotal: 40,
            hasMore: true,
        });

        await buttonNamed(wrapper, 'Load more custom reviews')?.trigger(
            'click',
        );

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('says when every matching review is showing', () => {
        const wrapper = mountList({
            reviews: [card(), card({ id: 2, name: 'Evening set' })],
            total: 2,
            allTotal: 5,
            hasFilters: true,
            hasSearch: true,
        });

        expect(wrapper.text()).toContain(
            'That is every matching custom review — 2 in total.',
        );
    });

    it('shows placeholders without cards while a new filter state arrives', () => {
        const wrapper = mountList({ updating: true });

        expect(wrapper.findAllComponents(CustomReviewCard)).toHaveLength(0);
        expect(
            wrapper.findAllComponents(CustomReviewCardSkeleton).length,
        ).toBeGreaterThan(0);
        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true);
    });

    it('announces the settled count only once the list has stopped moving', () => {
        const settled = mountList({
            reviews: [card()],
            total: 3,
            allTotal: 12,
            hasFilters: true,
            hasSearch: true,
        });
        const moving = mountList({
            reviews: [card()],
            total: 3,
            allTotal: 12,
            hasFilters: true,
            hasSearch: true,
            updating: true,
        });

        expect(settled.text()).toContain('3 custom reviews match.');
        expect(moving.text()).not.toContain('3 custom reviews match.');
    });
});
