import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { shortDate } from '@/lib/format';
import Show from '@/pages/sessions/Show.vue';
import {
    edit as sessionsEdit,
    index as sessionsIndex,
} from '@/routes/sessions';
import type { PracticeSessionData } from '@/types';

function session(
    overrides: Partial<PracticeSessionData> = {},
): PracticeSessionData {
    return {
        id: 7,
        status: 'finished',
        finished_at: '2026-10-12T09:00:00.000000Z',
        last_changed_at: '2026-10-12T10:00:00.000000Z',
        version: 3,
        topic: { id: 1, name: 'Ordering at a restaurant' },
        questions: [
            {
                id: 11,
                position: 1,
                question_html: '<p>What would you like to start with?</p>',
                answer_html: '<p>I want a soup.</p>',
                feedback: {
                    mistake: [
                        {
                            id: 'm1',
                            text: 'I want a soup',
                            correction_html:
                                '<strong>I&#039;d like a soup</strong>',
                            reason: 'Sounds more polite',
                        },
                    ],
                    vocabulary: [
                        {
                            id: 'v1',
                            text: 'soup',
                            meaning: 'a liquid food',
                            example_html: '',
                        },
                    ],
                },
            },
        ],
        ...overrides,
    };
}

function mountShow(saved: PracticeSessionData = session()) {
    return mount(Show, { props: { session: saved } });
}

describe('a session opened to be read', () => {
    it('says what it is and when it was finished', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).toContain('Ordering at a restaurant');
        expect(wrapper.text()).toContain(
            `Finished ${shortDate('2026-10-12T09:00:00.000000Z')} · 1 question`,
        );
    });

    it('shows the question, the answer and every box that holds something', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).toContain('What would you like to start with?');
        expect(wrapper.text()).toContain('I want a soup.');
        expect(wrapper.text()).toContain('Mistakes and corrections');
        expect(wrapper.text()).toContain('New vocabulary');
        expect(wrapper.text()).toContain('Sounds more polite');
        expect(wrapper.text()).toContain('a liquid food');
    });

    it('shows a correction that was saved as markup', () => {
        const wrapper = mountShow();
        const correction = wrapper
            .findAll('strong')
            .map((element) => element.text());

        expect(correction).toContain("I'd like a soup");
    });

    it('offers a way to the editor', () => {
        const wrapper = mountShow();
        const edit = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('Edit session'));

        expect(edit?.attributes('href')).toBe(sessionsEdit.url(7));
    });

    it('links back to the library', () => {
        const wrapper = mountShow();
        const back = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('Library'));

        expect(back?.attributes('href')).toBe(sessionsIndex.url());
    });

    it('says so when nothing has been written yet', () => {
        const wrapper = mountShow(
            session({
                status: 'unfinished',
                finished_at: null,
                questions: [],
            }),
        );

        expect(wrapper.text()).toContain('Unfinished · 0 questions');
        expect(wrapper.text()).toContain(
            'Nothing has been written into this session yet.',
        );
    });
});
