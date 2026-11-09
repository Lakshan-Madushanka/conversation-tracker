import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CustomReviewAttemptItems from '@/components/CustomReviewAttemptItems.vue';
import type {
    CustomReviewAttemptHistoryData,
    CustomReviewAttemptItemData,
} from '@/types';

function item(
    overrides: Partial<CustomReviewAttemptItemData> = {},
): CustomReviewAttemptItemData {
    return {
        id: 11,
        kind: 'vocabulary',
        kind_label: 'Words',
        exercise: 'flashcard',
        text: 'apple',
        rating: 'easy',
        rating_label: 'Easy',
        state: 'rated',
        topic: 'Fruit',
        session_date: '2026-10-12T08:00:00.000000Z',
        href: '/sessions/4/question/1/entry/11',
        deleted: false,
        ...overrides,
    };
}

function attempt(
    items: CustomReviewAttemptItemData[],
): CustomReviewAttemptHistoryData {
    return {
        id: 3,
        number: 1,
        number_label: '1st attempt',
        status: 'completed',
        status_label: 'Completed',
        started_at: '2026-10-12T08:00:00.000000Z',
        finished_at: '2026-10-12T08:05:00.000000Z',
        counts: {
            hard: 0,
            okay: 0,
            easy: items.length,
            skipped: 0,
            rated: items.length,
            unrated: 0,
        },
        items,
    };
}

function mountItems(items: CustomReviewAttemptItemData[]) {
    return mount(CustomReviewAttemptItems, {
        props: { attempt: attempt(items) },
    });
}

describe('an attempt item', () => {
    it('keeps its snapshot, its rating and a way to its session', () => {
        const wrapper = mountItems([item()]);

        expect(wrapper.text()).toContain('apple');
        expect(wrapper.text()).toContain('Easy');
        expect(wrapper.text()).not.toContain('Deleted');
        expect(wrapper.find('a').attributes('href')).toContain('/sessions/4');
    });

    it('marks a deleted item, explains it and opens nothing', () => {
        const wrapper = mountItems([item({ deleted: true, href: null })]);
        const text = wrapper.text();

        expect(text).toContain('Deleted');
        expect(text).toContain(
            'This item was deleted from your collections. It is kept here for your history.',
        );
        expect(text).toContain('apple');
        expect(text).toContain('Easy');
        expect(wrapper.find('a').exists()).toBe(false);
    });
});
