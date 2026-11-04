import { mount } from '@vue/test-utils';
import { beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import FillInTheBlankExercise from '@/components/review/FillInTheBlankExercise.vue';
import type { FillInTheBlankCard, ReviewRating } from '@/types';

beforeAll(() => {
    // jsdom leaves scrolling out; the browser scrolls for real.
    Element.prototype.scrollIntoView = vi.fn();
});

function card(overrides: Partial<FillInTheBlankCard> = {}): FillInTheBlankCard {
    return {
        kind: 'vocabulary',
        exercise: 'fill_in_the_blank',
        item_identity: 'hello',
        label: 'Fill in the blank',
        prompt_html:
            '<p>I <span data-blank="" role="img" aria-label="missing word"></span> to school every day.</p>',
        answer_html: '<p>I <mark>walk</mark> to school every day.</p>',
        answer_text: 'walk',
        hint: null,
        hint_label: null,
        session_url: '/sessions/7?question=2&field=entry',
        ...overrides,
    };
}

function mountExercise(
    props: Partial<{
        rating: ReviewRating | null;
        checked: boolean;
        attempt: string;
        card: FillInTheBlankCard;
    }> = {},
) {
    const wrapper = mount(FillInTheBlankExercise, {
        attachTo: document.body,
        props: {
            card: card(),
            attempt: '',
            checked: false,
            rating: null,
            processing: false,
            undoOffered: false,
            undoProcessing: false,
            // Behave like the page: the emitted state flows back as props.
            'onUpdate:attempt': (value: string) =>
                wrapper.setProps({ attempt: value }),
            'onUpdate:checked': (value: boolean) =>
                wrapper.setProps({ checked: value }),
            ...props,
        },
    });

    return wrapper;
}

function checkButton(wrapper: ReturnType<typeof mountExercise>) {
    return wrapper
        .findAll('button')
        .find((button) => button.text() === 'Check');
}

function ratingButtons(wrapper: ReturnType<typeof mountExercise>) {
    return wrapper
        .findAll('button')
        .filter((button) => ['Hard', 'Okay', 'Easy'].includes(button.text()));
}

describe('opening the card', () => {
    it('shows the prompt with the blank', () => {
        const wrapper = mountExercise();

        expect(wrapper.text()).toContain('Fill in the blank');
        expect(wrapper.find('[data-blank]').exists()).toBe(true);
        expect(wrapper.text()).toContain('to school every day.');

        wrapper.unmount();
    });

    it('does not show the answer or the ratings before Check', () => {
        const wrapper = mountExercise();

        expect(wrapper.text()).not.toContain('walk');
        expect(ratingButtons(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it('does not ask to rate before Check', () => {
        const wrapper = mountExercise();

        expect(wrapper.emitted('ready')).toEqual([[false]]);

        wrapper.unmount();
    });

    it('types into the field and reports the answer', async () => {
        const wrapper = mountExercise();

        await wrapper.get('input').setValue('walk');

        expect(wrapper.emitted('update:attempt')).toEqual([['walk']]);

        wrapper.unmount();
    });
});

describe('checking the answer', () => {
    it('checks with an empty answer', async () => {
        const wrapper = mountExercise();

        await checkButton(wrapper)!.trigger('click');

        expect(wrapper.emitted('update:checked')).toEqual([[true]]);

        wrapper.unmount();
    });

    it('reveals the answer with a mark', async () => {
        const wrapper = mountExercise();

        await checkButton(wrapper)!.trigger('click');

        expect(wrapper.text()).toContain('Answer');
        expect(wrapper.findAll('mark').map((mark) => mark.text())).toContain(
            'walk',
        );

        wrapper.unmount();
    });

    it('moves focus to the answer and announces it calmly', async () => {
        const wrapper = mountExercise();

        await checkButton(wrapper)!.trigger('click');
        await wrapper.vm.$nextTick();

        const heading = wrapper
            .findAll('h2')
            .find((item) => item.text() === 'Answer');
        const live = wrapper
            .findAll('[aria-live="polite"]')
            .find((item) => item.text() === 'Answer shown.');

        expect(document.activeElement).toBe(heading!.element);
        expect(live?.exists()).toBe(true);

        wrapper.unmount();
    });

    it('checks from the keyboard with Ctrl or Cmd + Enter', async () => {
        const wrapper = mountExercise();

        await wrapper
            .get('input')
            .trigger('keydown', { key: 'Enter', ctrlKey: true });

        expect(wrapper.emitted('update:checked')).toEqual([[true]]);

        wrapper.unmount();
    });

    it('offers the ratings only after Check', async () => {
        const wrapper = mountExercise();

        expect(ratingButtons(wrapper)).toHaveLength(0);

        await checkButton(wrapper)!.trigger('click');

        expect(wrapper.emitted('ready')?.at(-1)).toEqual([true]);
        expect(ratingButtons(wrapper)).toHaveLength(3);

        wrapper.unmount();
    });
});

describe('rating and the original session', () => {
    it('reports the rating the learner picks', async () => {
        const wrapper = mountExercise();

        await checkButton(wrapper)!.trigger('click');
        await ratingButtons(wrapper)
            .find((button) => button.text() === 'Okay')!
            .trigger('click');

        expect(wrapper.emitted('rate')).toEqual([['okay']]);

        wrapper.unmount();
    });

    it('shows a hint with its label when the card has one', () => {
        const wrapper = mountExercise({
            card: card({ hint: 'Use the past simple.', hint_label: 'Why' }),
        });

        expect(wrapper.text()).toContain('Why:');
        expect(wrapper.text()).toContain('Use the past simple.');

        wrapper.unmount();
    });

    it('opens the original session in a new tab with a spoken note', async () => {
        const wrapper = mountExercise();

        await checkButton(wrapper)!.trigger('click');

        const link = wrapper.get('a');

        expect(link.attributes('href')).toBe(
            '/sessions/7?question=2&field=entry',
        );
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toContain('noopener');
        expect(link.text()).toContain('(opens in a new tab)');

        wrapper.unmount();
    });
});

describe('the learner’s answer', () => {
    it('shows the attempt as text, never as markup', async () => {
        const attempt = '<script>alert(1)</script>';

        const wrapper = mountExercise({ attempt, checked: true });

        expect(wrapper.text()).toContain(attempt);
        expect(wrapper.find('script').exists()).toBe(false);

        wrapper.unmount();
    });

    it('keeps special characters and non-English letters', () => {
        const wrapper = mountExercise({
            attempt: 'naïve café — 日本',
            checked: true,
        });

        expect(wrapper.text()).toContain('naïve café — 日本');

        wrapper.unmount();
    });

    it('styles the answer mark without relying on colour', async () => {
        const wrapper = mountExercise();

        await checkButton(wrapper)!.trigger('click');

        const answer = wrapper.get('mark').element.closest('div')!;

        expect(answer.classList.contains('[&_mark]:underline')).toBe(true);
        expect(answer.classList.contains('[&_mark]:font-semibold')).toBe(true);

        wrapper.unmount();
    });
});

describe('an already-rated card', () => {
    it('opens with the answer showing and the rating named', () => {
        const wrapper = mountExercise({ rating: 'hard' });

        expect(wrapper.text()).toContain('walk');
        expect(wrapper.text()).toContain('Rated Hard');

        wrapper.unmount();
    });
});

describe('a long grammar note answer', () => {
    it('is scrollable and keyboard-focusable', () => {
        const wrapper = mountExercise({
            checked: true,
            card: card({
                kind: 'grammar_note',
                item_identity: 'note-12',
                hint: 'Past tenses',
                hint_label: 'Topic',
            }),
        });

        const answer = wrapper.get('[aria-label="Full grammar note"]');

        expect(answer.classes()).toContain('max-h-80');
        expect(answer.classes()).toContain('overflow-y-auto');
        expect(answer.attributes('tabindex')).toBe('0');

        wrapper.unmount();
    });
});
