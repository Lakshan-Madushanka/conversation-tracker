import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { useSelectionBar } from '@/composables/useSelectionBar';
import AppLayout from '@/layouts/AppLayout.vue';

/** A page-like child that asks the layout to make room at the bottom. */
const PageWithSelectionBar = defineComponent({
    setup() {
        const active = ref(true);

        useSelectionBar(active);

        return { active };
    },
    template:
        '<button id="toggle-bar" type="button" @click="active = !active">Toggle</button>',
});

afterEach(() => {
    document.body.innerHTML = '';
    window.sessionStorage.clear();
});

describe('the app layout while a selection bar is up', () => {
    it('gives the bottom navigation space back when the bar closes', async () => {
        const wrapper = mount(AppLayout, {
            attachTo: document.body,
            slots: { default: PageWithSelectionBar },
        });

        await nextTick();

        // The bar takes the bottom of a phone, so the navigation steps aside.
        expect(document.querySelector('nav[aria-label="Primary"]')).toBeNull();

        document.getElementById('toggle-bar')?.click();
        await nextTick();

        expect(
            document.querySelector('nav[aria-label="Primary"]'),
        ).not.toBeNull();

        wrapper.unmount();
    });
});
