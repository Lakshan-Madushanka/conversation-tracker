import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import EmptySessionDialog from '@/components/EmptySessionDialog.vue';

/** The dialog is portalled, and the portal fills in on a later tick. */
async function mountDialog(open = true): Promise<VueWrapper> {
    const wrapper = mount(EmptySessionDialog, {
        attachTo: document.body,
        props: { open },
    });

    await wrapper.vm.$nextTick();

    return wrapper;
}

/** A button by its words, wherever the dialog portal put it. */
function button(label: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll('button')].find(
        (element) => element.textContent?.trim() === label,
    );
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the empty session warning', () => {
    it('explains why the session cannot be finished', async () => {
        await mountDialog();

        expect(document.body.textContent).toContain('Nothing to finish yet');
        expect(document.body.textContent).toContain(
            'every question is still empty',
        );
        expect(button('Keep editing')).toBeDefined();
    });

    it('closes without discarding when the learner keeps editing', async () => {
        const wrapper = await mountDialog();

        button('Keep editing')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
        expect(wrapper.emitted('discard')).toBeUndefined();
    });
});

describe('discarding', () => {
    it('asks a second time instead of throwing anything away', async () => {
        const wrapper = await mountDialog();

        button('Discard session')?.click();
        await wrapper.vm.$nextTick();

        expect(document.body.textContent).toContain('Discard this session?');
        expect(wrapper.emitted('discard')).toBeUndefined();
    });

    it('keeps the session when the second ask is refused', async () => {
        const wrapper = await mountDialog();

        button('Discard session')?.click();
        await wrapper.vm.$nextTick();

        button('Keep it')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('discard')).toBeUndefined();
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('discards once the second ask is accepted', async () => {
        const wrapper = await mountDialog();

        button('Discard session')?.click();
        await wrapper.vm.$nextTick();

        button('Discard session')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('discard')).toHaveLength(1);
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('starts again at the warning each time it is opened', async () => {
        const wrapper = await mountDialog();

        button('Discard session')?.click();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });

        expect(document.body.textContent).toContain('Nothing to finish yet');
        expect(document.body.textContent).not.toContain(
            'Discard this session?',
        );
    });
});
