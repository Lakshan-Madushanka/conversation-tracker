import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import NotFound from '@/pages/errors/404.vue';

afterEach(() => {
    window.history.replaceState(null, '', '/');
    document.body.innerHTML = '';
});

describe('a page that could not be found', () => {
    it('offers the Library when a session address no longer exists', async () => {
        window.history.replaceState(null, '', '/sessions/7');

        const wrapper = mount(NotFound, { attachTo: document.body });

        await nextTick();

        expect(wrapper.text()).toContain('We couldn’t find that session');
        expect(wrapper.text()).toContain('It may have been deleted.');
        expect(wrapper.get('a').attributes('href')).toBe('/sessions');
        expect(wrapper.text()).toContain('Go to Library');
    });

    it('keeps the general page for any other address', async () => {
        window.history.replaceState(null, '', '/somewhere-else');

        const wrapper = mount(NotFound, { attachTo: document.body });

        await nextTick();

        expect(wrapper.text()).toContain('We couldn’t find that page');
        expect(wrapper.get('a').attributes('href')).toBe('/');
        expect(wrapper.text()).toContain('Go to Home');
    });
});
