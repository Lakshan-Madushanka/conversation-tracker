import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CollectionMistakePanel from '@/components/CollectionMistakePanel.vue';
import type {
    CollectionMistakeAppearance,
    CollectionMistakeItem,
} from '@/types';

function appearance(
    overrides: Partial<CollectionMistakeAppearance> = {},
): CollectionMistakeAppearance {
    return {
        session_id: 7,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: 'entry-1',
        entry_position: 0,
        said: 'I am agree',
        correction: 'I agree',
        correction_html: '',
        reason: 'verb tense',
        ...overrides,
    };
}

function item(
    overrides: Partial<CollectionMistakeItem> = {},
): CollectionMistakeItem {
    return {
        key: 'mistake-agree',
        title: 'I am agree',
        correction: 'I agree',
        correction_html: '',
        reason: 'verb tense',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 1,
        worth_practicing: false,
        needs_attention: false,
        attention_reason: null,
        appearances: [appearance()],
        ...overrides,
    };
}

let mounted: ReturnType<typeof mount> | undefined;

async function openPanel(chosen: CollectionMistakeItem): Promise<void> {
    mounted = mount(CollectionMistakePanel, {
        attachTo: document.body,
        props: { item: chosen, tab: 'mistakes' },
    });

    await flushPromises();
    await flushPromises();
}

function text(): string {
    return document.body.textContent ?? '';
}

function anchors(): HTMLAnchorElement[] {
    return [...document.querySelectorAll<HTMLAnchorElement>('a')];
}

function sessionLinks(): HTMLAnchorElement[] {
    return anchors().filter((anchor) =>
        anchor.textContent?.includes('See original session'),
    );
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('the opened mistake panel', () => {
    it('shows what was said, the correction and how often it was seen', async () => {
        await openPanel(item({ seen_count: 2 }));

        expect(text()).toContain('I am agree');
        expect(text()).toContain('I agree');
        expect(text()).toContain('What I said');
        expect(text()).toContain('Correction');
        expect(text()).toContain('Seen 2 times');
    });

    it('moves focus to the heading that names the mistake', async () => {
        await openPanel(item());

        const heading = document.querySelector('h2');

        expect(heading?.textContent).toBe('I am agree');
        expect(document.activeElement).toBe(heading);
    });

    it('asks for a correction when none was saved', async () => {
        await openPanel(
            item({
                correction: '',
                needs_attention: true,
                attention_reason: 'Add the correction.',
            }),
        );

        expect(text()).toContain('Add the correction');
        expect(text()).toContain('Needs attention');
        expect(text()).toContain('Open the original session to fix it');
    });

    it("keeps the correction's bold and italic, never its markup as words", async () => {
        await openPanel(item({ correction_html: '<strong>I agree</strong>' }));

        const heading = document.querySelector('h2');
        const content = document.body.innerHTML ?? '';

        expect(content).toContain('<strong>I agree</strong>');
        expect(heading?.textContent).toBe('I am agree');
    });

    it('shows every different reason with the sessions it was given in', async () => {
        await openPanel(
            item({
                seen_count: 3,
                appearances: [
                    appearance({ reason: 'verb tense', question_position: 1 }),
                    appearance({
                        reason: 'word choice',
                        question_position: 2,
                        entry_id: 'another',
                    }),
                    appearance({
                        topic: 'Talking about the weekend',
                        reason: 'word choice',
                        question_position: 3,
                        entry_id: 'elsewhere',
                    }),
                ],
            }),
        );

        expect(text()).toContain('All reasons');
        expect(text()).toContain('verb tense');
        expect(text()).toContain('word choice');
        expect(text()).toContain('Talking about the weekend');
    });

    it('leaves the reason section out when no appearance carried a reason', async () => {
        await openPanel(
            item({
                reason: '',
                appearances: [appearance({ reason: '' })],
            }),
        );

        expect(text()).not.toContain('All reasons');
        expect(text()).not.toContain('verb tense');
    });

    it('lists every question the mistake was seen in once', async () => {
        await openPanel(
            item({
                seen_count: 3,
                appearances: [
                    appearance({ question_position: 1 }),
                    appearance({ question_position: 1, entry_id: 'another' }),
                    appearance({ question_position: 2, entry_id: 'elsewhere' }),
                ],
            }),
        );

        expect(sessionLinks()).toHaveLength(2);
        expect(text()).toContain('Question 1');
        expect(text()).toContain('Question 2');
    });

    it('links each sighting back to the exact mistake row', async () => {
        await openPanel(item());

        const href = sessionLinks()[0]?.getAttribute('href') ?? '';

        expect(href).toContain('/sessions/7?');
        expect(href).toContain('question=1');
        expect(href).toContain('field=entry');
        expect(href).toContain('type=mistake');
        expect(href).toContain('entry=entry-1');
        expect(href).toContain('position=0');
    });

    it('asks the page to keep the list before leaving for a session', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        await openPanel(item());

        sessionLinks()[0]?.click();

        expect(mounted?.emitted('see-session')).toHaveLength(1);
        expect(visit).toHaveBeenCalled();
    });

    it('leaves the saved list alone for a modified click', async () => {
        await openPanel(item());

        sessionLinks()[0]?.dispatchEvent(
            new MouseEvent('click', { bubbles: true, ctrlKey: true }),
        );

        expect(mounted?.emitted('see-session')).toBeUndefined();
    });

    it('closes when the close button is chosen', async () => {
        await openPanel(item());

        const close = [
            ...document.querySelectorAll<HTMLButtonElement>('button'),
        ].find((button) => button.textContent?.includes('Close'));

        close?.click();
        await flushPromises();

        expect(mounted?.emitted('close')).toHaveLength(1);
    });

    it('labels a mistake worth practicing', async () => {
        await openPanel(item({ worth_practicing: true, seen_count: 3 }));

        expect(text()).toContain('Worth practicing');
    });
});
