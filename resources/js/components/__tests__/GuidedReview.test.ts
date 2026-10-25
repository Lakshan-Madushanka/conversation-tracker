import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import GuidedReview from '@/components/GuidedReview.vue';
import { emptyFeedbackValues, newNote } from '@/lib/feedback';
import type { MistakeEntry, PracticeQuestionForm } from '@/types';

function mistake(text: string): MistakeEntry {
    return { id: `m-${text}`, text, correction_html: '', reason: '' };
}

function question(
    overrides: Partial<PracticeQuestionForm> = {},
): PracticeQuestionForm {
    return {
        uid: 'q1',
        question_html: '<p>Why did he leave so early?</p>',
        answer_html: '<p>Because his train was at six.</p>',
        feedback: emptyFeedbackValues(),
        ...overrides,
    };
}

function mountReview(
    props: Partial<{
        question: PracticeQuestionForm;
        addDisabled: boolean;
        finishDisabled: boolean;
    }> = {},
) {
    return mount(GuidedReview, {
        attachTo: document.body,
        props: {
            question: question(),
            ...props,
        },
    });
}

describe('the sections', () => {
    it('lists every step of the question in the order it was filled in', () => {
        const wrapper = mountReview();

        const labels = wrapper
            .findAll('li p:first-child')
            .map((line) => line.text());

        expect(labels).toEqual([
            'Tutor’s question',
            'My answer',
            'Mistakes and corrections',
            'New vocabulary',
            'New phrases and expressions',
            'Grammar notes',
            'A more natural version',
        ]);

        wrapper.unmount();
    });

    it('shows what each section holds', () => {
        const wrapper = mountReview({
            question: question({
                feedback: {
                    ...emptyFeedbackValues(),
                    mistake: [mistake('I go → I went')],
                    grammar_note: [newNote('<p>Use the past tense</p>')],
                    natural_version: '<p>He left at dawn.</p>',
                },
            }),
        });

        expect(wrapper.text()).toContain('Why did he leave so early?');
        expect(wrapper.text()).toContain('1 row');
        expect(wrapper.text()).toContain('1 note');
        expect(wrapper.text()).toContain('He left at dawn.');
        // The two untouched boxes say so rather than showing an empty line.
        expect(
            wrapper
                .findAll('li')
                .filter((item) => item.text().includes('Nothing yet.')),
        ).toHaveLength(2);

        wrapper.unmount();
    });

    it('asks for a change to every section', async () => {
        const wrapper = mountReview();

        const changes = wrapper
            .findAll('button')
            .filter((button) => button.text().includes('Change'));

        expect(changes).toHaveLength(7);

        await changes[2]!.trigger('click');

        expect(wrapper.emitted('change')).toEqual([['mistake']]);

        wrapper.unmount();
    });
});

describe('what still needs attention', () => {
    it('offers a fix beside the box whose row is waiting', async () => {
        const wrapper = mountReview({
            question: question({
                feedback: {
                    ...emptyFeedbackValues(),
                    mistake: [mistake('I am agree')],
                },
            }),
        });

        const fixes = wrapper
            .findAll('button')
            .filter((button) => button.text().includes('Fix'));

        expect(fixes).toHaveLength(1);
        expect(wrapper.text()).toContain('Add the correction.');

        await fixes[0]!.trigger('click');

        expect(wrapper.emitted('fix')).toEqual([
            ['mistake', expect.any(String)],
        ]);
        expect(wrapper.emitted('fix')?.[0]?.[1]).toBeTruthy();

        wrapper.unmount();
    });

    it('says nothing when every row has been corrected', () => {
        const wrapper = mountReview({
            question: question({
                feedback: {
                    ...emptyFeedbackValues(),
                    mistake: [
                        {
                            id: 'm1',
                            text: 'I am agree',
                            correction_html: '<p>I agree</p>',
                            reason: '',
                        },
                    ],
                },
            }),
        });

        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text().includes('Fix')),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('need attention');

        wrapper.unmount();
    });
});

describe('the actions', () => {
    it('offers another question and the finish', async () => {
        const wrapper = mountReview();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add another'))!
            .trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Finish session'))!
            .trigger('click');

        expect(wrapper.emitted('add')).toHaveLength(1);
        expect(wrapper.emitted('finish')).toHaveLength(1);

        wrapper.unmount();
    });

    it('can stand still while at the question limit or mid-finish', () => {
        const wrapper = mountReview();

        const add = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add another'))!;
        const finish = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Finish session'))!;

        expect(add.attributes('disabled')).toBeUndefined();
        expect(finish.attributes('disabled')).toBeUndefined();

        wrapper.unmount();

        const held = mountReview({
            addDisabled: true,
            finishDisabled: true,
        });

        expect(
            held
                .findAll('button')
                .find((button) => button.text().includes('Add another'))!
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            held
                .findAll('button')
                .find((button) => button.text().includes('Finish session'))!
                .attributes('disabled'),
        ).toBeDefined();

        held.unmount();
    });
});
