import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import CustomReviewCard from '@/components/CustomReviewCard.vue';
import CustomReviewFilterBar from '@/components/CustomReviewFilterBar.vue';
import LibrarySearch from '@/components/LibrarySearch.vue';
import Index from '@/pages/custom-reviews/Index.vue';
import { updateReviewStatus } from '@/lib/custom-review';
import { defaultCustomReviewFilters } from '@/lib/custom-review-listing';
import type {
    CustomReviewCardData,
    CustomReviewFiltersState,
    CustomReviewSummary,
    CustomReviewsPageData,
} from '@/types';

vi.mock('@/lib/custom-review', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/custom-review')>()),
    updateReviewStatus: vi.fn(),
}));

function card(
    overrides: Partial<CustomReviewCardData> = {},
): CustomReviewCardData {
    return {
        id: 1,
        name: 'Morning set',
        description: null,
        description_excerpt: null,
        status: 'draft',
        status_label: 'Draft',
        allowed_kinds: null,
        allowed_kinds_label: 'All kinds',
        item_count: 2,
        item_count_label: '2 items',
        too_many_items: false,
        too_many_label: null,
        created_at: '2026-10-12T08:00:00.000000Z',
        attempt_count: 0,
        has_attempts: false,
        attempt_number_label: 'No attempts yet',
        previous_attempt_status: null,
        previous_attempt_label: null,
        attempt_summary_label: 'No attempts yet',
        view_history_url: null,
        can_start: false,
        eligible_count: 0,
        start_label: null,
        start_url: null,
        in_progress: false,
        resume_url: null,
        practice_note: 'Mark this review Finished to practise it.',
        delete_url: '/custom-reviews/1',
        ...overrides,
    };
}

function pageProps(
    overrides: Partial<CustomReviewsPageData> = {},
): CustomReviewsPageData {
    const reviews = overrides.reviews ?? [card()];

    return {
        reviews,
        next_cursor: null,
        has_more: false,
        total: reviews.length,
        all_total: reviews.length,
        per_page: 20,
        filters: defaultCustomReviewFilters(),
        next_review_name: 'Custom Review 1',
        notice: null,
        ...overrides,
    };
}

function mountPage(overrides: Partial<CustomReviewsPageData> = {}) {
    return mount(Index, {
        attachTo: document.body,
        props: pageProps(overrides),
    });
}

type Wrapper = ReturnType<typeof mountPage>;

/** What the server answers a status move with. */
function summary(
    overrides: Partial<CustomReviewSummary> = {},
): CustomReviewSummary {
    return {
        id: 1,
        name: 'Morning set',
        description: null,
        status: 'finished',
        status_label: 'Finished',
        allowed_kinds: null,
        allowed_kinds_label: 'All kinds',
        item_count: 2,
        item_count_label: '2 items',
        too_many_items: false,
        too_many_label: null,
        ...overrides,
    };
}

/** The action whose label reads exactly this, if it is on the page. */
function actionNamed(wrapper: Wrapper, label: string) {
    return wrapper
        .findAll('button, a')
        .find((candidate) => candidate.text().trim() === label);
}

/** Moves a card with its own status control, the way a learner does. */
function choose(
    wrapper: Wrapper,
    name: string,
    status: 'draft' | 'finished',
): void {
    const card = wrapper
        .findAllComponents(CustomReviewCard)
        .find((candidate) => candidate.props('review').name === name);

    card?.vm.$emit('change-status', card.props('review'), status);
}

function lastRequest(get: { mock: { calls: unknown[][] } }): {
    url: string;
    options: Record<string, unknown>;
} {
    const call = get.mock.calls.at(-1);

    if (call === undefined) {
        throw new Error('No listing request was made.');
    }

    return {
        url: String(call[0]),
        options: (call[2] ?? {}) as Record<string, unknown>,
    };
}

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    window.history.replaceState(null, '', '/');
});

describe('the Custom reviews page', () => {
    it('says how many of the whole list are showing when narrowed', () => {
        const wrapper = mountPage({
            reviews: [card(), card({ id: 2 }), card({ id: 3 })],
            total: 3,
            all_total: 12,
            filters: {
                ...defaultCustomReviewFilters(),
                q: 'cafe',
            },
        });

        expect(wrapper.text()).toContain('3 of 12 custom reviews');
    });

    it('counts the whole list when nothing narrows it', () => {
        const wrapper = mountPage({
            reviews: [card(), card({ id: 2 })],
            total: 2,
            all_total: 12,
        });

        expect(wrapper.text()).toContain('12 custom reviews');
        expect(wrapper.text()).not.toContain('of 12');
    });

    it('starts a review from a dialog that offers its name and kinds', async () => {
        const wrapper = mountPage();
        const button = wrapper
            .findAll('button')
            .find((candidate) =>
                candidate.text().includes('New custom review'),
            );

        await button?.trigger('click');
        await flushPromises();

        const name =
            document.querySelector<HTMLInputElement>('#new-review-name');

        expect(name?.value).toBe('Custom Review 1');
        expect(document.body.textContent).toContain('Kinds this review allows');
        expect(document.body.textContent).toContain('Create review');
    });

    it('shows a card for every review with its practice note', () => {
        const wrapper = mountPage({
            reviews: [
                card({ name: 'Morning set' }),
                card({
                    id: 2,
                    name: 'Evening set',
                    status: 'finished',
                    status_label: 'Finished',
                    practice_note: null,
                    can_start: true,
                }),
            ],
            total: 2,
            all_total: 2,
        });

        expect(wrapper.text()).toContain('Morning set');
        expect(wrapper.text()).toContain(
            'Mark this review Finished to practise it.',
        );
        expect(wrapper.text()).toContain('Evening set');
    });

    it('sends a status choice through the address, replacing history', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mountPage();
        const bar = wrapper.findComponent(CustomReviewFilterBar);

        bar.vm.$emit('statuses', ['draft', 'finished']);

        const request = lastRequest(get);

        expect(request.url).toContain('status=draft%2Cfinished');
        expect(request.options).toMatchObject({
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    });

    it('sends the order the learner chose without a chip', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mountPage();
        const bar = wrapper.findComponent(CustomReviewFilterBar);

        bar.vm.$emit('sort', 'name');

        expect(lastRequest(get).url).toContain('sort=name');
    });

    it('sends a search through the address, keeping the statuses', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const filters: CustomReviewFiltersState = {
            q: '',
            statuses: ['draft'],
            sort: 'name',
        };
        const wrapper = mountPage({ filters });
        const search = wrapper.findComponent(LibrarySearch);

        search.vm.$emit('search', '  cafe  ');

        const request = lastRequest(get);

        expect(request.url).toContain('q=cafe');
        expect(request.url).toContain('status=draft');
        expect(request.url).toContain('sort=name');
    });

    it('clears only the statuses and keeps the search and order', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mountPage({
            filters: { q: 'cafe', statuses: ['draft'], sort: 'created' },
        });

        wrapper.findComponent(CustomReviewFilterBar).vm.$emit('clear-statuses');

        const request = lastRequest(get);

        expect(request.url).toContain('q=cafe');
        expect(request.url).toContain('sort=created');
        expect(request.url).not.toContain('status=');
    });

    it('clears only the search and keeps the statuses and order', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mountPage({
            filters: { q: 'cafe', statuses: ['draft'], sort: 'name' },
        });

        wrapper.findComponent(LibrarySearch).vm.$emit('search', '');

        const request = lastRequest(get);

        expect(request.url).not.toContain('q=');
        expect(request.url).toContain('status=draft');
        expect(request.url).toContain('sort=name');
    });

    it('welcomes a learner with no reviews at all', () => {
        const wrapper = mountPage({
            reviews: [],
            total: 0,
            all_total: 0,
        });

        expect(wrapper.text()).toContain('No custom reviews yet');
        expect(wrapper.text()).toContain('0 custom reviews');
    });

    it('says, in the server’s words, what a delete answered with', () => {
        const info = vi
            .spyOn(toast, 'info')
            .mockImplementation(() => 'notice-1');

        mountPage({ notice: 'Morning set was deleted.' });

        expect(info).toHaveBeenCalledWith('Morning set was deleted.');
    });

    it('says nothing when no delete just answered', () => {
        const info = vi
            .spyOn(toast, 'info')
            .mockImplementation(() => 'notice-1');

        mountPage();

        expect(info).not.toHaveBeenCalled();
    });

    it('offers Start practice as soon as a card is marked Finished', async () => {
        vi.mocked(updateReviewStatus).mockResolvedValue(summary());
        vi.stubGlobal(
            'fetch',
            vi.fn().mockRejectedValue(new Error('the list could not be read')),
        );

        const wrapper = mountPage({
            reviews: [
                card({
                    eligible_count: 2,
                    practice_note: 'Mark this review Finished to practise it.',
                }),
            ],
        });

        expect(
            actionNamed(wrapper, 'Start practice · 2 items'),
        ).toBeUndefined();

        choose(wrapper, 'Morning set', 'finished');
        await flushPromises();

        expect(actionNamed(wrapper, 'Start practice · 2 items')).toBeDefined();
        expect(wrapper.text()).not.toContain(
            'Mark this review Finished to practise it.',
        );
    });

    it('takes Start practice away again when a card goes back to Draft', async () => {
        vi.mocked(updateReviewStatus).mockResolvedValue(
            summary({ status: 'draft', status_label: 'Draft' }),
        );
        vi.stubGlobal(
            'fetch',
            vi.fn().mockRejectedValue(new Error('the list could not be read')),
        );

        const wrapper = mountPage({
            reviews: [
                card({
                    status: 'finished',
                    status_label: 'Finished',
                    eligible_count: 2,
                    can_start: true,
                    start_label: 'Start practice · 2 items',
                    start_url: '/custom-reviews/1/attempts',
                    practice_note: null,
                }),
            ],
        });

        expect(actionNamed(wrapper, 'Start practice · 2 items')).toBeDefined();

        choose(wrapper, 'Morning set', 'draft');
        await flushPromises();

        expect(
            actionNamed(wrapper, 'Start practice · 2 items'),
        ).toBeUndefined();
        expect(wrapper.text()).toContain(
            'Mark this review Finished to practise it.',
        );
    });
});
