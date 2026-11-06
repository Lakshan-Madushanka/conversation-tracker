import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import ReviewRecentResults from '@/components/ReviewRecentResults.vue';
import { shortDate } from '@/lib/format';
import type { RecentReviewData } from '@/types';

const finishedAt = '2026-11-03T18:42:11.000000Z';

function review(overrides: Partial<RecentReviewData> = {}): RecentReviewData {
    return {
        id: 41,
        type: 'daily',
        type_label: 'Daily review',
        finished_at: finishedAt,
        rated: 5,
        counts: { hard: 1, okay: 1, easy: 3 },
        url: '/review/41/results',
        ...overrides,
    };
}

function mountResults(reviews: RecentReviewData[]) {
    return mount(ReviewRecentResults, { props: { reviews } });
}

describe('a learner with no results yet', () => {
    it('says where the results will come from', () => {
        const wrapper = mountResults([]);

        expect(wrapper.text()).toContain('Recent results');
        expect(wrapper.text()).toContain(
            'Your results appear here after your first review.',
        );
        expect(wrapper.findAll('a')).toHaveLength(0);
    });
});

describe('one finished sitting', () => {
    it('shows the local date, the type in words and the rating counts', () => {
        const wrapper = mountResults([review()]);

        expect(wrapper.text()).toContain(shortDate(finishedAt));
        expect(wrapper.text()).toContain('Daily review');
        expect(wrapper.text()).toContain('5 items rated');
        expect(wrapper.text()).toContain('Easy 3 · Okay 1 · Hard 1');
    });

    it('agrees with itself on a single rated item', () => {
        const wrapper = mountResults([review({ rated: 1 })]);

        expect(wrapper.text()).toContain('1 item rated');
    });

    it('names the custom sitting as what it was', () => {
        const wrapper = mountResults([
            review({ type: 'custom', type_label: 'Custom review' }),
        ]);

        expect(wrapper.text()).toContain('Custom review');
    });

    it('opens the sitting itself from a card and from the table', () => {
        const wrapper = mountResults([review()]);
        const links = wrapper.findAll('a');

        expect(links).toHaveLength(2);
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/review/41/results',
            '/review/41/results',
        ]);
    });

    it('reads every number out loud in the link’s own label', () => {
        const wrapper = mountResults([review()]);

        expect(wrapper.findAll('a')[0]?.attributes('aria-label')).toBe(
            `Open Daily review from ${shortDate(finishedAt)} — 5 items rated, Easy 3 · Okay 1 · Hard 1`,
        );
    });
});

describe('the last few sittings', () => {
    it('keeps them in the order the server sent them', () => {
        const wrapper = mountResults([
            review({ id: 44, finished_at: '2026-11-04T09:00:00.000000Z' }),
            review({ id: 43, finished_at: '2026-11-03T09:00:00.000000Z' }),
            review({ id: 41, finished_at: '2026-11-01T09:00:00.000000Z' }),
        ]);
        const dates = wrapper
            .findAll('tbody tr td:first-child a')
            .map((link) => link.text().trim());

        expect(dates).toEqual([
            shortDate('2026-11-04T09:00:00.000000Z'),
            shortDate('2026-11-03T09:00:00.000000Z'),
            shortDate('2026-11-01T09:00:00.000000Z'),
        ]);
    });

    it('gives the wide screen the same results as a table of numbers', () => {
        const wrapper = mountResults([
            review(),
            review({
                id: 42,
                type: 'custom',
                type_label: 'Custom review',
                rated: 2,
                counts: { hard: 2, okay: 0, easy: 0 },
                url: '/review/42/results',
            }),
        ]);

        const heads = wrapper.findAll('th').map((head) => head.text().trim());

        expect(heads).toEqual([
            'When',
            'Type',
            'Rated',
            'Easy',
            'Okay',
            'Hard',
        ]);

        const rows = wrapper.findAll('tbody tr');

        expect(rows).toHaveLength(2);
        expect(
            rows[1]?.findAll('td').map((cell) => cell.text().trim()),
        ).toEqual([shortDate(finishedAt), 'Custom review', '2', '0', '0', '2']);
    });

    it('counts each rating once, as the saved ratings came out', () => {
        const wrapper = mountResults([
            review({ rated: 8, counts: { hard: 5, okay: 0, easy: 3 } }),
        ]);

        expect(wrapper.text()).toContain('8 items rated');
        expect(wrapper.text()).toContain('Easy 3 · Okay 0 · Hard 5');
    });
});
