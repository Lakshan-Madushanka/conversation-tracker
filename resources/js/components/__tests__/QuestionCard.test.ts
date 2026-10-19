import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import FeedbackBox from '@/components/FeedbackBox.vue';
import QuestionCard from '@/components/QuestionCard.vue';
import { emptyFeedbackValues, feedbackBoxes } from '@/lib/feedback';
import { newVocabularyEntry } from '@/lib/vocabulary';

/**
 * Each feedback box takes a whole row. That keeps the layout simple and means
 * a box with entries can never stretch or squeeze the boxes around it, which
 * a shared grid row used to do.
 */
function mountCard(feedback = emptyFeedbackValues()) {
    return mount(QuestionCard, {
        attachTo: document.body,
        props: {
            number: 1,
            uid: 'q1',
            questionHtml: '<p>Question?</p>',
            answerHtml: '<p>Answer.</p>',
            feedback,
        },
    });
}

describe('the feedback boxes', () => {
    it('shows every box, one per row', () => {
        const wrapper = mountCard();

        expect(wrapper.findAllComponents(FeedbackBox)).toHaveLength(
            feedbackBoxes.length,
        );
    });

    it('never shares a grid row with another box', () => {
        const wrapper = mountCard();
        const stack = wrapper.get('.space-y-3');

        // A two-column grid would stretch an empty box to match its neighbour.
        expect(stack.classes()).not.toContain('grid');
        expect(stack.classes()).not.toContain('grid-cols-2');
    });
});

describe('a question with entries', () => {
    it('passes the entries through to the box that holds them', () => {
        const wrapper = mountCard({
            ...emptyFeedbackValues(),
            vocabulary: [
                newVocabularyEntry({
                    text: 'reservation',
                    meaning: 'a booking',
                }),
                newVocabularyEntry({ text: 'journey', meaning: 'a trip' }),
            ],
        });

        expect(wrapper.text()).toContain('reservation');
        expect(wrapper.text()).toContain('journey');
    });
});
