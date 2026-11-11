import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CollectionItemPanel from '@/components/CollectionItemPanel.vue';
import { saveItemMark } from '@/lib/collection-marks';
import type { CollectionAppearance, CollectionItem } from '@/types';

vi.mock('@/lib/collection-marks', async () => {
    const actual = await vi.importActual<
        typeof import('@/lib/collection-marks')
    >('@/lib/collection-marks');

    return {
        ...actual,
        saveItemMark: vi.fn(),
    };
});

function appearance(
    overrides: Partial<CollectionAppearance> = {},
): CollectionAppearance {
    return {
        session_id: 7,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: 'entry-1',
        entry_position: 0,
        meaning: 'a liquid food',
        example: 'I want a soup.',
        example_html: '',
        ...overrides,
    };
}

function item(overrides: Partial<CollectionItem> = {}): CollectionItem {
    return {
        key: 'soup',
        title: 'soup',
        meaning: 'a liquid food',
        example: 'I want a soup.',
        example_html: '',
        latest_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 1,
        needs_attention: false,
        attention_reason: null,
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        source: 'session',
        notebook: null,
        rows: [],
        starred: false,
        status: null,
        has_notes: false,
        appearances: [appearance()],
        ...overrides,
    };
}

let mounted: ReturnType<typeof mount> | undefined;

async function openPanel(chosen: CollectionItem): Promise<void> {
    mounted = mount(CollectionItemPanel, {
        attachTo: document.body,
        props: { item: chosen },
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

describe('the opened word panel', () => {
    it('shows the word, its meaning and its example', async () => {
        await openPanel(item());

        expect(text()).toContain('soup');
        expect(text()).toContain('a liquid food');
        expect(text()).toContain('I want a soup.');
    });

    it('moves focus to the word and gives it a heading', async () => {
        await openPanel(item());

        const heading = document.querySelector('h2');

        expect(heading?.textContent).toBe('soup');
        expect(document.activeElement).toBe(heading);
    });

    it('asks for a meaning when none was saved', async () => {
        await openPanel(
            item({
                meaning: '',
                example: '',
                needs_attention: true,
                appearances: [appearance({ meaning: '', example: '' })],
            }),
        );

        expect(text()).toContain('Add the meaning');
        expect(text()).toContain('Needs attention');
    });

    it('offers the learner marks, with the star named by the word', async () => {
        await openPanel(item());

        const star = [...document.querySelectorAll('button')].find(
            (button) =>
                button.getAttribute('aria-label') === 'Star the word soup',
        );

        expect(star).not.toBeNull();
        expect(text()).toContain('Your notes');
    });

    it('saves the kind of star that was asked for and passes what the server saved upward', async () => {
        const chosen = item();

        vi.mocked(saveItemMark).mockResolvedValue({
            starred: true,
            status: null,
            has_notes: false,
            notes_version: 0,
            notes_html: '',
        });

        await openPanel(chosen);

        const star = [...document.querySelectorAll('button')].find(
            (button) =>
                button.getAttribute('aria-label') === 'Star the word soup',
        );
        star?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(vi.mocked(saveItemMark)).toHaveBeenCalledWith(
            'vocabulary',
            'soup',
            true,
            null,
        );
        expect(mounted?.emitted('mark-saved')?.[0]).toEqual([
            chosen,
            {
                starred: true,
                status: null,
                has_notes: false,
                notes_version: 0,
                notes_html: '',
            },
        ]);
    });

    it('shows every different meaning and example with the session it came from', async () => {
        await openPanel(
            item({
                meaning: 'a bowl of soup',
                example: 'The soup was hot.',
                appearances: [
                    appearance({
                        meaning: 'a bowl of soup',
                        example: 'The soup was hot.',
                        question_position: 2,
                    }),
                    appearance({
                        meaning: 'a liquid food',
                        example: 'I want a soup.',
                        question_position: 1,
                    }),
                ],
            }),
        );

        expect(text()).toContain('All meanings and examples');
        expect(text()).toContain('a bowl of soup');
        expect(text()).toContain('a liquid food');
        expect(text()).toContain('The soup was hot.');
        expect(text()).toContain('I want a soup.');
        expect(text()).toContain('Ordering at a restaurant');
    });

    it('lists every question the word was seen in once', async () => {
        await openPanel(
            item({
                seen_count: 2,
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

    it('links a notebook word back to the notebook it was gathered from', async () => {
        await openPanel(
            item({
                session_id: null,
                session_date: null,
                question_position: null,
                seen_count: null,
                source: 'notebook',
                notebook: { id: 5, name: 'Kitchen', topic: 'Food' },
                rows: [
                    {
                        entry_id: 'row-a',
                        entry_position: 2,
                        meaning: 'a liquid food',
                        example: 'I want a soup.',
                        example_html: '',
                    },
                ],
                appearances: [],
            }),
        );

        expect(text()).toContain('From notebook');
        expect(text()).toContain('Kitchen');
        expect(text()).toContain('Added');
        expect(text()).not.toContain('Seen 0 times');
        expect(sessionLinks()).toHaveLength(0);

        const open = anchors().find((anchor) =>
            anchor.textContent?.includes('Open notebook'),
        );
        const href = open?.getAttribute('href') ?? '';

        expect(href).toContain('/notebooks/5?');
        expect(href).toContain('from=collections');
        expect(href).toContain('field=entry');
        expect(href).toContain('entry=row-a');
        expect(href).toContain('position=2');
    });

    it('shows every meaning and example a notebook row holds', async () => {
        await openPanel(
            item({
                source: 'notebook',
                notebook: { id: 5, name: 'Kitchen', topic: 'Food' },
                session_id: null,
                question_position: null,
                seen_count: null,
                appearances: [],
                rows: [
                    {
                        entry_id: 'row-a',
                        entry_position: 0,
                        meaning: 'a liquid food',
                        example: 'I want a soup.',
                        example_html: '',
                    },
                    {
                        entry_id: 'row-b',
                        entry_position: 1,
                        meaning: 'a bowl of soup',
                        example: 'The soup was hot.',
                        example_html: '',
                    },
                ],
            }),
        );

        expect(text()).toContain('All meanings and examples');
        expect(text()).toContain('a liquid food');
        expect(text()).toContain('a bowl of soup');
        expect(text()).toContain('I want a soup.');
        expect(text()).toContain('The soup was hot.');
        expect(text()).not.toContain('Question');
    });

    it('links each sighting back to its exact row', async () => {
        await openPanel(item());

        const href = sessionLinks()[0]?.getAttribute('href') ?? '';

        expect(href).toContain('/sessions/7?');
        expect(href).toContain('question=1');
        expect(href).toContain('field=entry');
        expect(href).toContain('type=vocabulary');
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
});
