import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import ReviewIndex from '@/pages/review/Index.vue';
import type {
    GrammarNotesTipData,
    RecentReviewData,
    ReviewRulesData,
    TodayReviewData,
    WaitingForFixData,
} from '@/types';

const todayReview: TodayReviewData = {
    state: 'suggest',
    count: 8,
    minutes: 6,
    mix: '5 flashcards, 2 mistakes to fix, 1 blank to fill in',
    canReviewMore: null,
};

const reviewRules: ReviewRulesData = {
    dailySize: 8,
    newItems: 4,
    hardDays: 1,
    okayDays: 3,
    easyGaps: [7, 14, 30, 60],
    learnedRun: 3,
    difficultRun: 2,
    maxSuggestions: 2,
};

const waitingForFix: WaitingForFixData = {
    vocabulary: 0,
    phrases: 0,
    mistakes: 0,
};

function mountPage(grammarNotesTip: GrammarNotesTipData) {
    return mount(ReviewIndex, {
        props: {
            todayReview,
            waitingForFix,
            grammarNotesTip,
            recentReviews: [] as RecentReviewData[],
            reviewRules,
        },
    });
}

describe('the Review Home grammar-notes tip', () => {
    it('explains how many grammar notes cannot make a blank yet', () => {
        const wrapper = mountPage({ count: 2 });

        expect(wrapper.text()).toContain('2 grammar notes cannot be used yet.');
        expect(wrapper.text()).toContain(
            'Bold the key part of a note to turn it into an exercise.',
        );
    });

    it('uses the singular for one note', () => {
        const wrapper = mountPage({ count: 1 });

        expect(wrapper.text()).toContain('1 grammar note cannot be used yet.');
    });

    it('links to the grammar notes in Collections', () => {
        const wrapper = mountPage({ count: 2 });
        const link = wrapper
            .findAll('a')
            .find((anchor) => anchor.text() === 'Grammar notes in Collections');

        expect(link?.attributes('href')).toContain('tab=grammar');
    });

    it('stays quiet when every grammar note can make a blank', () => {
        const wrapper = mountPage({ count: 0 });

        expect(wrapper.text()).not.toContain('cannot be used yet');
        expect(wrapper.text()).not.toContain(
            'Bold the key part of a note to turn it into an exercise.',
        );
    });
});
