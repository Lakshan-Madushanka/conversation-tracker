import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CustomReviewKindsControl from '@/components/CustomReviewKindsControl.vue';
import CustomReviewStatusControl from '@/components/CustomReviewStatusControl.vue';
import {
    loadAttemptHistory,
    removeItemsFromReview,
    updateReviewKinds,
    updateReviewStatus,
} from '@/lib/custom-review';
import Show from '@/pages/custom-reviews/Show.vue';
import type {
    CustomReviewAttemptHistoryData,
    CustomReviewGroupData,
    CustomReviewItemData,
    CustomReviewPageData,
    CustomReviewSummary,
} from '@/types';

vi.mock('@/lib/custom-review', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/custom-review')>()),
    loadAttemptHistory: vi.fn(),
    removeItemsFromReview: vi.fn(),
    updateReviewKinds: vi.fn(),
    updateReviewStatus: vi.fn(),
}));

afterEach(() => {
    vi.restoreAllMocks();

    // Dialogs are portaled onto the page, so they outlive their test unless
    // the page is unmounted.
    for (const wrapper of mounted.splice(0)) {
        wrapper.unmount();
    }
});

/** Every page mounted by a test, so each one can be taken down again. */
const mounted: { unmount(): void }[] = [];

function summary(
    overrides: Partial<CustomReviewSummary> = {},
): CustomReviewSummary {
    return {
        id: 1,
        name: 'Morning set',
        description: null,
        status: 'draft',
        status_label: 'Draft',
        allowed_kinds: null,
        allowed_kinds_label: 'All kinds',
        item_count: 3,
        item_count_label: '3 items',
        too_many_items: false,
        too_many_label: null,
        ...overrides,
    };
}

function item(position: number): CustomReviewItemData {
    return {
        id: `word-${position}`,
        kind: 'vocabulary',
        identity: `word${position}`,
        title: `word${position}`,
        correction: 'the meaning',
        correction_html: 'the meaning',
        available: true,
        eligible: true,
        reason: null,
        topic: 'Ordering coffee',
        session_date: '2026-10-12T09:00:00.000000Z',
        href: '/sessions/7',
    };
}

function groups(): CustomReviewGroupData[] {
    return [
        {
            key: 'vocabulary',
            label: 'Words',
            count: 3,
            items: [item(1), item(2), item(3)],
        },
    ];
}

function pageProps(
    overrides: Partial<CustomReviewPageData> = {},
): CustomReviewPageData {
    return {
        review: summary(),
        groups: groups(),
        back_url: '/custom-reviews',
        add_items_url: '/collections?review=1&eligible=1',
        practice: {
            can_start: false,
            eligible_count: 3,
            start_label: null,
            start_url: null,
            in_progress: false,
            resume_url: null,
            note: 'Mark this review Finished to practise it.',
        },
        attempts: [],
        attempt_total: 0,
        attempts_next_page: null,
        expanded_attempt: null,
        attempt_error: null,
        delete_url: '/custom-reviews/1',
        ...overrides,
    };
}

function mountPage(overrides: Partial<CustomReviewPageData> = {}) {
    const wrapper = mount(Show, { props: pageProps(overrides) });
    mounted.push(wrapper);

    return wrapper;
}

type Wrapper = ReturnType<typeof mountPage>;

/** The action whose label reads exactly this, if it is on the page. */
function actionNamed(wrapper: Wrapper, label: string) {
    return wrapper
        .findAll('button, a')
        .find((candidate) => candidate.text().trim() === label);
}

/** Moves the review with the control on the page, the way a learner does. */
function choose(wrapper: Wrapper, status: 'draft' | 'finished'): void {
    wrapper.findComponent(CustomReviewStatusControl).vm.$emit('change', status);
}

describe('the practice card on a review’s page', () => {
    it('offers Start practice as soon as the learner marks it Finished', async () => {
        vi.mocked(updateReviewStatus).mockResolvedValue(
            summary({ status: 'finished', status_label: 'Finished' }),
        );

        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            'Mark this review Finished to practise it.',
        );
        expect(
            actionNamed(wrapper, 'Start practice · 3 items'),
        ).toBeUndefined();

        choose(wrapper, 'finished');
        await flushPromises();

        expect(actionNamed(wrapper, 'Start practice · 3 items')).toBeDefined();
        expect(wrapper.text()).not.toContain(
            'Mark this review Finished to practise it.',
        );
    });

    it('takes Start practice away again when the review goes back to Draft', async () => {
        vi.mocked(updateReviewStatus).mockResolvedValue(
            summary({ status: 'draft', status_label: 'Draft' }),
        );

        const wrapper = mountPage({
            review: summary({ status: 'finished', status_label: 'Finished' }),
            practice: {
                can_start: true,
                eligible_count: 3,
                start_label: 'Start practice · 3 items',
                start_url: '/custom-reviews/1/attempts',
                in_progress: false,
                resume_url: null,
                note: null,
            },
        });

        expect(actionNamed(wrapper, 'Start practice · 3 items')).toBeDefined();

        choose(wrapper, 'draft');
        await flushPromises();

        expect(
            actionNamed(wrapper, 'Start practice · 3 items'),
        ).toBeUndefined();
        expect(wrapper.text()).toContain(
            'Mark this review Finished to practise it.',
        );
    });

    it('keeps offering the running attempt whatever the status becomes', async () => {
        vi.mocked(updateReviewStatus).mockResolvedValue(
            summary({ status: 'draft', status_label: 'Draft' }),
        );

        const wrapper = mountPage({
            review: summary({ status: 'finished', status_label: 'Finished' }),
            practice: {
                can_start: true,
                eligible_count: 3,
                start_label: null,
                start_url: null,
                in_progress: true,
                resume_url: '/custom-reviews/1/attempts/4',
                note: null,
            },
        });

        expect(actionNamed(wrapper, 'Resume attempt')).toBeDefined();

        choose(wrapper, 'draft');
        await flushPromises();

        expect(actionNamed(wrapper, 'Resume attempt')).toBeDefined();
        expect(
            actionNamed(wrapper, 'Start practice · 3 items'),
        ).toBeUndefined();
    });

    it('posts to the attempt route when Start practice is pressed', async () => {
        vi.mocked(updateReviewStatus).mockResolvedValue(
            summary({ status: 'finished', status_label: 'Finished' }),
        );
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation(() => undefined);

        const wrapper = mountPage();

        choose(wrapper, 'finished');
        await flushPromises();

        const start = actionNamed(wrapper, 'Start practice · 3 items');

        expect(start).toBeDefined();

        await start?.trigger('click');

        expect(post).toHaveBeenCalledWith(
            '/custom-reviews/1/attempts',
            {},
            expect.anything(),
        );
    });

    it('keeps saying what still stands in the way when the move is refused', async () => {
        vi.mocked(updateReviewStatus).mockRejectedValue(
            new Error('This review could not be moved just now.'),
        );

        const wrapper = mountPage();

        choose(wrapper, 'finished');
        await flushPromises();

        expect(wrapper.text()).toContain(
            'This review could not be moved just now.',
        );
        expect(
            actionNamed(wrapper, 'Start practice · 3 items'),
        ).toBeUndefined();
        expect(wrapper.text()).toContain(
            'Mark this review Finished to practise it.',
        );
    });

    it('lets the practice count follow the items that leave the review', async () => {
        vi.mocked(removeItemsFromReview).mockResolvedValue({
            removed_count: 1,
            removed_identities: ['word1'],
            total: 1,
        });

        const wrapper = mountPage({
            review: summary({
                status: 'finished',
                status_label: 'Finished',
                item_count: 3,
                item_count_label: '3 items',
            }),
            practice: {
                can_start: true,
                eligible_count: 3,
                start_label: 'Start practice · 3 items',
                start_url: '/custom-reviews/1/attempts',
                in_progress: false,
                resume_url: null,
                note: null,
            },
        });

        /**
         * The server's half of a partial reload, applied the way Inertia
         * applies it: only the props the page asked for reach the screen, so
         * a practice card left out of the request keeps its old count.
         */
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation((options) => {
                const only = (options?.only ?? []) as string[];
                const next: Partial<CustomReviewPageData> = {};

                if (only.includes('review')) {
                    next.review = summary({
                        status: 'finished',
                        status_label: 'Finished',
                        item_count: 2,
                        item_count_label: '2 items',
                    });
                }

                if (only.includes('practice')) {
                    next.practice = {
                        can_start: true,
                        eligible_count: 2,
                        start_label: 'Start practice · 2 items',
                        start_url: '/custom-reviews/1/attempts',
                        in_progress: false,
                        resume_url: null,
                        note: null,
                    };
                }

                void wrapper.setProps(next);
            });

        expect(actionNamed(wrapper, 'Start practice · 3 items')).toBeDefined();

        const remove = wrapper
            .findAll('button')
            .find((candidate) =>
                (candidate.attributes('aria-label') ?? '').startsWith(
                    'Remove the',
                ),
            );

        expect(remove).toBeDefined();

        await remove?.trigger('click');
        await flushPromises();

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: expect.arrayContaining(['practice']),
            }),
        );
        expect(actionNamed(wrapper, 'Start practice · 2 items')).toBeDefined();
    });
});

describe('the attempt history pages', () => {
    function historyAttempt(id: number): CustomReviewAttemptHistoryData {
        return {
            id,
            number: id,
            number_label: `${id}th attempt`,
            status: 'completed',
            status_label: 'Completed',
            started_at: '2026-10-12T08:00:00.000000Z',
            finished_at: '2026-10-12T08:05:00.000000Z',
            counts: {
                hard: 0,
                okay: 0,
                easy: 0,
                skipped: 0,
                rated: 0,
                unrated: 0,
            },
            items: [],
        };
    }

    const firstPage = Array.from({ length: 10 }, (_, index) =>
        historyAttempt(index + 1),
    );

    it('loads the next page when the learner asks for more', async () => {
        vi.mocked(loadAttemptHistory).mockResolvedValue({
            attempts: [historyAttempt(11), historyAttempt(12)],
            next_page: null,
            total: 12,
        });

        const wrapper = mountPage({
            attempts: firstPage,
            attempt_total: 12,
            attempts_next_page: 2,
        });

        expect(wrapper.text()).toContain('Showing 10 of 12 attempts.');

        const loadMore = actionNamed(wrapper, 'Load more');

        expect(loadMore).toBeDefined();

        await loadMore?.trigger('click');
        await flushPromises();

        expect(loadAttemptHistory).toHaveBeenCalledWith(1, 2);
        expect(wrapper.findAll('tbody tr')).toHaveLength(12);
        expect(wrapper.text()).not.toContain('Showing 10 of 12 attempts.');
        expect(actionNamed(wrapper, 'Load more')).toBeUndefined();
    });

    it('keeps the rows on screen when the next page does not arrive', async () => {
        vi.mocked(loadAttemptHistory).mockRejectedValue(
            new Error('Loading the attempt history failed (500).'),
        );

        const wrapper = mountPage({
            attempts: firstPage,
            attempt_total: 12,
            attempts_next_page: 2,
        });

        await actionNamed(wrapper, 'Load more')?.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(
            'Loading the attempt history failed (500).',
        );
        expect(wrapper.findAll('tbody tr')).toHaveLength(10);
        expect(actionNamed(wrapper, 'Load more')).toBeDefined();
    });
});

describe('the kinds a review allows', () => {
    /** The page's own words for what a change takes away, said before saving. */
    function bodyText(): string {
        return (document.body.textContent ?? '').replace(/\s+/gu, ' ').trim();
    }

    function buttonWith(text: string): HTMLButtonElement | undefined {
        return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
            (button) => button.textContent?.includes(text),
        );
    }

    /** Chooses kinds with the control, the way a learner does. */
    async function chooseKinds(
        wrapper: Wrapper,
        kinds: string[],
    ): Promise<void> {
        wrapper
            .findComponent(CustomReviewKindsControl)
            .vm.$emit('change', kinds);
        await flushPromises();
    }

    /** Chooses kinds and agrees to the change the page asks about. */
    async function chooseAndConfirm(
        wrapper: Wrapper,
        kinds: string[],
    ): Promise<void> {
        await chooseKinds(wrapper, kinds);
        buttonWith('Change kinds')?.click();
        await flushPromises();
    }

    it('warns what a change takes away before saving it', async () => {
        const wrapper = mountPage();

        await chooseKinds(wrapper, ['vocabulary', 'phrase']);

        expect(bodyText()).toContain('Change the kinds this review allows?');
        expect(bodyText()).toContain('This review will allow Words + Phrases.');
        expect(bodyText()).toContain(
            'Grammar notes + Mistakes + Questions can no longer be added here, though nothing already in the review changes.',
        );
        expect(bodyText()).toContain(
            'Past attempts keep the items they started with.',
        );
    });

    it('says a wider choice lets the kinds left out back in', async () => {
        const wrapper = mountPage({
            review: summary({
                allowed_kinds: ['vocabulary'],
                allowed_kinds_label: 'Words only',
            }),
        });

        await chooseKinds(wrapper, ['vocabulary', 'phrase']);

        expect(bodyText()).toContain('This review will allow Words + Phrases.');
        expect(bodyText()).toContain(
            'The kinds left out can be added here again.',
        );
        expect(bodyText()).not.toContain('can no longer be added here');
    });

    it('keeps the current kinds, and saves nothing, when the learner says so', async () => {
        const wrapper = mountPage();

        await chooseKinds(wrapper, ['vocabulary']);
        buttonWith('Keep the current kinds')?.click();
        await flushPromises();

        expect(updateReviewKinds).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('All kinds');
    });

    it('shows the current choice and saves a new one whole', async () => {
        vi.mocked(updateReviewKinds).mockResolvedValue(
            summary({
                allowed_kinds: ['vocabulary'],
                allowed_kinds_label: 'Words only',
            }),
        );

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('All kinds');

        await chooseAndConfirm(wrapper, ['vocabulary']);

        expect(updateReviewKinds).toHaveBeenCalledWith(1, ['vocabulary']);
        expect(wrapper.text()).toContain('Words only');
        expect(wrapper.text()).not.toContain('All kinds');
    });

    it('puts the previous choice back when the server refuses', async () => {
        vi.mocked(updateReviewKinds).mockRejectedValue(
            new Error('Remove the 2 phrase items first.'),
        );

        const wrapper = mountPage();

        await chooseAndConfirm(wrapper, ['vocabulary']);

        expect(wrapper.text()).toContain('All kinds');
        expect(wrapper.text()).toContain('Remove the 2 phrase items first.');
    });
});
