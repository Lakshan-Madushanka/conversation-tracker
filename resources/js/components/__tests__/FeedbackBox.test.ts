import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import FeedbackBox from '@/components/FeedbackBox.vue';
import type { FeedbackType } from '@/types';

/**
 * Wait a real animation frame, so deferred work (focus retries, the
 * "opening" flag clearing) has run.
 */
function animationFrame(): Promise<void> {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            resolve();
        });
    });
}

function mountBox(
    props: Partial<{
        type: FeedbackType;
        modelValue: string;
        fieldId: string;
        questionNumber: number;
        error: string;
    }> = {},
) {
    return mount(FeedbackBox, {
        attachTo: document.body,
        props: {
            type: 'mistake',
            modelValue: '',
            fieldId: 'field-mistake',
            questionNumber: 1,
            ...props,
        },
    });
}

describe('collapsed trigger', () => {
    it('is a real button so it is reachable with the keyboard', () => {
        const wrapper = mountBox();
        const trigger = wrapper.get('button');

        expect(trigger.attributes('type')).toBe('button');
        expect(trigger.attributes('aria-expanded')).toBe('false');

        wrapper.unmount();
    });

    it('opens the box and moves focus into it', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(wrapper.find('textarea').exists()).toBe(true);
        expect(document.activeElement?.id).toBe('field-mistake');

        wrapper.unmount();
    });
});

describe('focus handling', () => {
    it('stays open when the removed trigger fires a focusout while opening', async () => {
        const wrapper = mountBox();
        const trigger = wrapper.get('button');

        trigger.element.focus();
        // A native click, so the re-render that removes the trigger has not
        // happened yet when the event below is dispatched.
        trigger.element.click();

        // Chromium fires a bubbling focusout (with no new focus target) when
        // the focused trigger loses focus during the re-render. That must not
        // be mistaken for the user leaving the box.
        trigger.element.dispatchEvent(
            new FocusEvent('focusout', { bubbles: true }),
        );

        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(wrapper.find('textarea').exists()).toBe(true);

        wrapper.unmount();
    });

    it('stays open when focus moves to an element inside the box', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        const textarea = wrapper.get('textarea');
        textarea.element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: wrapper.element,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('textarea').exists()).toBe(true);

        wrapper.unmount();
    });

    it('collapses an empty box when focus moves to an element outside', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        wrapper.get('textarea').element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: document.body,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('textarea').exists()).toBe(false);

        wrapper.unmount();
    });

    it('collapses an empty box when focus lands nowhere, as on a background click', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        wrapper
            .get('textarea')
            .element.dispatchEvent(
                new FocusEvent('focusout', { bubbles: true }),
            );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('textarea').exists()).toBe(false);

        wrapper.unmount();
    });

    it('keeps a box with content open no matter where focus goes', async () => {
        const wrapper = mountBox({ modelValue: 'I go → I went' });

        expect(wrapper.find('textarea').exists()).toBe(true);

        wrapper.get('textarea').element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: document.body,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('textarea').exists()).toBe(true);

        wrapper.unmount();
    });

    it('opens and keeps a box that carries an error', async () => {
        const wrapper = mountBox({ error: 'This box is required' });

        expect(wrapper.find('textarea').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toBe(
            'This box is required',
        );

        wrapper.unmount();
    });
});
