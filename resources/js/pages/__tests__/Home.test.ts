import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import Home from '@/pages/Home.vue';
import { index as sessionsIndex } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

function session(id: number): SessionCardData {
    return {
        id,
        topic: `Topic ${id}`,
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
        archived_at: null,
        status: 'finished',
        counts: {
            questions: 1,
            words: 1,
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
        },
    };
}

describe('the Home page', () => {
    it('offers the way to the whole Library even before anything is finished', () => {
        const wrapper = mount(Home, { props: { recentSessions: [] } });
        const link = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('View all sessions'));

        expect(link?.attributes('href')).toBe(sessionsIndex.url());
    });

    it('says what will appear once a session is finished', () => {
        const wrapper = mount(Home, { props: { recentSessions: [] } });

        expect(wrapper.text()).toContain(
            'Finished sessions will appear here once you complete one.',
        );
    });

    it('lists recent sessions with the same cards as the Library', () => {
        const wrapper = mount(Home, {
            props: { recentSessions: [session(1), session(2)] },
        });

        expect(wrapper.text()).toContain('Topic 1');
        expect(wrapper.text()).toContain('Topic 2');
        expect(wrapper.text()).not.toContain(
            'Finished sessions will appear here',
        );
    });
});
