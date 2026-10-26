import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import LibrarySearch from '@/components/LibrarySearch.vue';
import { MAX_SEARCH_LENGTH } from '@/lib/library-filters';

function mountSearch(modelValue = '') {
    return mount(LibrarySearch, {
        attachTo: document.body,
        props: { modelValue },
    });
}

afterEach(() => {
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe('the Library search box', () => {
    it('waits for a pause before searching', async () => {
        vi.useFakeTimers();

        const wrapper = mountSearch();

        await wrapper.get('input').setValue('beach');

        expect(wrapper.emitted('search')).toBeUndefined();

        vi.advanceTimersByTime(349);

        expect(wrapper.emitted('search')).toBeUndefined();

        vi.advanceTimersByTime(1);

        expect(wrapper.emitted('search')?.[0]).toEqual(['beach']);

        wrapper.unmount();
    });

    it('searches at once when Enter is pressed', async () => {
        const wrapper = mountSearch();

        await wrapper.get('input').setValue('beach');
        await wrapper.get('input').trigger('keydown.enter');

        expect(wrapper.emitted('search')?.[0]).toEqual(['beach']);

        wrapper.unmount();
    });

    it('does not search on a single letter', async () => {
        vi.useFakeTimers();

        const wrapper = mountSearch();

        await wrapper.get('input').setValue('a');
        vi.advanceTimersByTime(350);

        expect(wrapper.emitted('search')?.[0]).toEqual(['']);
        expect(wrapper.text()).toContain(
            'Type at least 2 characters to search.',
        );

        wrapper.unmount();
    });

    it('tidy spaces before searching', async () => {
        vi.useFakeTimers();

        const wrapper = mountSearch();

        await wrapper.get('input').setValue('  café   au lait  ');
        vi.advanceTimersByTime(350);

        expect(wrapper.emitted('search')?.[0]).toEqual(['café au lait']);

        wrapper.unmount();
    });

    it('says when the search has reached its limit', async () => {
        const wrapper = mountSearch();

        await wrapper.get('input').setValue('a'.repeat(MAX_SEARCH_LENGTH));

        expect(wrapper.text()).toContain(
            `Search is limited to ${MAX_SEARCH_LENGTH} characters.`,
        );

        wrapper.unmount();
    });

    it('clears on Escape and keeps the cursor in the box', async () => {
        const wrapper = mountSearch('beach');

        const input = wrapper.get('input');

        await input.trigger('keydown.esc');
        await nextTick();

        expect(wrapper.emitted('search')?.[0]).toEqual(['']);
        expect((input.element as HTMLInputElement).value).toBe('');
        expect(document.activeElement).toBe(input.element);

        wrapper.unmount();
    });

    it('clears only the search with its button', async () => {
        const wrapper = mountSearch('beach');

        const clear = wrapper.get('[aria-label="Clear search"]');

        await clear.trigger('click');
        await nextTick();

        expect(wrapper.emitted('search')?.[0]).toEqual(['']);
        expect((wrapper.get('input').element as HTMLInputElement).value).toBe(
            '',
        );

        wrapper.unmount();
    });

    it('follows a search cleared from somewhere else', async () => {
        const wrapper = mountSearch('beach');

        await wrapper.setProps({ modelValue: '' });

        expect((wrapper.get('input').element as HTMLInputElement).value).toBe(
            '',
        );

        wrapper.unmount();
    });
});
