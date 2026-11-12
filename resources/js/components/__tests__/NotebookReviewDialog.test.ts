import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import NotebookReviewDialog from '@/components/NotebookReviewDialog.vue';
import { jsonRequest } from '@/lib/http';
import type {
    NotebookReviewAnswer,
    NotebookReviewCandidateData,
    NotebookReviewCandidatesData,
} from '@/types';

/**
 * Create review and practise is the one place a notebook becomes a saved,
 * Finished review. These tests hold it to the promises the notebook
 * specification makes: every saved item joins up to the maximum, the learner
 * chooses above it, a long review is agreed to first, and nothing is written
 * before the learner says yes.
 */

vi.mock('@/lib/http', () => ({
    jsonRequest: vi.fn(),
}));

const { mockVisit } = vi.hoisted(() => ({ mockVisit: vi.fn() }));

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    router: { visit: mockVisit },
}));

const request = vi.mocked(jsonRequest);

function respond(body: unknown): Response {
    return {
        ok: true,
        status: 200,
        json: async () => body,
    } as unknown as Response;
}

function candidate(
    overrides: Partial<NotebookReviewCandidateData> = {},
): NotebookReviewCandidateData {
    return {
        kind: 'vocabulary',
        identity: `identity-${overrides.title ?? 'bank'}`,
        title: 'bank',
        meaning: 'river side',
        example: '',
        eligible: true,
        reason: null,
        ...overrides,
    };
}

function offers(
    items: NotebookReviewCandidateData[],
): NotebookReviewCandidatesData {
    return {
        candidates: items,
        count: items.length,
        eligible_count: items.filter((item) => item.eligible).length,
        recommended: 20,
        maximum: 50,
    };
}

/** The bodies sent to create, in order. */
let posts: Record<string, unknown>[] = [];
/** What the next posts answer, in order. */
let answers: NotebookReviewAnswer[] = [];
/** Every address a read went to. */
let reads: string[] = [];

function mockServer(offered: NotebookReviewCandidatesData): void {
    request.mockImplementation(async (url, init) => {
        if (init?.method === 'POST') {
            posts.push(
                JSON.parse(init.body as string) as Record<string, unknown>,
            );

            const answer = answers.shift();

            if (answer === undefined) {
                throw new Error('No answer was prepared for this post.');
            }

            return respond(answer);
        }

        reads.push(url);

        return respond(offered);
    });
}

let mounted: VueWrapper | undefined;

async function openDialog(notebookName = 'Kitchen words'): Promise<VueWrapper> {
    mounted = mount(NotebookReviewDialog, {
        attachTo: document.body,
        props: { open: true, notebookId: 7, notebookName },
    });

    await flushPromises();

    return mounted;
}

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.trim() === text,
    );
}

function selectionCheckbox(kind: string, index: number): HTMLElement {
    const checkbox = document.querySelector<HTMLElement>(
        `#notebook-review-item-${kind}-${index}`,
    );

    if (checkbox === null) {
        throw new Error(`The ${kind} item ${index} is not on the page.`);
    }

    return checkbox;
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    posts = [];
    answers = [];
    reads = [];
    request.mockReset();
    mockVisit.mockReset();
    document.body.innerHTML = '';
});

describe('Create review and practise', () => {
    it('creates a review from every saved item and opens the attempt', async () => {
        mockServer(
            offers([
                candidate({ identity: 'bank', title: 'bank' }),
                candidate({
                    identity: 'for-good',
                    kind: 'phrase',
                    title: 'for good',
                    meaning: '',
                    eligible: false,
                    reason: 'Add a meaning.',
                }),
            ]),
        );
        answers = [
            {
                outcome: 'created',
                review_id: 5,
                review_url: '/custom-reviews/5',
                attempt_url: '/custom-reviews/5/attempts/1',
                count: 2,
                maximum: 50,
                message: null,
            },
        ];

        await openDialog();

        expect(document.body.textContent).toContain('2 items');
        expect(document.body.textContent).toContain('1 can be practised now');
        expect(document.body.textContent).toContain('for good');
        expect(document.body.textContent).toContain('Add a meaning.');

        buttonWith('Create review and practise')?.click();
        await flushPromises();

        expect(posts).toHaveLength(1);
        expect(posts[0]).toMatchObject({
            kinds: ['vocabulary', 'phrase', 'grammar_note'],
            identities: null,
            confirmed: false,
        });
        expect(typeof posts[0].creation_key).toBe('string');
        expect(mockVisit).toHaveBeenCalledWith('/custom-reviews/5/attempts/1');
    });

    it('asks before a long review and sends the confirmation only on Continue', async () => {
        mockServer(
            offers(
                Array.from({ length: 21 }, (_, index) =>
                    candidate({
                        identity: `word-${index}`,
                        title: `word ${index + 1}`,
                    }),
                ),
            ),
        );
        answers = [
            {
                outcome: 'created',
                review_id: 9,
                review_url: '/custom-reviews/9',
                attempt_url: '/custom-reviews/9/attempts/1',
                count: 21,
                maximum: 50,
                message: null,
            },
        ];

        await openDialog();

        buttonWith('Create review and practise')?.click();
        await flushPromises();

        expect(posts).toHaveLength(0);
        expect(document.body.textContent).toContain('Practise 21 items?');

        buttonWith('Continue')?.click();
        await flushPromises();

        expect(posts).toHaveLength(1);
        expect(posts[0]).toMatchObject({ confirmed: true });
        expect(mockVisit).toHaveBeenCalledWith('/custom-reviews/9/attempts/1');
    });

    it('writes nothing when the size warning is cancelled', async () => {
        mockServer(
            offers(
                Array.from({ length: 21 }, (_, index) =>
                    candidate({
                        identity: `word-${index}`,
                        title: `word ${index + 1}`,
                    }),
                ),
            ),
        );

        const wrapper = await openDialog();

        buttonWith('Create review and practise')?.click();
        await flushPromises();
        buttonWith('Cancel')?.click();
        await flushPromises();

        expect(posts).toHaveLength(0);
        expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
    });

    it('opens selection above the maximum and sends only the chosen items', async () => {
        mockServer(
            offers(
                Array.from({ length: 51 }, (_, index) =>
                    candidate({
                        identity: `word-${index}`,
                        title: `word ${index + 1}`,
                    }),
                ),
            ),
        );
        answers = [
            {
                outcome: 'created',
                review_id: 12,
                review_url: '/custom-reviews/12',
                attempt_url: '/custom-reviews/12/attempts/1',
                count: 2,
                maximum: 50,
                message: null,
            },
        ];

        await openDialog();

        expect(document.body.textContent).toContain('Choose items to practise');
        expect(document.body.textContent).toContain(
            '51 items in this notebook',
        );
        expect(buttonWith('Create review and practise')?.disabled).toBe(true);

        selectionCheckbox('vocabulary', 0).click();
        await flushPromises();
        selectionCheckbox('vocabulary', 1).click();
        await flushPromises();

        expect(buttonWith('Create review and practise')?.disabled).toBe(false);

        buttonWith('Create review and practise')?.click();
        await flushPromises();

        expect(posts).toHaveLength(1);
        expect(posts[0].identities).toEqual(['word-0', 'word-1']);
    });

    it('cannot start when nothing chosen can be practised yet', async () => {
        mockServer(
            offers([
                candidate({
                    identity: 'bank',
                    title: 'bank',
                    meaning: '',
                    eligible: false,
                    reason: 'Add a meaning.',
                }),
                candidate({
                    identity: 'note',
                    kind: 'grammar_note',
                    title: 'Use the past tense',
                    eligible: false,
                    reason: 'Bold the key part of the note.',
                }),
            ]),
        );

        await openDialog();

        expect(document.body.textContent).toContain(
            'None of these can be practised yet.',
        );
        expect(buttonWith('Create review and practise')?.disabled).toBe(true);
    });

    it('keeps the saved review when practice could not start and retries it', async () => {
        mockServer(offers([candidate({ identity: 'bank', title: 'bank' })]));
        answers = [
            {
                outcome: 'created_start_failed',
                review_id: 3,
                review_url: '/custom-reviews/3',
                attempt_url: null,
                count: 1,
                maximum: 50,
                message:
                    'The review was saved, but practice could not start. Open the review and try again.',
            },
            {
                outcome: 'created',
                review_id: 3,
                review_url: '/custom-reviews/3',
                attempt_url: '/custom-reviews/3/attempts/1',
                count: 1,
                maximum: 50,
                message: null,
            },
        ];

        await openDialog();

        buttonWith('Create review and practise')?.click();
        await flushPromises();

        expect(document.body.textContent).toContain(
            'The review was saved, but practice could not start.',
        );

        const openReview = [...document.querySelectorAll('a')].find(
            (anchor) => anchor.textContent?.trim() === 'Open review',
        );

        expect(openReview?.getAttribute('href')).toBe('/custom-reviews/3');

        buttonWith('Try again')?.click();
        await flushPromises();

        expect(posts).toHaveLength(2);
        expect(posts[1].creation_key).toBe(posts[0].creation_key);
        expect(mockVisit).toHaveBeenCalledWith('/custom-reviews/3/attempts/1');
    });

    it('reads the items again for the chosen kinds only', async () => {
        mockServer(offers([candidate({ identity: 'bank', title: 'bank' })]));

        await openDialog();

        expect(reads[0]).toContain('grammar_note');

        const grammarNotes = document.querySelector<HTMLElement>(
            '[aria-label="Include Grammar notes"]',
        );

        grammarNotes?.click();
        await flushPromises();

        expect(reads).toHaveLength(2);
        expect(reads[1]).toContain('vocabulary');
        expect(reads[1]).not.toContain('grammar_note');
    });

    it('says what the notebook offers and writes nothing on cancel', async () => {
        mockServer(offers([candidate({ identity: 'bank', title: 'bank' })]));

        const wrapper = await openDialog();

        buttonWith('Cancel')?.click();
        await flushPromises();

        expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
        expect(posts).toHaveLength(0);
    });
});
