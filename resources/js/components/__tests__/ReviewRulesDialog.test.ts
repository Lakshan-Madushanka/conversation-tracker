import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import ReviewRulesDialog from '@/components/review/ReviewRulesDialog.vue';
import type { ReviewRulesData } from '@/types';

const rules: ReviewRulesData = {
    dailySize: 8,
    newItems: 4,
    hardDays: 1,
    okayDays: 3,
    easyGaps: [7, 14, 30, 60],
    learnedRun: 3,
    difficultRun: 2,
    maxSuggestions: 2,
};

/** The dialog is portalled, and the portal fills in on a later tick. */
async function mountDialog(): Promise<VueWrapper> {
    return mount(ReviewRulesDialog, {
        attachTo: document.body,
        props: { rules },
    });
}

async function open(): Promise<VueWrapper> {
    const wrapper = await mountDialog();

    trigger()?.click();
    await wrapper.vm.$nextTick();

    return wrapper;
}

/** The info button, named by the words it carries for a screen reader. */
function trigger(): HTMLButtonElement | undefined {
    return [...document.querySelectorAll('button')].find(
        (element) => element.textContent?.trim() === 'How reviews work',
    );
}

/** Every word the open dialog shows, flattened. */
function spoken(): string {
    return (document.body.textContent ?? '').replace(/\s+/g, ' ');
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the info button', () => {
    it('is a plain button that names itself', async () => {
        await mountDialog();

        expect(trigger()?.textContent).toContain('How reviews work');
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });

    it('says nothing until the learner asks', async () => {
        await mountDialog();

        expect(spoken()).not.toContain('Still difficult');
    });

    it('closes again when the learner is done reading', async () => {
        const wrapper = await open();

        const close = [...document.querySelectorAll('button')].find(
            (element) => element.textContent?.trim() === 'Close',
        );
        close?.click();
        await wrapper.vm.$nextTick();

        // The panel leaves with its animation, so the honest check is its state.
        expect(
            document
                .querySelector('[role="dialog"]')
                ?.getAttribute('data-state'),
        ).toBe('closed');
    });
});

describe('what the dialog explains', () => {
    it('opens with the two ideas it tells apart', async () => {
        await open();

        expect(
            document.querySelector('[role="dialog"]')?.textContent,
        ).toContain('How reviews work');
        expect(spoken()).toContain(
            'the rating you give each card, and the status you choose for an item',
        );
    });

    it('gives the gap each rating sets', async () => {
        await open();

        expect(spoken()).toContain('Hard — again in 1 day.');
        expect(spoken()).toContain('Okay — again in 3 days.');
        expect(spoken()).toContain(
            'Easy — again in 7 days, then 14, then 30, then 60 days.',
        );
    });

    it('says what a status does and what it never does', async () => {
        await open();

        expect(spoken()).toContain(
            'Nothing is ever marked without you saying so.',
        );
        expect(spoken()).toContain(
            'an item you have never reviewed stops coming round as a new one',
        );
        expect(spoken()).toContain(
            'comes ahead of other items that are due the same day',
        );
        expect(spoken()).toContain(
            'a rating never changes your status on its own',
        );
    });

    it('explains the offers and how often they come', async () => {
        await open();

        expect(spoken()).toContain('3 Easy ratings in a row');
        expect(spoken()).toContain('2 Hard ratings in a row');
        expect(spoken()).toContain('At most 2 offers appear at once.');
        expect(spoken()).toContain('"Not now" changes nothing');
        expect(spoken()).toContain('grammar notes are never offered a status');
    });

    it('says how a day’s sitting is filled', async () => {
        await open();

        expect(spoken()).toContain('8 items, and at most 4 of them');
    });
});

describe('the numbers', () => {
    it('are the ones the server sent, not typed in', async () => {
        const wrapper = await mountDialog();

        await wrapper.setProps({
            rules: { ...rules, hardDays: 2, dailySize: 10, learnedRun: 5 },
        });
        trigger()?.click();
        await wrapper.vm.$nextTick();

        expect(spoken()).toContain('Hard — again in 2 days.');
        expect(spoken()).toContain('10 items, and at most 4 of them');
        expect(spoken()).toContain('5 Easy ratings in a row');
    });
});
