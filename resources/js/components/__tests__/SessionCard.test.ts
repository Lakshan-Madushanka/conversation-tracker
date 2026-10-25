import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import SessionCard from '@/components/SessionCard.vue';
import { shortDate } from '@/lib/format';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

function session(overrides: Partial<SessionCardData> = {}): SessionCardData {
    return {
        id: 7,
        topic: 'Ordering at a restaurant',
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
        status: 'finished',
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

function mountCard(card: SessionCardData = session()) {
    return mount(SessionCard, { props: { session: card } });
}

describe('a session in a list', () => {
    it('shows its topic, the day it happened, what it holds and its status', () => {
        const wrapper = mountCard();
        const date = shortDate('2026-10-12T09:00:00.000000Z');

        // Dates are formatted in the learner's own locale, so the same
        // formatter is used here rather than a fixed expectation.
        expect(date).not.toBe('');
        expect(wrapper.text()).toContain('Ordering at a restaurant');
        expect(wrapper.text()).toContain(
            '6 questions · 12 words · 4 phrases · 4 mistakes',
        );
        expect(wrapper.text()).toContain(date);
        expect(wrapper.text()).toContain('Finished');
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
            '1 question · 1 word · 1 phrase · 1 mistake',
        );
    });

    it('opens the page where a finished session can be read', () => {
        const wrapper = mountCard();

        expect(wrapper.get('a').attributes('href')).toBe(sessionsShow.url(7));
    });

    it('opens the editor for a session that is not finished', () => {
        const wrapper = mountCard(
            session({ status: 'unfinished', last_edited_at: null }),
        );

        expect(wrapper.text()).toContain('Unfinished');
        expect(wrapper.get('a').attributes('href')).toBe(sessionsEdit.url(7));
    });

    it('says when a session was edited on a later day', () => {
        const edited = '2026-10-14T09:00:00.000000Z';
        const wrapper = mountCard(session({ last_edited_at: edited }));
        const time = wrapper
            .findAll('time')
            .find((element) => element.text().includes('Edited'));

        expect(wrapper.text()).toContain('Edited');
        expect(wrapper.text()).toContain(shortDate(edited));
        expect(time?.attributes('title')).toBe(`Edited ${shortDate(edited)}`);
    });

    it('leaves the edited line off when the edit happened on the same day', () => {
        const wrapper = mountCard(
            session({ last_edited_at: '2026-10-12T18:00:00.000000Z' }),
        );

        expect(wrapper.text()).not.toContain('Edited');
    });

    it('shows only the counts when there is no date to read', () => {
        const wrapper = mountCard(session({ session_date: null }));

        expect(wrapper.findAll('time')).toHaveLength(0);
        expect(wrapper.text()).toContain('6 questions');
    });
});
