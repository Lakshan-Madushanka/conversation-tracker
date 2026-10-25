import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import FeedbackTable from '@/components/FeedbackTable.vue';
import type { FeedbackTableConfig, TableRow } from '@/lib/feedback-table';
import { MISTAKES_TABLE } from '@/lib/mistakes';
import { PHRASE_TABLE } from '@/lib/phrases';
import { VOCABULARY_TABLE } from '@/lib/vocabulary';
import type { MistakeEntry } from '@/types';

let wrapper: ReturnType<typeof mountTable> | null = null;

/** A filled row, ready to be shown in the table. */
function row(overrides: Partial<MistakeEntry> = {}): MistakeEntry {
    return {
        id: 'r1',
        text: 'I go',
        correction_html: '<p>I went</p>',
        reason: '',
        ...overrides,
    };
}

function mountTable(
    entries: TableRow[] = [],
    config: FeedbackTableConfig = MISTAKES_TABLE,
) {
    const component = mount(FeedbackTable, {
        attachTo: document.body,
        props: {
            entries,
            config,
            label: 'Mistakes for question 1',
            fieldId: 'field-mistake',
            placeholder: 'e.g. I go → I went',
        },
    });

    wrapper = component;

    return component;
}

/** Mount the same component as the vocabulary box rather than the mistakes box. */
function mountVocabulary(entries: TableRow[] = []) {
    const component = mount(FeedbackTable, {
        attachTo: document.body,
        props: {
            entries,
            config: VOCABULARY_TABLE,
            label: 'New vocabulary for question 1',
            fieldId: 'field-vocabulary',
            placeholder: 'e.g. reservation - a booking',
        },
    });

    wrapper = component;

    return component;
}

/** Mount the same component as the phrases box rather than the mistakes box. */
function mountPhrases(entries: TableRow[] = []) {
    const component = mount(FeedbackTable, {
        attachTo: document.body,
        props: {
            entries,
            config: PHRASE_TABLE,
            label: 'New phrases for question 1',
            fieldId: 'field-phrase',
            placeholder: 'e.g. a long way - far away',
        },
    });

    wrapper = component;

    return component;
}

/** Build a paste event carrying plain text, as a real clipboard would. */
function pasteEvent(text: string, html = ''): Event {
    const event = new Event('paste', { bubbles: true, cancelable: true });

    Object.defineProperty(event, 'clipboardData', {
        value: {
            getData: (type: string) => (type === 'text/html' ? html : text),
        },
    });

    return event;
}

async function pasteInto(
    component: ReturnType<typeof mountTable>,
    text: string,
): Promise<void> {
    component.get('#field-mistake-add').element.dispatchEvent(pasteEvent(text));
    await component.vm.$nextTick();
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('displaying entries', () => {
    it('shows each row read-only, with its three columns', () => {
        const component = mountTable([
            row({
                text: 'I go',
                correction_html: '<p>I went</p>',
                reason: 'Past tense',
            }),
        ]);

        const cells = component.findAll('td');

        expect(cells[0].text()).toContain('I go');
        expect(cells[1].text()).toContain('I went');
        expect(cells[2].text()).toContain('Past tense');
        // Read-only mode shows no inputs for the row's columns.
        expect(component.findAll('input')).toHaveLength(0);

        // The add field is the only input until a row is edited.
        expect(component.find('#field-mistake-add').exists()).toBe(true);
    });

    it('shows an em dash for a column that holds nothing', () => {
        const component = mountTable([
            row({ text: 'I go', correction_html: '', reason: '' }),
        ]);

        const cells = component.findAll('td');

        expect(cells[1].text()).toContain('—');
        expect(cells[2].text()).toContain('—');
    });

    it('is a bordered table with column headers', () => {
        const component = mountTable([row()]);

        const table = component.get('table');

        // The border sits on the wrapper, so header and records share it.
        expect(table.element.parentElement?.classList.contains('border')).toBe(
            true,
        );
        expect(table.findAll('th').map((header) => header.text())).toEqual([
            'What I said',
            'Correction',
            'Why (optional)',
            'Actions',
        ]);
    });
});

describe('editing a row', () => {
    it('shows every column as an editable input when the edit icon is used', async () => {
        const component = mountTable([
            row({
                text: 'I go',
                correction_html: '<p>I went</p>',
                reason: 'Past tense',
            }),
        ]);

        await component
            .get('button[aria-label^="Edit row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        const text = component.get('#field-mistake-text-r1');
        const reason = component.get('#field-mistake-reason-r1');

        expect((text.element as HTMLInputElement).value).toBe('I go');
        expect((reason.element as HTMLInputElement).value).toBe('Past tense');
        // The correction is a rich text editor, not a plain input.
        expect(component.findAll('.rich-text-editor')).toHaveLength(1);
    });

    it('emits the new value as a column is typed into', async () => {
        const component = mountTable([row()]);

        await component
            .get('button[aria-label^="Edit row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        await component.get('#field-mistake-text-r1').setValue('I goed');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            row({ text: 'I goed' }),
        ]);
    });

    it('goes back to the read-only view once editing is done', async () => {
        const component = mountTable([row()]);

        await component
            .get('button[aria-label^="Edit row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        await component
            .get('button[aria-label="Done editing row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        expect(component.findAll('input')).toHaveLength(0);
        expect(component.text()).toContain('I go');
    });

    it('leaves edit mode on Escape', async () => {
        const component = mountTable([row()]);

        await component
            .get('button[aria-label^="Edit row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        await component
            .get('#field-mistake-text-r1')
            .trigger('keydown', { key: 'Escape' });
        await component.vm.$nextTick();

        expect(component.findAll('input')).toHaveLength(0);
    });

    it('opens a newly added blank row for typing straight away', async () => {
        const component = mountTable([row()]);

        await component
            .findAll('button')
            .find((candidate) => candidate.text().includes('Add row'))!
            .trigger('click');
        await component.vm.$nextTick();

        const added = component
            .emitted('update:entries')
            ?.at(-1)?.[0] as MistakeEntry[];

        expect(added).toHaveLength(2);
        expect(added[1].text).toBe('');

        // The parent feeds the new rows back, and the new one is open.
        await component.setProps({ entries: added });
        await component.vm.$nextTick();

        expect(
            component.find(`#field-mistake-text-${added[1].id}`).exists(),
        ).toBe(true);
    });
});

describe('removing a row', () => {
    it('removes it at once and offers an undo', async () => {
        const component = mountTable([
            row(),
            row({ id: 'r2', text: 'she go' }),
        ]);

        await component
            .get('button[aria-label="Remove row 2: she go"]')
            .trigger('click');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            row(),
        ]);
        expect(component.text()).toContain('Row removed.');

        await component
            .get('button[aria-label="Undo the removal"]')
            .trigger('click');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            row(),
            row({ id: 'r2', text: 'she go' }),
        ]);
    });

    it('closes edit mode when the row being edited is removed', async () => {
        const component = mountTable([row()]);

        await component
            .get('button[aria-label^="Edit row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        await component
            .get('button[aria-label^="Remove row 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        expect(component.findAll('input')).toHaveLength(0);
    });
});

describe('pasting rows', () => {
    it('adds one row per pasted line and shows them read-only', async () => {
        const component = mountTable();

        await pasteInto(component, 'I go → I went\nshe go → she went');

        const added = component
            .emitted('update:entries')
            ?.at(-1)?.[0] as MistakeEntry[];

        expect(added).toHaveLength(2);
        expect(added[0].text).toBe('I go');
        expect(added[0].correction_html).toBe('I went');
        expect(component.text()).toContain('Pasted 2 rows.');
        expect(component.text()).toContain('Undo');
        // Pasted rows land read-only, not open for editing.
        expect(component.findAll('input')).toHaveLength(0);
    });
});

describe('the vocabulary table', () => {
    it('shows the word, meaning and example columns', () => {
        const component = mountVocabulary([
            {
                id: 'v1',
                text: 'reservation',
                meaning: 'a booking',
                example_html: '<p>I made a reservation.</p>',
            },
        ]);

        expect(component.findAll('th').map((header) => header.text())).toEqual([
            'Word',
            'Meaning',
            'Example (optional)',
            'Actions',
        ]);

        const cells = component.findAll('td');

        expect(cells[0].text()).toContain('reservation');
        expect(cells[1].text()).toContain('a booking');
        expect(cells[2].text()).toContain('I made a reservation.');
    });

    it('asks for a meaning when a row has none', () => {
        const component = mountVocabulary([
            {
                id: 'v1',
                text: 'reservation',
                meaning: '',
                example_html: '',
            },
        ]);

        expect(component.text()).toContain('Add the meaning.');
    });

    it('edits a row whose example was saved as one piece of markup', async () => {
        const component = mountVocabulary([
            {
                id: 'v1',
                text: 'reservation',
                meaning: 'a booking',
                example_html: '<p>I made a reservation.</p>',
            },
        ]);

        await component
            .get('[aria-label="Edit row 1: reservation"]')
            .trigger('click');

        const example = component.get('#field-vocabulary-example_html-v1');

        expect(component.text()).toContain('I made a reservation.');
        expect(example.attributes('aria-label')).toBe(
            'Add an entry to Example (optional), row 1 of New vocabulary for question 1',
        );
    });

    it('shows each example entry with the formatting it was saved with', () => {
        const component = mountVocabulary([
            {
                id: 'v1',
                text: 'reservation',
                meaning: 'a booking',
                example_html: [
                    {
                        id: 'e1',
                        text: 'I made a reservation.',
                        html: 'I made a <strong>reservation</strong>.',
                    },
                ],
            },
        ]);

        const example = component.findAll('td')[2];

        expect(example.text()).toContain('I made a reservation.');
        expect(example.html()).toContain('<strong>reservation</strong>');
    });

    it('tags a repeated word as a duplicate even with a different meaning', () => {
        const component = mountVocabulary([
            {
                id: 'v1',
                text: 'reservation',
                meaning: 'a booking',
                example_html: '',
            },
            {
                id: 'v2',
                text: 'Reservation',
                meaning: 'something else',
                example_html: '',
            },
        ]);

        expect(component.text()).toContain('Duplicate');
    });
});

describe('the phrases table', () => {
    it('shows the phrase, meaning and example columns', () => {
        const component = mountPhrases([
            {
                id: 'p1',
                text: 'Could I have the bill, please?',
                meaning: 'a polite way to ask',
                example_html: '<p>Could I have <em>the bill</em>, please?</p>',
            },
        ]);

        expect(component.findAll('th').map((header) => header.text())).toEqual([
            'Phrase',
            'Meaning or use',
            'Example (optional)',
            'Actions',
        ]);

        const cells = component.findAll('td');

        expect(cells[0].text()).toContain('Could I have the bill, please?');
        expect(cells[1].text()).toContain('a polite way to ask');
        expect(cells[2].text()).toContain('Could I have the bill, please?');
    });

    it('never asks for a meaning when a row has none', () => {
        const component = mountPhrases([
            {
                id: 'p1',
                text: 'What do you recommend?',
                meaning: '',
                example_html: '',
            },
        ]);

        expect(component.text()).not.toContain('attention');
    });

    it('asks to split a row that holds more than one separator', () => {
        const component = mountPhrases([
            {
                id: 'p1',
                text: 'a - b - c',
                meaning: '',
                example_html: '',
            },
        ]);

        expect(component.text()).toContain(
            'More than one separator found. Please split it yourself.',
        );
    });

    it('tags a repeated phrase as a duplicate even with a different meaning', () => {
        const component = mountPhrases([
            {
                id: 'p1',
                text: 'a long way',
                meaning: 'far away',
                example_html: '',
            },
            {
                id: 'p2',
                text: 'A Long Way',
                meaning: 'something else',
                example_html: '',
            },
        ]);

        expect(component.text()).toContain('Duplicate');
    });
});

describe('the paste area first', () => {
    function mountGuided() {
        const component = mount(FeedbackTable, {
            attachTo: document.body,
            props: {
                entries: [row()],
                config: MISTAKES_TABLE,
                label: 'Mistakes for question 1',
                fieldId: 'field-mistake',
                pasteFirst: true,
            },
        });

        wrapper = component;

        return component;
    }

    it('orders the paste area ahead of the rows', () => {
        const component = mountGuided();

        const pasteArea = component.get('#field-mistake-add').element
            .parentElement as HTMLElement;
        const table = component.get('table').element
            .parentElement as HTMLElement;

        // The blocks stay in the order they are written; the classes below
        // are what moves the paste area to the top for this one box.
        expect(pasteArea.className).toContain('order-1');
        expect(table.className).toContain('order-3');
        expect(component.classes()).toContain('flex');
    });

    it('leaves the ordinary layout exactly where it is', () => {
        const component = mountTable([row()]);

        const pasteArea = component.get('#field-mistake-add').element
            .parentElement as HTMLElement;

        expect(component.classes()).toContain('space-y-2');
        expect(pasteArea.className).not.toContain('order-');
    });
});
