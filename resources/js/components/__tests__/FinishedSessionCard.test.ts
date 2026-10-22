import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import FinishedSessionCard from '@/components/FinishedSessionCard.vue';
import { shortDate } from '@/lib/format';
import { show as sessionsShow } from '@/routes/sessions';
import type { FinishedSessionData } from '@/types';

function session(
    overrides: Partial<FinishedSessionData> = {},
): FinishedSessionData {
    return {
        id: 7,
        topic: 'Ordering at a restaurant',
        finished_at: '2026-10-12T09:00:00.000000Z',
        counts: {
            questions: 6,
            words: 12,
            phrases: 4,
            mistakes: 4,
            grammar_notes: 2,
        },
        ...overrides,
    };
}

function mountCard(finished: FinishedSessionData = session()) {
    return mount(FinishedSessionCard, { props: { session: finished } });
}

describe('a finished session in a list', () => {
    it('shows its topic, the day it was finished and what it holds', () => {
        const wrapper = mountCard();
        const date = shortDate('2026-10-12T09:00:00.000000Z');

        // Dates are formatted in the learner's own locale, so the same
        // formatter is used here rather than a fixed expectation.
        expect(date).not.toBe('');
        expect(wrapper.text()).toContain('Ordering at a restaurant');
        expect(wrapper.text()).toContain(
            '6 questions · 12 words · 4 phrases · 4 mistakes · 2 notes',
        );
        expect(wrapper.text()).toContain(`Finished ${date}`);
    });

    it('counts a single one of anything as one, not ones', () => {
        const wrapper = mountCard(
            session({
                counts: {
                    questions: 1,
                    words: 1,
                    phrases: 1,
                    mistakes: 1,
                    grammar_notes: 1,
                },
            }),
        );

        expect(wrapper.text()).toContain(
            '1 question · 1 word · 1 phrase · 1 mistake · 1 note',
        );
    });

    it('opens the page where the session can be read', () => {
        const wrapper = mountCard();

        expect(wrapper.get('a').attributes('href')).toBe(sessionsShow.url(7));
    });

    it('leaves the date off when there is no timestamp to read', () => {
        const wrapper = mountCard(session({ finished_at: null }));

        expect(wrapper.text()).not.toContain('Finished');
    });
});
