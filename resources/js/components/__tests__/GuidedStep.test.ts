import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import GuidedStep from '@/components/GuidedStep.vue';

function mountStep(
    props: Partial<{
        label: string;
        stepNumber: number | null;
        heading: string;
        hint: string;
        detail: string | null;
        canGoBack: boolean;
        filled: boolean;
        nextVisible: boolean;
        headingId: string;
    }> = {},
) {
    return mount(GuidedStep, {
        attachTo: document.body,
        props: {
            label: 'Mistakes',
            stepNumber: 3,
            heading: 'Mistakes',
            hint: 'Paste the mistakes the tutor pointed out.',
            status: 'saved',
            lastSavedAt: 1_771_699_200_000,
            canGoBack: true,
            ...props,
        },
    });
}

describe('the step frame', () => {
    it('says which step this is, with the chip and the count line', () => {
        const wrapper = mountStep({ detail: '2 rows so far' });

        expect(wrapper.text()).toContain('Mistakes');
        expect(wrapper.text()).toContain('Step 3 of 7');
        expect(wrapper.text()).toContain('Paste the mistakes');
        expect(wrapper.text()).toContain('2 rows so far');

        wrapper.unmount();
    });

    it('keeps the counter off a step that is not one of the seven', () => {
        const wrapper = mountStep({
            label: 'Topic',
            stepNumber: null,
            heading: 'Choose a topic',
            hint: 'Your work starts saving as soon as a topic is set.',
        });

        expect(wrapper.text()).not.toContain('Step');

        wrapper.unmount();
    });

    it('has one heading the screen reader can land on', () => {
        const wrapper = mountStep({ headingId: 'guided-heading' });

        expect(wrapper.get('h1').attributes('id')).toBe('guided-heading');

        wrapper.unmount();
    });
});

describe('the buttons', () => {
    it('says Skip until the learner has put something in', async () => {
        const wrapper = mountStep({ filled: false });

        const button = wrapper
            .findAll('button')
            .find((candidate) => candidate.text().includes('Skip'));

        expect(button).toBeDefined();

        await button!.trigger('click');

        expect(wrapper.emitted('next')).toHaveLength(1);

        wrapper.unmount();
    });

    it('says Next once there is something to move on from', () => {
        const wrapper = mountStep({ filled: true });

        expect(wrapper.text()).toContain('Next');
        expect(wrapper.text()).not.toContain('Skip');

        wrapper.unmount();
    });

    it('offers Back only when a step is behind this one', () => {
        const wrapper = mountStep({ canGoBack: false });

        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text().includes('Back')),
        ).toBe(false);

        wrapper.unmount();
    });

    it('goes back when Back is pressed', async () => {
        const wrapper = mountStep({ canGoBack: true });

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Back'))!
            .trigger('click');

        expect(wrapper.emitted('back')).toHaveLength(1);

        wrapper.unmount();
    });

    it('moves on with Ctrl+Enter and with nothing else', async () => {
        const wrapper = mountStep({ canGoBack: false });

        wrapper.element.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Enter',
                ctrlKey: true,
                bubbles: true,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('next')).toHaveLength(1);

        wrapper.element.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('next')).toHaveLength(1);

        wrapper.unmount();
    });

    it('shows the step buttons but not Next on the review screen', () => {
        const wrapper = mountStep({ nextVisible: false });

        expect(wrapper.text()).not.toContain('Skip');
        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text().includes('Back')),
        ).toBe(true);

        wrapper.unmount();
    });
});
