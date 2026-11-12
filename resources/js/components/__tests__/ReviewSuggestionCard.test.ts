import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import ReviewSuggestionCard from '@/components/ReviewSuggestionCard.vue';
import type { TodayReviewData } from '@/types';

function suggestion(overrides: Partial<TodayReviewData> = {}): TodayReviewData {
    return {
        state: 'suggest',
        count: 5,
        minutes: 4,
        mix: '5 flashcards',
        canReviewMore: null,
        ...overrides,
    };
}

function mountCard(overrides: Partial<TodayReviewData> = {}) {
    return mount(ReviewSuggestionCard, {
        props: { suggestion: suggestion(overrides) },
    });
}

describe('the daily review suggestion card', () => {
    it('offers a custom review, notebooks and a new session when nothing is due', () => {
        const wrapper = mountCard({
            state: 'nothing',
            count: null,
            minutes: null,
            mix: null,
        });

        expect(wrapper.text()).toContain('Nothing needs review right now');

        const labels = wrapper.findAll('a').map((anchor) => anchor.text());

        expect(labels).toContain('Custom review');
        expect(labels).toContain('Open notebooks');
        expect(labels).toContain('Start a session');

        wrapper.unmount();
    });

    it('explains that notebooks and sessions both fill the review up', () => {
        const wrapper = mountCard({
            state: 'empty',
            count: null,
            minutes: null,
            mix: null,
        });

        expect(wrapper.text()).toContain('first session or notebook');

        const labels = wrapper.findAll('a').map((anchor) => anchor.text());

        expect(labels).toContain('Open notebooks');
        expect(labels).toContain('Start a session');

        wrapper.unmount();
    });
});
