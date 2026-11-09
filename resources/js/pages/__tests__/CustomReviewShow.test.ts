import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CustomReviewStatusControl from '@/components/CustomReviewStatusControl.vue';
import { removeItemsFromReview, updateReviewStatus } from '@/lib/custom-review';
import Show from '@/pages/custom-reviews/Show.vue';
import type {
    CustomReviewGroupData,
    CustomReviewItemData,
    CustomReviewPageData,
    CustomReviewSummary,
} from '@/types';

vi.mock('@/lib/custom-review', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/custom-review')>()),
    removeItemsFromReview: vi.fn(),
    updateReviewStatus: vi.fn(),
}));

afterEach(() => {
    vi.restoreAllMocks();
});

function summary(
    overrides: Partial<CustomReviewSummary> = {},
): CustomReviewSummary {
    return {
        id: 1,
        name: 'Morning set',
        description: null,
        status: 'draft',
        status_label: 'Draft',
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
        expanded_attempt: null,
        attempt_error: null,
        delete_url: '/custom-reviews/1',
        ...overrides,
    };
}

function mountPage(overrides: Partial<CustomReviewPageData> = {}) {
    return mount(Show, { props: pageProps(overrides) });
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
