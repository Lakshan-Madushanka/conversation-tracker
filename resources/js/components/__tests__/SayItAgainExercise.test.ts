import { mount } from '@vue/test-utils';
import { beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import SayItAgainExercise from '@/components/review/SayItAgainExercise.vue';
import type { ReviewRating, SayItAgainCard } from '@/types';

beforeAll(() => {
    // jsdom leaves scrolling out; the browser scrolls for real.
    Element.prototype.scrollIntoView = vi.fn();
});

function card(overrides: Partial<SayItAgainCard> = {}): SayItAgainCard {
    return {
        kind: 'question',
        exercise: 'say_it_again',
        item_identity: '42',
        label: 'Say it again',
        question_html: '<p>Why did <strong>he</strong> leave early?</p>',
        question_text: 'Why did he leave early?',
        natural_html: '<p>What made him leave so early?</p>',
        natural_text: 'What made him leave so early?',
        session_url: '/sessions/7?question=2&field=question',
        ...overrides,
    };
}

function mountExercise(props: Partial<{ rating: ReviewRating | null }> = {}) {
    return mount(SayItAgainExercise, {
        attachTo: document.body,
        props: {
            card: card(),
            rating: null,
            processing: false,
            undoOffered: false,
            undoProcessing: false,
            ...props,
        },
    });
}

function showButton(wrapper: ReturnType<typeof mountExercise>) {
    return wrapper
        .findAll('button')
        .find((button) => button.text().includes('Show natural answer'));
}

describe('opening the card', () => {
    it("shows the tutor's question with its formatting", () => {
        const wrapper = mountExercise();
        const question = wrapper.get('[role="group"]');

        expect(question.text()).toContain('Tutor’s question');
        expect(question.find('strong').text()).toBe('he');

        wrapper.unmount();
    });

    it('focuses the question so it is read first', async () => {
        const wrapper = mountExercise();
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(
            wrapper.get('[role="group"]').element,
        );

        wrapper.unmount();
    });

    it('hides the natural answer and the ratings until revealed', () => {
        const wrapper = mountExercise();

        expect(wrapper.text()).not.toContain('What made him leave');
        expect(
            wrapper
                .findAll('button')
                .filter((button) =>
                    ['Hard', 'Okay', 'Easy'].includes(button.text()),
                ),
        ).toHaveLength(0);

        wrapper.unmount();
    });

    it('does not ask to rate before the answer is shown', () => {
        const wrapper = mountExercise();

        expect(wrapper.emitted('ready')).toEqual([[false]]);

        wrapper.unmount();
    });
});

describe('showing the natural answer', () => {
    it('reveals the natural version in place', async () => {
        const wrapper = mountExercise();

        await showButton(wrapper)!.trigger('click');

        expect(wrapper.text()).toContain('Natural answer');
        expect(wrapper.get('h2').text()).toBe('Natural answer');
        expect(wrapper.html()).toContain('What made him leave so early?');

        wrapper.unmount();
    });

    it('moves focus to the natural answer and announces it calmly', async () => {
        const wrapper = mountExercise();

        await showButton(wrapper)!.trigger('click');
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(wrapper.get('h2').element);
        expect(wrapper.get('[aria-live="polite"]').text()).toBe(
            'Natural answer shown.',
        );

        wrapper.unmount();
    });

    it('allows rating once the answer is shown', async () => {
        const wrapper = mountExercise();

        await showButton(wrapper)!.trigger('click');

        expect(wrapper.emitted('ready')?.at(-1)).toEqual([true]);
        expect(
            wrapper
                .findAll('button')
                .filter((button) =>
                    ['Hard', 'Okay', 'Easy'].includes(button.text()),
                ),
        ).toHaveLength(3);

        wrapper.unmount();
    });

    it('reveals from the keyboard when the card has focus', async () => {
        const wrapper = mountExercise();
        await wrapper.vm.$nextTick();

        document.body.dispatchEvent(
            new KeyboardEvent('keydown', { key: ' ', bubbles: true }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Natural answer');

        wrapper.unmount();
    });
});

describe('rating and the original session', () => {
    it('reports the rating the learner picks', async () => {
        const wrapper = mountExercise();

        await showButton(wrapper)!.trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Okay')!
            .trigger('click');

        expect(wrapper.emitted('rate')).toEqual([['okay']]);

        wrapper.unmount();
    });

    it('opens the original session in a new tab with a spoken note', async () => {
        const wrapper = mountExercise();

        await showButton(wrapper)!.trigger('click');

        const link = wrapper.get('a');

        expect(link.attributes('href')).toBe(
            '/sessions/7?question=2&field=question',
        );
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toContain('noopener');
        expect(link.text()).toContain('Open original session');
        expect(link.text()).toContain('(opens in a new tab)');

        wrapper.unmount();
    });

    it('never offers recording or a microphone', async () => {
        const wrapper = mountExercise();

        await showButton(wrapper)!.trigger('click');

        expect(wrapper.find('input, textarea').exists()).toBe(false);
        expect(wrapper.text().toLowerCase()).not.toContain('record');
        expect(wrapper.text().toLowerCase()).not.toContain('microphone');

        wrapper.unmount();
    });
});

describe('an already-rated card', () => {
    it('opens with the natural answer showing', () => {
        const wrapper = mountExercise({ rating: 'hard' });

        expect(wrapper.text()).toContain('What made him leave so early?');
        expect(wrapper.text()).toContain('Rated Hard');

        wrapper.unmount();
    });
});
