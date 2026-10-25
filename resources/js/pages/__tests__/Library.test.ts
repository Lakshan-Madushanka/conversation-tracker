import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vite-plus/test';
import Index from '@/pages/sessions/Index.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

function session(id: number): SessionCardData {
    return {
        id,
        topic: `Topic ${id}`,
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
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

function mountLibrary(
    sessions: SessionCardData[] = [session(1)],
    total = sessions.length,
) {
    return mount(Index, {
        props: {
            sessions,
            next_cursor: null,
            has_more: false,
            total,
        },
    });
}

beforeEach(() => {
    window.sessionStorage.clear();
});

describe('the Library page', () => {
    it('says how many sessions there are and lists them', () => {
        const wrapper = mountLibrary([session(1), session(2)]);

        expect(wrapper.text()).toContain('2 sessions, newest first.');
        expect(wrapper.text()).toContain('Topic 1');
        expect(wrapper.text()).toContain('Topic 2');
        expect(wrapper.text()).toContain('That is every session — 2 in total.');
    });

    it('uses the singular when there is one session', () => {
        const wrapper = mountLibrary();

        expect(wrapper.text()).toContain('1 session, newest first.');
    });

    it('offers a fresh start when the Library is empty', () => {
        const wrapper = mountLibrary([], 0);

        expect(wrapper.text()).toContain('No sessions yet');

        const start = wrapper
            .findAll('a')
            .find((element) =>
                element.text().includes('Start your first session'),
            );

        expect(start?.attributes('href')).toBe(sessionsCreate.url());
    });
});
