import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CustomReviewStatusControl from '@/components/CustomReviewStatusControl.vue';

function statusButton(
    wrapper: ReturnType<typeof mount>,
    label: 'Draft' | 'Finished',
) {
    const button = wrapper
        .findAll('button')
        .find((candidate) => candidate.text() === label);

    if (button === undefined) {
        throw new Error(`No ${label} button on the status control.`);
    }

    return button;
}

describe('the review status control', () => {
    it('shows an empty review with neither move available', () => {
        const wrapper = mount(CustomReviewStatusControl, {
            props: { status: 'empty', canFinish: false },
        });

        expect(
            statusButton(wrapper, 'Draft').attributes('disabled'),
        ).toBeDefined();
        expect(
            statusButton(wrapper, 'Finished').attributes('disabled'),
        ).toBeDefined();
    });

    it('keeps Finished out of reach while the review holds nothing', () => {
        const wrapper = mount(CustomReviewStatusControl, {
            props: { status: 'draft', canFinish: false },
        });

        expect(
            statusButton(wrapper, 'Draft').attributes('disabled'),
        ).toBeUndefined();
        expect(
            statusButton(wrapper, 'Finished').attributes('disabled'),
        ).toBeDefined();
    });

    it('moves a draft to finished only when the learner asks', async () => {
        const wrapper = mount(CustomReviewStatusControl, {
            props: { status: 'draft', canFinish: true },
        });

        await statusButton(wrapper, 'Finished').trigger('click');

        expect(wrapper.emitted('change')).toEqual([['finished']]);
    });

    it('moves a finished review back to draft', async () => {
        const wrapper = mount(CustomReviewStatusControl, {
            props: { status: 'finished', canFinish: true },
        });

        await statusButton(wrapper, 'Draft').trigger('click');

        expect(wrapper.emitted('change')).toEqual([['draft']]);
    });

    it('says nothing when the chosen status is pressed again', async () => {
        const wrapper = mount(CustomReviewStatusControl, {
            props: { status: 'draft', canFinish: true },
        });

        await statusButton(wrapper, 'Draft').trigger('click');

        expect(wrapper.emitted('change')).toBeUndefined();
    });

    it('holds both choices while a move is under way', () => {
        const wrapper = mount(CustomReviewStatusControl, {
            props: { status: 'draft', canFinish: true, busy: true },
        });

        expect(
            statusButton(wrapper, 'Draft').attributes('disabled'),
        ).toBeDefined();
        expect(
            statusButton(wrapper, 'Finished').attributes('disabled'),
        ).toBeDefined();
    });
});
