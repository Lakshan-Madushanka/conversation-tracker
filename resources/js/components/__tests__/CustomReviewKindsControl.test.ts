import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CustomReviewKindsControl from '@/components/CustomReviewKindsControl.vue';
import type { CustomReviewKind } from '@/types';

function kindButton(wrapper: ReturnType<typeof mount>, label: string) {
    const button = wrapper
        .findAll('button')
        .find((candidate) => candidate.text() === label);

    if (button === undefined) {
        throw new Error(`No ${label} button on the kinds control.`);
    }

    return button;
}

function control(allowedKinds: CustomReviewKind[] | null, busy = false) {
    return mount(CustomReviewKindsControl, {
        props: { allowedKinds, busy },
    });
}

describe('the review kinds control', () => {
    it('turns a kind off and sends the whole choice', async () => {
        const wrapper = control(null);

        await kindButton(wrapper, 'Phrases').trigger('click');

        expect(wrapper.emitted('change')).toEqual([
            [['vocabulary', 'grammar_note', 'mistake', 'question']],
        ]);
    });

    it('turns a kind back on from the stored choice', async () => {
        const wrapper = control(['vocabulary']);

        await kindButton(wrapper, 'Phrases').trigger('click');

        expect(wrapper.emitted('change')).toEqual([[['vocabulary', 'phrase']]]);
    });

    it('never lets the last kind go', async () => {
        const wrapper = control(['vocabulary']);

        await kindButton(wrapper, 'Words').trigger('click');

        expect(wrapper.emitted('change')).toBeUndefined();
    });

    it('holds every kind while a save is under way', async () => {
        const wrapper = control(null, true);

        expect(
            kindButton(wrapper, 'Words').attributes('disabled'),
        ).toBeDefined();

        await kindButton(wrapper, 'Phrases').trigger('click');

        expect(wrapper.emitted('change')).toBeUndefined();
    });
});
