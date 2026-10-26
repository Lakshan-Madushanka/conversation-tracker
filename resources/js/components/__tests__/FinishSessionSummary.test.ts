import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import FinishSessionSummary from '@/components/FinishSessionSummary.vue';
import type { AttentionScan } from '@/lib/attention';
import type { FinishSummary } from '@/types';

function summary(overrides: Partial<FinishSummary> = {}): FinishSummary {
    return {
        empty: false,
        session: {
            id: 7,
            status: 'finished',
            finished_at: '2026-10-19T10:00:00Z',
            last_changed_at: '2026-10-19T10:00:00Z',
            archived_at: null,
            version: 4,
            topic: { id: 1, name: 'Travel' },
            questions: [],
        },
        counts: {
            questions: 6,
            words: 12,
            phrases: 4,
            mistakes: 3,
            grammar_notes: 2,
        },
        needs_attention: 0,
        empty_questions_removed: 0,
        ...overrides,
    };
}

const nothingToDo: AttentionScan = { count: 0, first: null };

const somethingToDo: AttentionScan = {
    count: 3,
    first: { questionUid: 'q1', boxType: 'mistake', entryId: 'm1' },
};

/**
 * The dialog is portalled, and the portal fills in on a later tick, so the
 * content is waited for rather than assumed.
 */
async function mountSummary(
    result: FinishSummary = summary(),
    attention: AttentionScan = nothingToDo,
): Promise<VueWrapper> {
    const wrapper = mount(FinishSessionSummary, {
        attachTo: document.body,
        props: { open: true, result, attention },
    });

    await wrapper.vm.$nextTick();

    return wrapper;
}

/** A button by its words, wherever the dialog portal put it. */
function button(label: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll('button')].find(
        (element) => element.textContent?.trim() === label,
    );
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the finished summary', () => {
    it('says what the session holds, in one sentence', async () => {
        await mountSummary();

        expect(document.body.textContent).toContain('Session finished');
        expect(document.body.textContent).toContain(
            '6 questions, 12 new words, 4 phrases, 3 mistakes, and 2 grammar notes',
        );
    });

    it('agrees with one of anything', async () => {
        await mountSummary(
            summary({
                counts: {
                    questions: 1,
                    words: 1,
                    phrases: 1,
                    mistakes: 1,
                    grammar_notes: 1,
                },
            }),
        );

        expect(document.body.textContent).toContain(
            '1 question, 1 new word, 1 phrase, 1 mistake, and 1 grammar note',
        );
    });

    it('mentions the cards that held nothing and were removed', async () => {
        await mountSummary(summary({ empty_questions_removed: 2 }));

        expect(document.body.textContent).toContain(
            '2 question cards held nothing and were removed',
        );
    });

    it('says nothing about removals when every card held something', async () => {
        await mountSummary();

        expect(document.body.textContent).not.toContain('were removed');
    });
});

describe('finishing when something still needs work', () => {
    it('counts the rows and offers a way to them', async () => {
        await mountSummary(summary(), somethingToDo);

        expect(document.body.textContent).toContain(
            '3 rows still need a correction or a meaning',
        );
        expect(button('Review them')).toBeDefined();
    });

    it('takes the learner there and closes', async () => {
        const wrapper = await mountSummary(summary(), somethingToDo);

        button('Review them')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('review')).toHaveLength(1);
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('does not offer the way when every row is complete', async () => {
        await mountSummary(summary(), nothingToDo);

        expect(document.body.textContent).not.toContain('still need');
        expect(button('Review them')).toBeUndefined();
    });
});

describe('closing the summary', () => {
    it('closes on Done', async () => {
        const wrapper = await mountSummary();

        button('Done')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
        expect(wrapper.emitted('review')).toBeUndefined();
    });
});
