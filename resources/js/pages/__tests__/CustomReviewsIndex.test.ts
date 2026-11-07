import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CustomReviewFilterBar from '@/components/CustomReviewFilterBar.vue';
import LibrarySearch from '@/components/LibrarySearch.vue';
import Index from '@/pages/custom-reviews/Index.vue';
import { defaultCustomReviewFilters } from '@/lib/custom-review-listing';
import type {
    CustomReviewCardData,
    CustomReviewFiltersState,
    CustomReviewsPageData,
} from '@/types';

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
        practice_note: 'Mark this review Finished to practise it.',
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
        ...overrides,
    };
}

function mountPage(overrides: Partial<CustomReviewsPageData> = {}) {
    return mount(Index, {
        attachTo: document.body,
        props: pageProps(overrides),
    });
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

    it('offers New custom review as a post to the listing', () => {
        const wrapper = mountPage();
        const button = wrapper
            .findAll('button')
            .find((candidate) =>
                candidate.text().includes('New custom review'),
            );

        expect(button).toBeDefined();
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
});
