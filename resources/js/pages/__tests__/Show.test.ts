import { nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import SessionMenu from '@/components/SessionMenu.vue';
import SessionDeleteDialog from '@/components/SessionDeleteDialog.vue';
import { shortDate } from '@/lib/format';
import {
    deleteSessionPermanently,
    fetchDeletePreview,
} from '@/lib/session-delete';
import Show from '@/pages/sessions/Show.vue';
import {
    edit as sessionsEdit,
    index as sessionsIndex,
} from '@/routes/sessions';
import type {
    PracticeQuestionData,
    PracticeSessionData,
    SessionDeletionPreview,
} from '@/types';

vi.mock('@/lib/session-delete', () => ({
    fetchDeletePreview: vi.fn(),
    deleteSessionPermanently: vi.fn(),
}));

function session(
    overrides: Partial<PracticeSessionData> = {},
): PracticeSessionData {
    return {
        id: 7,
        status: 'finished',
        session_date: '2026-10-12T09:00:00.000000Z',
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

/** Run the next animation frame at once, so a reveal can be checked. */
function runFramesImmediately(): void {
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => {
        callback();

        return 0;
    });
}

function questionAt(
    position: number,
    overrides: Partial<PracticeQuestionData> = {},
): PracticeQuestionData {
    return {
        id: 100 + position,
        position,
        question_html: `<p>Question ${position} text</p>`,
        answer_html: `<p>Answer ${position}</p>`,
        feedback: {},
        ...overrides,
    };
}

function twoQuestions(): PracticeSessionData {
    return session({
        questions: [
            questionAt(1, {
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
                        {
                            id: 'v2',
                            text: 'starter',
                            meaning: 'a first course',
                            example_html: '',
                        },
                    ],
                },
            }),
            questionAt(2, {
                feedback: {
                    vocabulary: [
                        {
                            id: 'v3',
                            text: 'bill',
                            meaning: 'what you pay',
                            example_html: '',
                        },
                    ],
                },
            }),
        ],
    });
}

/** The button whose words include this much. */
function showButton(wrapper: ReturnType<typeof mountShow>, text: string) {
    return wrapper
        .findAll('button')
        .find((button) => button.text().includes(text));
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

    it('links back to the collection a word was opened from', async () => {
        window.history.replaceState(
            null,
            '',
            '/sessions/7?from=collections&question=1&field=entry',
        );

        const wrapper = mountShow();
        await nextTick();

        const back = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('Back to Collections'));

        expect(back?.attributes('href')).toContain('/collections');
        expect(back?.attributes('href')).toContain('tab=vocabulary');
        expect(
            wrapper
                .findAll('a')
                .some((anchor) => anchor.text().includes('Back to Library')),
        ).toBe(false);
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

describe('a session with more than one question', () => {
    it('opens with every question collapsed into a summary', () => {
        const wrapper = mountShow(twoQuestions());

        expect(wrapper.find('#reading-1-question').exists()).toBe(false);
        expect(wrapper.find('#reading-2-question').exists()).toBe(false);
        expect(showButton(wrapper, 'Expand all')?.exists()).toBe(true);
        expect(showButton(wrapper, 'Collapse all')?.exists()).toBe(true);
    });

    it('shows the number, the opening words and the entry summary of a question', () => {
        const wrapper = mountShow(twoQuestions());

        expect(wrapper.text()).toContain('Question 1');
        expect(wrapper.text()).toContain('Question 1 text');
        expect(wrapper.text()).toContain('1 mistake · 2 words');
    });

    it('opens one question when its summary is pressed', async () => {
        const wrapper = mountShow(twoQuestions());

        await showButton(wrapper, 'Question 1')?.trigger('click');
        await nextTick();

        expect(wrapper.find('#reading-1-question').exists()).toBe(true);
        expect(wrapper.find('#reading-2-question').exists()).toBe(false);
    });

    it('opens and shrinks every question at once', async () => {
        const wrapper = mountShow(twoQuestions());

        await showButton(wrapper, 'Expand all')?.trigger('click');
        await nextTick();

        expect(wrapper.find('#reading-1-question').exists()).toBe(true);
        expect(wrapper.find('#reading-2-question').exists()).toBe(true);

        await showButton(wrapper, 'Collapse all')?.trigger('click');
        await nextTick();

        expect(wrapper.find('#reading-1-question').exists()).toBe(false);
        expect(wrapper.find('#reading-2-question').exists()).toBe(false);
    });

    it('opens the question a collection item points at, so its word is highlighted', async () => {
        runFramesImmediately();

        window.history.replaceState(
            null,
            '',
            '/sessions/7?from=collections&question=2&field=entry&type=vocabulary&entry=v3&position=0',
        );

        const wrapper = mount(Show, {
            props: { session: twoQuestions() },
            attachTo: document.body,
        });
        await flushPromises();
        await nextTick();

        const target = document.getElementById('reading-2-entry-v3');

        expect(target).not.toBeNull();
        expect(target?.classList.contains('search-target')).toBe(true);

        wrapper.unmount();
    });
});

describe('reading the boxes as the editor shows them', () => {
    it('names the columns of mistakes, vocabulary and phrases', () => {
        const wrapper = mountShow(
            session({
                questions: [
                    questionAt(1, {
                        feedback: {
                            mistake: [
                                {
                                    id: 'm1',
                                    text: 'I want',
                                    correction_html: '<strong>I want</strong>',
                                    reason: 'More polite',
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
                            phrase: [
                                {
                                    id: 'p1',
                                    text: 'a long way',
                                    meaning: 'far away',
                                    example_html: '',
                                },
                            ],
                        },
                    }),
                ],
            }),
        );

        expect(wrapper.text()).toContain('What I said');
        expect(wrapper.text()).toContain('Correction');
        expect(wrapper.text()).toContain('Why (optional)');
        expect(wrapper.text()).toContain('Word');
        expect(wrapper.text()).toContain('Meaning');
        expect(wrapper.text()).toContain('Example (optional)');
        expect(wrapper.text()).toContain('Phrase');
        expect(wrapper.text()).toContain('Meaning or use');
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

    it('moves focus to the heading after restoring', async () => {
        const fetch = vi.fn(
            async (_url: string, _options?: RequestInit) =>
                new Response(null, { status: 200 }),
        );
        vi.stubGlobal('fetch', fetch);
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            options?.onSuccess?.({} as never);
        });

        const wrapper = mount(Show, {
            props: { session: archived },
            attachTo: document.body,
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Restore session'))
            ?.trigger('click');
        await flushPromises();
        await nextTick();

        expect(document.activeElement?.id).toBe('session-heading');

        wrapper.unmount();
    });
});

describe('the delete warning on the reading page', () => {
    it('names the day the practice happened, not the day it was finished', () => {
        const wrapper = mountShow(
            session({
                session_date: '2026-10-05T09:00:00.000000Z',
                finished_at: '2026-10-12T09:00:00.000000Z',
            }),
        );

        expect(
            wrapper.findComponent(SessionDeleteDialog).props('sessionDate'),
        ).toBe('2026-10-05T09:00:00.000000Z');
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

function deletePreview(archived = false): SessionDeletionPreview {
    return {
        session: {
            id: 7,
            topic: 'Ordering at a restaurant',
            session_date: '2026-10-12T09:00:00.000000Z',
            finished: true,
            archived,
        },
        counts: {
            questions: 1,
            words: 1,
            phrases: 0,
            mistakes: 1,
            grammar_notes: 0,
        },
        progress: {
            before: {
                words: 1,
                phrases: 0,
                mistakes: 1,
                grammar_notes: 0,
                practice_days: 1,
                current_streak: 1,
            },
            after: {
                words: 0,
                phrases: 0,
                mistakes: 0,
                grammar_notes: 0,
                practice_days: 0,
                current_streak: 0,
            },
        },
        token: 'token-1',
    };
}

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.includes(text),
    );
}

async function confirmDelete(saved = session()): Promise<void> {
    vi.mocked(fetchDeletePreview).mockResolvedValue({
        outcome: 'preview',
        preview: deletePreview(saved.archived_at !== null),
    });
    vi.mocked(deleteSessionPermanently).mockResolvedValue({
        outcome: 'deleted',
        result: {
            deleted: true,
            already_deleted: false,
            session: { id: saved.id, topic: saved.topic.name },
            counts: {
                questions: 1,
                words: 1,
                phrases: 0,
                mistakes: 1,
                grammar_notes: 0,
            },
        },
    });

    const wrapper = mountShow(saved);

    wrapper.findComponent(SessionMenu).vm.$emit('delete');
    await flushPromises();

    document.getElementById('delete-understand')?.click();
    await flushPromises();

    buttonWith('Delete permanently')?.click();
    await flushPromises();
}

describe('deleting the session being read', () => {
    it('says it was deleted and returns to the Library', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const success = vi.spyOn(toast, 'success');

        await confirmDelete();

        expect(deleteSessionPermanently).toHaveBeenCalledWith(
            7,
            'token-1',
            expect.any(String),
        );
        expect(success).toHaveBeenCalledWith(
            'Deleted “Ordering at a restaurant”.',
        );
        expect(visit).toHaveBeenCalledWith(sessionsIndex.url());
    });

    it('returns an archived session to the Archived view', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});

        await confirmDelete(
            session({ archived_at: '2026-10-20T09:00:00.000000Z' }),
        );

        expect(visit).toHaveBeenCalledWith(
            sessionsIndex.url({ query: { view: 'archived' } }),
        );
    });
});
