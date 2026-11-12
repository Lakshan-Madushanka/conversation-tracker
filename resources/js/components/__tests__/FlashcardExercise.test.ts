import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import FlashcardExercise from '@/components/review/FlashcardExercise.vue';
import type { FlashcardCard } from '@/types';

function card(overrides: Partial<FlashcardCard> = {}): FlashcardCard {
    return {
        kind: 'vocabulary',
        exercise: 'flashcard',
        item_identity: 'spatula',
        label: 'Word',
        front: 'spatula',
        meaning: 'a flat tool for lifting food',
        example: null,
        source_kind: 'notebook',
        source_label: 'Kitchen words · Kitchen',
        session_url: '/notebooks/3?entry=abc',
        ...overrides,
    };
}

function mountCard(overrides: Partial<FlashcardCard> = {}) {
    return mount(FlashcardExercise, {
        props: {
            card: card(overrides),
            rating: null,
            processing: false,
            undoOffered: false,
            undoProcessing: false,
        },
    });
}

describe('a notebook flashcard', () => {
    it('shows the words from its own notebook', async () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('spatula');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Show answer')!
            .trigger('click');

        expect(wrapper.text()).toContain('a flat tool for lifting food');

        wrapper.unmount();
    });

    it('names the notebook and its topic and opens the saved entry', async () => {
        const wrapper = mountCard();

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Show answer')!
            .trigger('click');

        expect(wrapper.text()).toContain('From Kitchen words · Kitchen');

        const link = wrapper.get('a');

        expect(link.text()).toContain('Open notebook');
        expect(link.attributes('href')).toBe('/notebooks/3?entry=abc');

        wrapper.unmount();
    });

    it('opens the original session when the card came from one', async () => {
        const wrapper = mountCard({
            source_kind: 'session',
            source_label: null,
            session_url: '/sessions/9?question=1&field=entry',
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Show answer')!
            .trigger('click');

        expect(wrapper.text()).not.toContain('From ');

        const link = wrapper.get('a');

        expect(link.text()).toContain('Open original session');
        expect(link.attributes('href')).toBe(
            '/sessions/9?question=1&field=entry',
        );

        wrapper.unmount();
    });
});
