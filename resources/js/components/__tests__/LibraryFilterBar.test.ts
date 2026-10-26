import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import LibraryFilterBar from '@/components/LibraryFilterBar.vue';
import {
    defaultLibraryFilters,
    type LibraryFilters,
} from '@/lib/library-filters';
import type { TopicSummary } from '@/types';

const topics: TopicSummary[] = [
    { id: 1, name: 'Work' },
    { id: 2, name: 'Travel' },
];

function filters(overrides: Partial<LibraryFilters> = {}): LibraryFilters {
    return { ...defaultLibraryFilters(), ...overrides };
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the Library filter bar', () => {
    it('counts the active filters on the phone button', () => {
        const wrapper = mount(LibraryFilterBar, {
            props: {
                filters: filters({
                    topics: [1, 2],
                    types: ['mistake'],
                    status: 'finished',
                }),
                topics,
            },
        });

        const button = wrapper.find('#library-filters-button');

        expect(button.attributes('aria-label')).toBe('Filters, 4 active');
        expect(button.text()).toContain('4');
    });

    it('shows one removable chip per active choice', () => {
        const wrapper = mount(LibraryFilterBar, {
            props: {
                filters: filters({
                    topics: [1],
                    types: ['mistake'],
                    date: 'today',
                    status: 'unfinished',
                }),
                topics,
            },
        });

        expect(
            wrapper.find('[aria-label="Remove Topic: Work filter"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[aria-label="Remove Mistakes filter"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[aria-label="Remove Today filter"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[aria-label="Remove Unfinished filter"]').exists(),
        ).toBe(true);
    });

    it('removes only the chosen chip', async () => {
        const wrapper = mount(LibraryFilterBar, {
            props: {
                filters: filters({
                    topics: [1, 2],
                    types: ['mistake'],
                    status: 'finished',
                }),
                topics,
            },
        });

        await wrapper
            .find('[aria-label="Remove Topic: Travel filter"]')
            .trigger('click');

        expect(wrapper.emitted('change')?.[0]?.[0]).toEqual(
            filters({
                topics: [1],
                types: ['mistake'],
                status: 'finished',
            }),
        );
    });

    it('clears every filter and moves focus to a control', async () => {
        const wrapper = mount(LibraryFilterBar, {
            attachTo: document.body,
            props: {
                filters: filters({ topics: [1], status: 'finished' }),
                topics,
            },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Clear all')
            ?.trigger('click');

        expect(wrapper.emitted('clear')).toHaveLength(1);

        await wrapper.vm.$nextTick();

        // On a narrow screen the phone Filters button is the first control.
        expect(document.activeElement?.id).toBe('library-filters-button');

        wrapper.unmount();
    });

    it('shows no chips or Clear all when nothing is filtered', () => {
        const wrapper = mount(LibraryFilterBar, {
            props: { filters: defaultLibraryFilters(), topics },
        });

        expect(wrapper.find('[data-chip-remove]').exists()).toBe(false);
        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text() === 'Clear all'),
        ).toBe(false);
    });
});
