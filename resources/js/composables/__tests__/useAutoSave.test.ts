import { effectScope, nextTick, ref, type EffectScope } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    useAutoSave,
    type AutoSaveOutcome,
    type AutoSaveRequest,
} from '@/composables/useAutoSave';
import { questionHasContent, toQuestionForm } from '@/lib/feedback';
import type {
    FeedbackValues,
    PracticeQuestionForm,
    PracticeSessionData,
} from '@/types';

const DEBOUNCE = 500;
const RETRY = 1000;

function emptyFeedback(): FeedbackValues {
    return {
        mistake: '',
        vocabulary: '',
        phrase: '',
        grammar_note: '',
        natural_version: '',
    };
}

function formQuestion(html: string): PracticeQuestionForm {
    return {
        uid: `u-${html || 'empty'}`,
        question_html: html,
        answer_html: '',
        feedback: emptyFeedback(),
    };
}

function savedSession(
    overrides: Partial<PracticeSessionData> = {},
): PracticeSessionData {
    return {
        id: 1,
        status: 'unfinished',
        version: 1,
        topic: { id: 1, name: 'Travel' },
        questions: [],
        ...overrides,
    };
}

interface HarnessOptions {
    topic?: string;
    questions?: PracticeQuestionForm[];
    sessionId?: number | null;
    version?: number | null;
    manual?: boolean;
    responder?: (request: AutoSaveRequest, attempt: number) => AutoSaveOutcome;
    onLoadLatest?: (session: PracticeSessionData) => void;
    onSessionCreated?: (session: PracticeSessionData) => void;
}

function setup(options: HarnessOptions = {}) {
    vi.useFakeTimers();

    const topic = ref(options.topic ?? '');
    const questions = ref<PracticeQuestionForm[]>(
        options.questions ?? [formQuestion('')],
    );
    const calls: AutoSaveRequest[] = [];
    const pending: Array<(outcome: AutoSaveOutcome) => void> = [];
    const manual = options.manual ?? false;

    const responder =
        options.responder ??
        ((request: AutoSaveRequest): AutoSaveOutcome => ({
            outcome: 'saved',
            session: savedSession({
                id: request.sessionId ?? 1,
                version: (request.version ?? 0) + 1,
                topic: { id: 1, name: request.topic },
            }),
            savedAt: '2026-10-16T00:00:00Z',
        }));

    const save = vi.fn((request: AutoSaveRequest): Promise<AutoSaveOutcome> => {
        calls.push(request);

        if (manual) {
            return new Promise<AutoSaveOutcome>((resolve) => {
                pending.push(resolve);
            });
        }

        return Promise.resolve(responder(request, calls.length));
    });

    let createdCount = 0;
    let loaded: PracticeSessionData | null = null;

    const scope: EffectScope = effectScope();
    const api = scope.run(() =>
        useAutoSave({
            snapshot: () => ({
                topic: topic.value,
                questions: questions.value,
            }),
            save,
            hasTopic: () => topic.value.trim().length > 0,
            hasContent: () => questions.value.some(questionHasContent),
            sessionId: options.sessionId ?? null,
            version: options.version ?? null,
            debounceMs: DEBOUNCE,
            retryDelaysMs: [RETRY, RETRY * 2],
            newId: (() => {
                let counter = 0;

                return () => {
                    counter += 1;

                    return `id-${counter}`;
                };
            })(),
            onSessionCreated: (session) => {
                createdCount += 1;
                options.onSessionCreated?.(session);
            },
            onLoadLatest: (session) => {
                // Mirror the page: replace the editor with the server's copy.
                topic.value = session.topic.name;
                questions.value = session.questions.map((question, index) =>
                    toQuestionForm(question, `remote-${index}`),
                );
                loaded = session;
                options.onLoadLatest?.(session);
            },
        }),
    )!;

    return {
        topic,
        questions,
        calls,
        save,
        api,
        scope,
        resolveNext: (outcome: AutoSaveOutcome) => pending.shift()?.(outcome),
        createdCount: () => createdCount,
        loaded: () => loaded,
    };
}

async function flush(): Promise<void> {
    await nextTick();
    await vi.advanceTimersByTimeAsync(0);
}

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('waiting for a pause', () => {
    it('saves the latest content once after typing stops', async () => {
        const test = setup();

        test.topic.value = 'Travel';
        await nextTick();
        test.questions.value = [formQuestion('<p>Where did you go?</p>')];
        await nextTick();

        expect(test.save).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.save).toHaveBeenCalledTimes(1);
        expect(test.calls[0]?.topic).toBe('Travel');
        expect(test.calls[0]?.questions[0]?.question_html).toBe(
            '<p>Where did you go?</p>',
        );
        expect(test.api.status.value).toBe('saved');
        expect(test.api.sessionId.value).toBe(1);

        test.scope.stop();
    });

    it('collapses a burst of edits into one save', async () => {
        const test = setup();

        test.topic.value = 'Travel';
        await nextTick();
        test.questions.value = [formQuestion('<p>One</p>')];
        await nextTick();
        await vi.advanceTimersByTimeAsync(200);
        test.questions.value = [formQuestion('<p>Two</p>')];
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.save).toHaveBeenCalledTimes(1);
        expect(test.calls[0]?.questions[0]?.question_html).toBe('<p>Two</p>');

        test.scope.stop();
    });
});

describe('waiting for a topic', () => {
    it('does not save and asks for a topic when there is content but no topic', async () => {
        const test = setup();

        test.questions.value = [formQuestion('<p>Hello</p>')];
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.save).not.toHaveBeenCalled();
        expect(test.api.status.value).toBe('needs_topic');
        expect(test.api.hasUnsavedChanges.value).toBe(true);

        test.scope.stop();
    });

    it('starts saving as soon as a topic appears', async () => {
        const test = setup({ questions: [formQuestion('<p>Hello</p>')] });

        await vi.advanceTimersByTimeAsync(DEBOUNCE);
        expect(test.api.status.value).toBe('needs_topic');

        test.topic.value = 'Travel';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.save).toHaveBeenCalledTimes(1);
        expect(test.api.status.value).toBe('saved');

        test.scope.stop();
    });

    it('does not create a session for a topic with no content yet', async () => {
        const test = setup();

        test.topic.value = 'Travel';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.save).not.toHaveBeenCalled();

        test.scope.stop();
    });
});

describe('one save at a time', () => {
    it('queues edits made during a save and never saves an older copy after a newer one', async () => {
        const test = setup({
            manual: true,
            topic: 'Travel',
            questions: [formQuestion('<p>One</p>')],
            sessionId: 5,
            version: 1,
        });

        test.questions.value[0]!.question_html = '<p>Two</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);
        expect(test.save).toHaveBeenCalledTimes(1);
        expect(test.api.hasUnsavedChanges.value).toBe(true);

        // Keep typing while the first save is in flight.
        test.questions.value[0]!.question_html = '<p>Three</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);
        expect(test.save).toHaveBeenCalledTimes(1);

        test.resolveNext({
            outcome: 'saved',
            session: savedSession({ id: 5, version: 2 }),
            savedAt: '2026-10-16T00:00:00Z',
        });
        await flush();

        expect(test.save).toHaveBeenCalledTimes(2);
        expect(test.calls[1]?.questions[0]?.question_html).toBe('<p>Three</p>');
        expect(test.api.status.value).toBe('saving');
        expect(test.api.hasUnsavedChanges.value).toBe(true);

        test.resolveNext({
            outcome: 'saved',
            session: savedSession({ id: 5, version: 3 }),
            savedAt: '2026-10-16T00:00:00Z',
        });
        await flush();

        expect(test.api.status.value).toBe('saved');
        expect(test.api.hasUnsavedChanges.value).toBe(false);

        test.scope.stop();
    });
});

describe('failures', () => {
    it('retries after a failure and recovers to Saved', async () => {
        const test = setup({
            topic: 'Travel',
            questions: [formQuestion('<p>Hello</p>')],
            responder: (_request, attempt) =>
                attempt === 1
                    ? { outcome: 'failed' }
                    : {
                          outcome: 'saved',
                          session: savedSession({ id: 1, version: 1 }),
                          savedAt: '2026-10-16T00:00:00Z',
                      },
        });

        test.questions.value[0]!.question_html = '<p>Changed</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.api.status.value).toBe('retrying');

        await vi.advanceTimersByTimeAsync(RETRY);

        expect(test.save).toHaveBeenCalledTimes(2);
        expect(test.api.status.value).toBe('saved');

        test.scope.stop();
    });

    it('reports field problems and does not keep retrying a rejected save', async () => {
        const test = setup({
            topic: 'Travel',
            questions: [formQuestion('<p>Hello</p>')],
            responder: () => ({
                outcome: 'invalid',
                errors: { topic: 'This topic is too long.' },
            }),
        });

        test.questions.value[0]!.question_html = '<p>Changed</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.api.status.value).toBe('failed');
        expect(test.api.errors.value.topic).toBe('This topic is too long.');

        await vi.advanceTimersByTimeAsync(RETRY * 10);

        expect(test.save).toHaveBeenCalledTimes(1);

        test.scope.stop();
    });
});

describe('conflicts', () => {
    it('stops saving and keeps the on-screen text until the learner decides', async () => {
        const test = setup({
            topic: 'Travel',
            questions: [formQuestion('<p>Mine</p>')],
            sessionId: 5,
            version: 1,
            responder: () => ({
                outcome: 'conflict',
                session: savedSession({ id: 5, version: 3 }),
            }),
        });

        test.questions.value[0]!.question_html = '<p>Mine edited</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.api.status.value).toBe('conflict');
        expect(test.api.conflict.value?.version).toBe(3);
        expect(test.api.hasUnsavedChanges.value).toBe(true);

        // No further automatic saves while the conflict is unresolved.
        await vi.advanceTimersByTimeAsync(DEBOUNCE * 10);

        expect(test.save).toHaveBeenCalledTimes(1);

        test.scope.stop();
    });

    it('saves over the other version when the learner keeps their text', async () => {
        const test = setup({
            topic: 'Travel',
            questions: [formQuestion('<p>Mine</p>')],
            sessionId: 5,
            version: 1,
            responder: (_request, attempt) =>
                attempt === 1
                    ? {
                          outcome: 'conflict',
                          session: savedSession({ id: 5, version: 3 }),
                      }
                    : {
                          outcome: 'saved',
                          session: savedSession({ id: 5, version: 4 }),
                          savedAt: '2026-10-16T00:00:00Z',
                      },
        });

        test.questions.value[0]!.question_html = '<p>Mine edited</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);
        expect(test.api.status.value).toBe('conflict');

        test.api.keepMine();
        await flush();

        expect(test.save).toHaveBeenCalledTimes(2);
        expect(test.calls[1]?.version).toBe(3);
        expect(test.api.status.value).toBe('saved');

        test.scope.stop();
    });

    it('adopts the other version when the learner loads it, without saving again', async () => {
        const test = setup({
            topic: 'Travel',
            questions: [formQuestion('<p>Mine</p>')],
            sessionId: 5,
            version: 1,
            responder: () => ({
                outcome: 'conflict',
                session: savedSession({
                    id: 5,
                    version: 3,
                    topic: { id: 1, name: 'Travel' },
                    questions: [
                        {
                            id: 9,
                            position: 1,
                            question_html: '<p>Theirs</p>',
                            answer_html: '',
                            feedback: {},
                        },
                    ],
                }),
            }),
        });

        test.questions.value[0]!.question_html = '<p>Mine edited</p>';
        await nextTick();
        await vi.advanceTimersByTimeAsync(DEBOUNCE);
        expect(test.api.status.value).toBe('conflict');

        test.api.loadLatest();
        await flush();

        expect(test.api.status.value).toBe('saved');
        expect(test.api.conflict.value).toBeNull();
        expect(test.loaded()?.version).toBe(3);
        expect(test.questions.value[0]?.question_html).toBe('<p>Theirs</p>');

        // Replacing the fields must not look like a fresh edit to save.
        await vi.advanceTimersByTimeAsync(DEBOUNCE);

        expect(test.save).toHaveBeenCalledTimes(1);

        test.scope.stop();
    });
});
