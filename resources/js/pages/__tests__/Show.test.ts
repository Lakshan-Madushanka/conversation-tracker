import { nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
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
        archived_at: null,
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

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    window.sessionStorage.clear();
    window.history.replaceState(null, '', '/');
});

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

    it('shows the count tiles matching the entries shown', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).toContain('Questions');
        expect(wrapper.text()).toContain('Words');
        expect(wrapper.text()).toContain('Phrases');
        expect(wrapper.text()).toContain('Mistakes');
        expect(wrapper.text()).toContain('Grammar notes');
    });

    it('shows a Finished badge for a finished session', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).toContain('Finished');
    });

    it('shows an Unfinished badge for an unfinished session', () => {
        const wrapper = mountShow(
            session({ status: 'unfinished', finished_at: null }),
        );

        expect(wrapper.text()).toContain('Unfinished');
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

describe('a session that was archived', () => {
    const archived = session({
        archived_at: '2026-10-20T09:00:00.000000Z',
    });

    it('says it is archived and explains it is read-only', () => {
        const wrapper = mountShow(archived);

        expect(wrapper.text()).toContain('Archived');
        expect(wrapper.text()).toContain('This session is archived');
        expect(wrapper.text()).toContain(
            'It is read-only until you restore it.',
        );
        expect(wrapper.text()).toContain('Restore session');
    });

    it('does not offer the editor', () => {
        const wrapper = mountShow(archived);

        expect(
            wrapper
                .findAll('a')
                .some((link) => link.text().includes('Edit session')),
        ).toBe(false);
    });

    it('leads back to the Archived view', () => {
        const wrapper = mountShow(archived);
        const back = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('Back to Archived'));

        expect(back?.attributes('href')).toBe(
            sessionsIndex.url({ query: { view: 'archived' } }),
        );
    });

    it('restores the session and reloads the page in place', async () => {
        const fetch = vi.fn(
            async (_url: string, _options?: RequestInit) =>
                new Response(null, { status: 200 }),
        );
        vi.stubGlobal('fetch', fetch);
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});

        const wrapper = mountShow(archived);

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Restore session'))
            ?.trigger('click');
        await flushPromises();

        expect(String(fetch.mock.calls[0]?.[0])).toContain(
            '/sessions/7/restore',
        );
        expect(reload).toHaveBeenCalledTimes(1);
    });
});

describe('a session opened from a search result', () => {
    it('names the place of every item so a result can find it', () => {
        const wrapper = mountShow();

        expect(wrapper.find('#reading-1-question').exists()).toBe(true);
        expect(wrapper.find('#reading-1-answer').exists()).toBe(true);
        expect(wrapper.find('#reading-1-section-mistake').exists()).toBe(true);
        expect(wrapper.find('#reading-1-entry-m1').exists()).toBe(true);
        expect(wrapper.find('#reading-1-entry-v1').exists()).toBe(true);
    });

    it('opens calmly at the top when the question is gone', async () => {
        vi.spyOn(window, 'scrollTo').mockImplementation(() => {});

        window.history.replaceState(
            null,
            '',
            '/sessions/7?question=9&field=question',
        );

        const wrapper = mountShow();

        await nextTick();

        expect(wrapper.text()).toContain('That item has changed');
        expect(wrapper.text()).toContain(
            'The item you searched for has changed since you found it.',
        );
    });

    it('opens calmly at the top when the entry is gone', async () => {
        vi.spyOn(window, 'scrollTo').mockImplementation(() => {});

        window.history.replaceState(
            null,
            '',
            '/sessions/7?question=1&field=entry&type=mistake&entry=gone',
        );

        const wrapper = mountShow();

        await nextTick();

        expect(wrapper.text()).toContain('That item has changed');
    });
});
