import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CustomReviewCard from '@/components/CustomReviewCard.vue';
import type { CustomReviewCardData } from '@/types';

function card(
    overrides: Partial<CustomReviewCardData> = {},
): CustomReviewCardData {
    return {
        id: 7,
        name: 'Morning set',
        description: 'The words I use before breakfast.',
        description_excerpt: 'The words I use before breakfast.',
        status: 'draft',
        status_label: 'Draft',
        item_count: 3,
        item_count_label: '3 items',
        too_many_items: false,
        too_many_label: null,
        created_at: '2026-10-12T08:00:00.000000Z',
        attempt_count: 0,
        has_attempts: false,
        attempt_number_label: 'No attempts yet',
        previous_attempt_status: null,
        previous_attempt_label: null,
        attempt_summary_label: 'No attempts yet',
        view_history_url: null,
        can_start: false,
        practice_note: 'Mark this review Finished to practise it.',
        ...overrides,
    };
}

function mountCard(overrides: Partial<CustomReviewCardData> = {}) {
    return mount(CustomReviewCard, { props: { review: card(overrides) } });
}

describe('a custom review card', () => {
    it('names the review and links to its page', () => {
        const wrapper = mountCard();
        const link = wrapper.find('h2 a');

        expect(link.text()).toBe('Morning set');
        expect(link.attributes('href')).toContain('/custom-reviews/7');
    });

    it('shows the status, how many items and when it was created', () => {
        const wrapper = mountCard();
        const text = wrapper.text();

        expect(text).toContain('Draft');
        expect(text).toContain('3 items');
        expect(text).toContain('Created');
        expect(wrapper.find('time').attributes('datetime')).toBe(
            '2026-10-12T08:00:00.000000Z',
        );
    });

    it('shortens the description to its excerpt', () => {
        const wrapper = mountCard({
            description_excerpt: 'The words I use before breakfast…',
        });

        expect(wrapper.text()).toContain('The words I use before breakfast…');
        expect(wrapper.text()).not.toContain(
            'The words I use before breakfast. The words',
        );
    });

    it('shows the Too Many Items tag only past the size', () => {
        expect(mountCard().text()).not.toContain('Too Many Items');

        expect(
            mountCard({
                item_count: 21,
                item_count_label: '21 items',
                too_many_items: true,
                too_many_label: 'Too Many Items · 21 items',
            }).text(),
        ).toContain('Too Many Items · 21 items');
    });

    it('says there are no attempts yet and offers no history link', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('No attempts yet');
        expect(wrapper.text()).not.toContain('View history');
        expect(wrapper.findAll('a').map((link) => link.text())).not.toContain(
            'View history',
        );
    });

    it('explains what still stands between the review and practice', () => {
        expect(mountCard().text()).toContain(
            'Mark this review Finished to practise it.',
        );
    });

    it('offers no Start practice or Delete action yet', () => {
        const wrapper = mountCard({
            status: 'finished',
            status_label: 'Finished',
            can_start: true,
            practice_note: null,
        });

        expect(wrapper.text()).not.toContain('Start practice');
        expect(wrapper.text()).not.toContain('Delete');
    });

    it('asks for the chosen status, naming this review', async () => {
        const wrapper = mountCard();
        const finished = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Finished');

        await finished?.trigger('click');

        expect(wrapper.emitted('change-status')?.[0]?.[1]).toBe('finished');
        expect(wrapper.emitted('change-status')?.[0]?.[0]).toMatchObject({
            id: 7,
            name: 'Morning set',
        });
    });

    it('holds both status choices while the change is on its way', () => {
        const wrapper = mount(CustomReviewCard, {
            props: { review: card(), busy: true },
        });

        const buttons = wrapper
            .findAll('button')
            .filter((button) => ['Draft', 'Finished'].includes(button.text()));

        expect(buttons).toHaveLength(2);
        expect(
            buttons.every(
                (button) => button.attributes('disabled') !== undefined,
            ),
        ).toBe(true);
    });
});
