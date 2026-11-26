import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import LibraryFilterControls from '@/components/LibraryFilterControls.vue';
import { contentTypes, defaultLibraryFilters } from '@/lib/library-filters';
import type { TopicSummary } from '@/types';

const topics: TopicSummary[] = [{ id: 1, name: 'Travel' }];

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the Library filter controls', () => {
    it('offers overall feedback as a content choice', async () => {
        const wrapper = mount(LibraryFilterControls, {
            attachTo: document.body,
            props: {
                filters: defaultLibraryFilters(),
                topics,
            },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Content'))
            ?.trigger('click');

        expect(document.body.textContent).toContain('Overall feedback');
        expect(
            contentTypes.some((option) => option.type === 'overall_feedback'),
        ).toBe(true);

        wrapper.unmount();
    });
});
