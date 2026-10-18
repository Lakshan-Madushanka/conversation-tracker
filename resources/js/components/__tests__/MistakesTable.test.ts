import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import MistakesTable from '@/components/MistakesTable.vue';
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

function mountTable(entries: MistakeEntry[] = []) {
    const component = mount(MistakesTable, {
        attachTo: document.body,
        props: {
            entries,
            label: 'Mistakes for question 1',
            fieldId: 'field-mistake',
            placeholder: 'e.g. I go → I went',
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

        const cells = component.findAll('[role="cell"]');

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

        const cells = component.findAll('[role="cell"]');

        expect(cells[1].text()).toContain('—');
        expect(cells[2].text()).toContain('—');
    });

    it('is a bordered table with column headers', () => {
        const component = mountTable([row()]);

        const table = component.get('[role="table"]');

        expect(table.classes()).toContain('border');
        expect(
            table
                .findAll('[role="columnheader"]')
                .map((header) => header.text()),
        ).toEqual(['What I said', 'Correction', 'Why (optional)', 'Actions']);
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
