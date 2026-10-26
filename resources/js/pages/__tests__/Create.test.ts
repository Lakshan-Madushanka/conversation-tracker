import { nextTick } from 'vue';
import { DOMWrapper, mount, type VueWrapper } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import Create from '@/pages/sessions/Create.vue';
import type { PracticeQuestionData, PracticeSessionData } from '@/types';

function question(
    overrides: Partial<PracticeQuestionData> = {},
): PracticeQuestionData {
    return {
        id: 11,
        position: 1,
        question_html: '<p>What would you like to start with?</p>',
        answer_html: '<p>I want a soup.</p>',
        feedback: {},
        ...overrides,
    };
}

function savedSession(
    overrides: Partial<PracticeSessionData> = {},
): PracticeSessionData {
    return {
        id: 7,
        status: 'unfinished',
        finished_at: null,
        last_changed_at: '2026-10-12T10:00:00.000000Z',
        version: 3,
        topic: { id: 1, name: 'Ordering at a restaurant' },
        questions: [question()],
        ...overrides,
    };
}

let mounted: VueWrapper | undefined;

async function mountCreate(
    props: Partial<{ topics: []; session: PracticeSessionData | null }> = {},
): Promise<VueWrapper> {
    mounted = mount(Create, {
        attachTo: document.body,
        props: { topics: [], session: null, ...props },
    });

    // Guided mode reads the address in onMounted and flips the layout; wait
    // for that flush so assertions see the view the learner would.
    await nextTick();

    return mounted;
}

/** The button whose words include this much, so icons never get in the way. */
function button(
    wrapper: VueWrapper,
    text: string,
): DOMWrapper<HTMLButtonElement> {
    const found = wrapper
        .findAll('button')
        .find((candidate) => candidate.text().includes(text));

    if (found === undefined) {
        throw new Error(`No button contains: ${text}`);
    }

    return found;
}

function heading(wrapper: VueWrapper): string {
    return wrapper.get('h1').text();
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    window.history.replaceState(null, '', '/');
});

describe('the plain editor', () => {
    it('offers the guided order beside the finish', async () => {
        const wrapper = await mountCreate();

        expect(button(wrapper, 'Paste in order').exists()).toBe(true);
        expect(button(wrapper, 'Finish session').exists()).toBe(true);
    });

    it('enters guided mode at the topic when there is none', async () => {
        const wrapper = await mountCreate();

        await button(wrapper, 'Paste in order').trigger('click');

        expect(heading(wrapper)).toBe('Choose a topic');
        expect(wrapper.text()).not.toContain('Step 1 of 7');
        expect(window.location.search).toBe('?guided=1&question=1&step=topic');
    });

    it('enters on the first step when a topic is set', async () => {
        const wrapper = await mountCreate({ session: savedSession() });

        await button(wrapper, 'Paste in order').trigger('click');

        expect(heading(wrapper)).toBe('Paste the tutor’s question');
        expect(wrapper.text()).toContain('Step 1 of 7');
        expect(window.location.search).toBe(
            '?guided=1&question=1&step=question',
        );
    });
});

describe('walking through the steps', () => {
    it('moves one step at a time, keeping the address with it', async () => {
        const wrapper = await mountCreate({ session: savedSession() });

        await button(wrapper, 'Paste in order').trigger('click');
        await button(wrapper, 'Next').trigger('click');

        expect(heading(wrapper)).toBe('Paste your own answer');
        expect(wrapper.text()).toContain('Step 2 of 7');
        expect(window.location.search).toBe('?guided=1&question=1&step=answer');
    });

    it('hides Back until there is somewhere to go back to', async () => {
        const wrapper = await mountCreate({ session: savedSession() });

        await button(wrapper, 'Paste in order').trigger('click');

        expect(
            wrapper
                .findAll('button')
                .some((candidate) => candidate.text().includes('Back')),
        ).toBe(false);

        await button(wrapper, 'Next').trigger('click');
        await button(wrapper, 'Back').trigger('click');

        expect(heading(wrapper)).toBe('Paste the tutor’s question');
    });

    it('calls an empty step Skip and a filled one Next', async () => {
        window.history.replaceState(
            null,
            '',
            '/sessions/create?guided=1&question=1&step=answer',
        );
        const answered = await mountCreate({ session: savedSession() });

        expect(button(answered, 'Next').exists()).toBe(true);
        answered.unmount();

        window.history.replaceState(
            null,
            '',
            '/sessions/create?guided=1&question=1&step=answer',
        );
        const unanswered = await mountCreate({
            session: savedSession({
                questions: [question({ answer_html: '' })],
            }),
        });

        expect(
            unanswered
                .findAll('button')
                .some((candidate) => candidate.text().includes('Skip')),
        ).toBe(true);
    });

    it('moves on with Ctrl+Enter, without reaching for the mouse', async () => {
        const wrapper = await mountCreate({ session: savedSession() });

        await button(wrapper, 'Paste in order').trigger('click');
        await wrapper
            .get('h1')
            .trigger('keydown', { key: 'Enter', ctrlKey: true });

        expect(heading(wrapper)).toBe('Paste your own answer');
        expect(window.location.search).toBe('?guided=1&question=1&step=answer');
    });
});

/** Mount straight onto the review screen, as a long address would. */
async function atReview(): Promise<VueWrapper> {
    window.history.replaceState(
        null,
        '',
        '/sessions/7/edit?guided=1&question=1&step=review',
    );

    return await mountCreate({ session: savedSession() });
}

describe('the review at the end of a question', () => {
    it('shows every section of the question in order', async () => {
        const wrapper = await atReview();

        expect(heading(wrapper)).toBe('Review question 1');
        expect(
            wrapper
                .findAll('button')
                .filter((candidate) => candidate.text().includes('Change')),
        ).toHaveLength(7);
        expect(wrapper.text()).toContain('What would you like to start with?');
        expect(window.location.search).toBe('?guided=1&question=1&step=review');
    });

    it('changes back to one section', async () => {
        const wrapper = await atReview();

        await button(wrapper, 'Change').trigger('click');

        expect(heading(wrapper)).toBe('Paste the tutor’s question');
        expect(window.location.search).toBe(
            '?guided=1&question=1&step=question',
        );
    });

    it('adds another question on its first step', async () => {
        const wrapper = await atReview();

        await button(wrapper, 'Add another question').trigger('click');

        expect(heading(wrapper)).toBe('Paste the tutor’s question');
        expect(wrapper.text()).toContain('Step 1 of 7');
        expect(window.location.search).toBe(
            '?guided=1&question=2&step=question',
        );
    });
});

describe('leaving guided mode', () => {
    it('returns to the plain editor on this question', async () => {
        const wrapper = await mountCreate({ session: savedSession() });

        await button(wrapper, 'Paste in order').trigger('click');
        await button(wrapper, 'Exit').trigger('click');

        expect(heading(wrapper)).toBe('Edit session');
        expect(window.location.search).toBe('');
        expect(wrapper.text()).toContain('Question 1');
    });
});

describe('opening a guided address', () => {
    it('lands on the step the address asks for', async () => {
        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?guided=1&question=1&step=grammar_note',
        );
        const wrapper = await mountCreate({ session: savedSession() });

        expect(heading(wrapper)).toBe('Paste the grammar notes');
        expect(wrapper.text()).toContain('Step 6 of 7');
    });

    it('starts with the topic when there is none, whatever the address says', async () => {
        window.history.replaceState(
            null,
            '',
            '/sessions/create?guided=1&question=1&step=review',
        );
        const wrapper = await mountCreate();

        expect(heading(wrapper)).toBe('Choose a topic');
        expect(window.location.search).toBe('?guided=1&question=1&step=topic');
    });

    it('stays on a question the session actually holds', async () => {
        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?guided=1&question=9&step=question',
        );
        const wrapper = await mountCreate({ session: savedSession() });

        expect(heading(wrapper)).toBe('Paste the tutor’s question');
        expect(window.location.search).toBe(
            '?guided=1&question=1&step=question',
        );
    });

    it('leaves the plain editor alone when the address is not guided', async () => {
        window.history.replaceState(null, '', '/sessions/7/edit');
        const wrapper = await mountCreate({ session: savedSession() });

        expect(heading(wrapper)).toBe('Edit session');
        expect(window.location.search).toBe('');
        expect(wrapper.text()).toContain('Question 1');
    });
});

describe('coming back through the browser', () => {
    it('re-asks the server after Back lands on this page', async () => {
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        const wrapper = await mountCreate();

        document.dispatchEvent(
            new CustomEvent('inertia:navigate', {
                detail: {
                    page: { component: 'sessions/Create', url: '/' },
                    type: 'history',
                    cached: false,
                },
            }),
        );

        expect(visit).toHaveBeenCalledWith(window.location.href, {
            replace: true,
            preserveState: false,
            preserveScroll: true,
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('leaves other pages to themselves', async () => {
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        await mountCreate();

        document.dispatchEvent(
            new CustomEvent('inertia:navigate', {
                detail: {
                    page: { component: 'sessions/Show', url: '/sessions/7' },
                    type: 'history',
                    cached: false,
                },
            }),
        );

        expect(visit).not.toHaveBeenCalled();
    });
});

/** Run the next animation frame at once, so a reveal can be checked. */
function runFramesImmediately(): void {
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => {
        callback();

        return 0;
    });
}

describe('an editor opened from a search result', () => {
    it('opens calmly at the top when the question is gone', async () => {
        vi.spyOn(window, 'scrollTo').mockImplementation(() => {});

        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?question=99&field=question',
        );

        const wrapper = await mountCreate({ session: savedSession() });

        expect(wrapper.text()).toContain('That item has changed');
        expect(wrapper.text()).toContain(
            'The item you searched for has changed since you found it.',
        );
    });

    it('takes the learner to the tutor question that matched', async () => {
        runFramesImmediately();

        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?question=1&field=question',
        );

        await mountCreate({ session: savedSession() });
        await nextTick();

        expect(
            document
                .getElementById('question-q1')
                ?.classList.contains('search-target'),
        ).toBe(true);
    });

    it('takes the learner to a matching table row', async () => {
        runFramesImmediately();

        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?question=1&field=entry&type=vocabulary&entry=v1&position=0',
        );

        await mountCreate({
            session: savedSession({
                questions: [
                    question({
                        feedback: {
                            vocabulary: [
                                {
                                    id: 'v1',
                                    text: 'soup',
                                    meaning: 'a liquid food',
                                    example_html: '',
                                },
                            ],
                        },
                    }),
                ],
            }),
        });
        await nextTick();

        expect(
            document
                .getElementById('feedback-q1-vocabulary-row-v1')
                ?.classList.contains('search-target'),
        ).toBe(true);
    });

    it('takes the learner to the natural version that matched', async () => {
        runFramesImmediately();

        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?question=1&field=entry&type=natural_version&entry=nv1&position=0',
        );

        await mountCreate({
            session: savedSession({
                questions: [
                    question({
                        feedback: {
                            natural_version: '<p>I would love to go.</p>',
                        },
                    }),
                ],
            }),
        });
        await nextTick();

        expect(
            document
                .getElementById('feedback-q1-natural_version')
                ?.classList.contains('search-target'),
        ).toBe(true);
    });

    it('opens the card of a question hidden in a long session', async () => {
        runFramesImmediately();

        window.history.replaceState(
            null,
            '',
            '/sessions/7/edit?question=6&field=question',
        );

        const questions = Array.from({ length: 6 }, (_, index) =>
            question({
                id: index + 1,
                position: index + 1,
                question_html: `<p>Question ${index + 1}</p>`,
                answer_html: '',
            }),
        );

        await mountCreate({ session: savedSession({ questions }) });
        await nextTick();

        expect(document.getElementById('question-q6')).not.toBeNull();
    });
});
