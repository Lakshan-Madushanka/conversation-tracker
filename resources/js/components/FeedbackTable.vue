<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import {
    CheckIcon,
    CopyIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from '@lucide/vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import UndoNotice from '@/components/UndoNotice.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { attentionNoteId } from '@/lib/attention';
import { focusElementById } from '@/lib/dom';
import { exampleEntries, exampleParts } from '@/lib/example';
import {
    cellText,
    cellValue,
    duplicateKey,
    MAX_TABLE_CELL,
    MAX_TABLE_ROWS,
    newRow,
    rowAttention,
    rowsFromPaste,
    type FeedbackTableConfig,
    type TableCells,
    type TableRow,
} from '@/lib/feedback-table';
import { plainTextFromPaste } from '@/lib/paste';
import EntryList from '@/components/EntryList.vue';

/**
 * A table feedback box: a bordered table of rows, one column set per box, as
 * configured by `props.config`. The mistakes box and the vocabulary box are the
 * same component with different columns.
 *
 * Entries are shown read-only until their row is edited, and every row can be
 * removed. Pasting several lines — or a table with the same columns — adds one
 * row each and splits them by the separator rules, then can be undone. A row
 * whose attention column is missing shows the reason beside it, and a row that
 * repeats an earlier one is tagged as a duplicate.
 */
const props = defineProps<{
    entries: TableRow[];
    /** The columns and rules of this table. */
    config: FeedbackTableConfig;
    /** Accessible name for the fields, for example "Mistakes for question 1". */
    label: string;
    /** Unique id for the add field, so focus can be moved to it. */
    fieldId: string;
    placeholder?: string;
    error?: string;
    /**
     * Puts the paste area above the rows instead of below them. Guided mode
     * uses it, so an empty box opens with somewhere to paste straight away.
     */
    pasteFirst?: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:entries', entries: TableRow[]): void;
}>();

const columns = computed(() => props.config.columns);

const draft = ref('');
/** The row being edited, if any; the others stay read-only. */
const editingId = ref<string | null>(null);

/** Set after a paste or a removal, until it is undone or replaced. */
const notice = ref<{ message: string; undoLabel: string } | null>(null);
/** Shown under the add field when a paste could not be added. */
const limitMessage = ref('');
/** Words repeated into the live region so a screen reader hears what happened. */
const announcement = ref('');

/** The entries as they were before the last paste or removal, for undo. */
let restoreTo: TableRow[] | null = null;

const addFieldId = computed(() => `${props.fieldId}-add`);

/** Which rows still need their attention column, and why. */
const attention = computed(() => {
    const map = new Map<string, string>();

    for (const entry of props.entries) {
        const reason = rowAttention(props.config, entry);

        if (reason !== null) {
            map.set(entry.id, reason);
        }
    }

    return map;
});

/** Rows whose columns already appear together earlier in the box. */
const duplicateIds = computed(() => {
    const seen = new Set<string>();
    const duplicates = new Set<string>();

    for (const entry of props.entries) {
        const key = duplicateKey(props.config, entry);

        if (key === '') {
            continue;
        }

        if (seen.has(key)) {
            duplicates.add(entry.id);
        }

        seen.add(key);
    }

    return duplicates;
});

/** True while the add field holds text or a row is being edited. */
const hasDraft = computed(
    () => draft.value.trim() !== '' || editingId.value !== null,
);

defineExpose({ hasDraft });

function replaceEntries(entries: TableRow[]): void {
    emit('update:entries', entries);
}

function say(message: string): void {
    announcement.value = message;
}

function rowFieldId(id: string, key: string): string {
    return `${props.fieldId}-${key}-${id}`;
}

function updateRow(id: string, patch: TableCells): void {
    replaceEntries(
        props.entries.map((entry) =>
            entry.id === id ? { ...entry, ...patch } : entry,
        ),
    );
}

/** Open a row for editing and put the cursor in its first field. */
function startEdit(id: string): void {
    editingId.value = id;
    void nextTick(() => focusElementById(rowFieldId(id, columns.value[0].key)));
}

/** Close the row being edited; its values are already saved as they changed. */
function stopEdit(): void {
    editingId.value = null;
}

/** Add one empty row, opened for typing or pasting into. */
function addBlankRow(): void {
    if (props.entries.length >= MAX_TABLE_ROWS) {
        limitMessage.value = `A box can hold up to ${MAX_TABLE_ROWS} rows. Remove one to add another.`;
        say(limitMessage.value);

        return;
    }

    const row = newRow(props.config);

    replaceEntries([...props.entries, row]);
    limitMessage.value = '';
    say('Empty row added.');
    editingId.value = row.id;
    void nextTick(() =>
        focusElementById(rowFieldId(row.id, columns.value[0].key)),
    );
}

/**
 * Add pasted rows, or explain why they were not added. The text the learner
 * pasted is never thrown away: on failure it stays in the add field.
 */
function addRows(rows: TableCells[], pasted: boolean): boolean {
    if (rows.length === 0) {
        limitMessage.value = '';

        return false;
    }

    const tooLong = rows.some((row) =>
        columns.value.some(
            (column) => cellText(column, row).length > MAX_TABLE_CELL,
        ),
    );

    if (tooLong) {
        keepFailedPaste(pasted, rows);
        limitMessage.value = `Each cell can be up to ${MAX_TABLE_CELL} characters. Split it into two rows.`;
        say(limitMessage.value);

        return false;
    }

    const room = MAX_TABLE_ROWS - props.entries.length;

    if (rows.length > room) {
        keepFailedPaste(pasted, rows);
        limitMessage.value = `A box can hold up to ${MAX_TABLE_ROWS} rows. Remove one to add another.`;
        say(limitMessage.value);

        return false;
    }

    limitMessage.value = '';
    restoreTo = [...props.entries];
    replaceEntries([
        ...props.entries,
        ...rows.map((row) => newRow(props.config, row)),
    ]);

    return true;
}

/**
 * Keep text that could not be added in the add field. A typed draft already
 * holds it; a paste was stopped before it reached the field, so it is put back
 * there — nothing the learner pasted is ever dropped. The line is rebuilt from
 * the first two columns, so re-adding splits it the same way.
 */
function keepFailedPaste(pasted: boolean, rows: TableCells[]): void {
    if (!pasted) {
        return;
    }

    const first = columns.value[0];
    const second = columns.value[1];

    const pastedText = rows
        .map((row) =>
            [cellText(first, row), cellText(second, row)]
                .filter(Boolean)
                .join(' - '),
        )
        .join('\n');

    draft.value =
        draft.value === '' ? pastedText : `${draft.value}\n${pastedText}`;
}

function addFromDraft(): void {
    const { rows } = rowsFromPaste(props.config, draft.value);

    if (!addRows(rows, false)) {
        return;
    }

    draft.value = '';
    say(`${rows.length} ${rows.length === 1 ? 'row' : 'rows'} added.`);
    focusElementById(addFieldId.value);
}

/**
 * A paste of several lines, or of a table, becomes rows straight away, and can
 * be undone. A single line is left to the browser, so the caret stays put.
 */
function handleAddPaste(event: ClipboardEvent): void {
    const text = plainTextFromPaste(event);
    const html = event.clipboardData?.getData('text/html') ?? '';
    const { rows, headerSkipped } = rowsFromPaste(props.config, text, html);

    if (rows.length < 2 && !headerSkipped) {
        return;
    }

    event.preventDefault();

    if (!addRows(rows, true)) {
        return;
    }

    const message = `Pasted ${rows.length} ${rows.length === 1 ? 'row' : 'rows'}.`;
    notice.value = { message, undoLabel: 'Undo the paste' };
    say(headerSkipped ? `${message} A header row was skipped.` : message);
    focusElementById(addFieldId.value);
}

function removeRow(id: string): void {
    restoreTo = [...props.entries];

    const removed = props.entries.find((entry) => entry.id === id);
    replaceEntries(props.entries.filter((entry) => entry.id !== id));

    if (editingId.value === id) {
        editingId.value = null;
    }

    notice.value = { message: 'Row removed.', undoLabel: 'Undo the removal' };
    say(
        `Row removed${
            removed ? `: ${cellText(columns.value[0], removed)}` : ''
        }. Undo is available.`,
    );
}

function undo(): void {
    if (restoreTo === null) {
        return;
    }

    replaceEntries(restoreTo);
    notice.value = null;
    restoreTo = null;
    say('Change undone.');
}

/** Ctrl/Cmd+Z puts back the last paste or removal while the notice is up. */
function handleKeyDown(event: KeyboardEvent): void {
    if (notice.value === null || !(event.ctrlKey || event.metaKey)) {
        return;
    }

    if (event.key.toLowerCase() !== 'z' || event.shiftKey) {
        return;
    }

    event.preventDefault();
    undo();
}

/** Enter confirms a cell and moves on; Escape simply leaves it. */
function confirmCell(event: KeyboardEvent): void {
    (event.target as HTMLElement | null)?.blur();
}
</script>

<template>
    <div
        :class="props.pasteFirst ? 'flex flex-col gap-2' : 'space-y-2'"
        @keydown="handleKeyDown"
    >
        <!-- The words above are also spoken, so the change is heard at once. -->
        <p role="status" aria-live="polite" class="sr-only">
            {{ announcement }}
        </p>

        <!-- With the paste area first, the order classes below (on each
            block) lift the paste area above the rows. Everywhere else the
            blocks keep the spacing they are written with above. -->

        <div
            class="overflow-hidden rounded-lg border bg-card"
            :class="props.pasteFirst ? 'order-3' : ''"
        >
            <!-- A real table shares column sizing across every row, so the
                header lines up with its records. On small screens it stacks,
                each row becoming a labelled card. -->
            <table
                :aria-label="props.label"
                class="block w-full border-collapse text-sm sm:table"
            >
                <thead
                    class="hidden bg-muted/40 text-xs font-medium text-muted-foreground sm:table-header-group"
                >
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="border-r border-b px-3 py-2 text-left font-semibold"
                        >
                            {{ column.header }}
                        </th>
                        <th
                            scope="col"
                            class="w-[15%] border-b px-3 py-2 text-right font-semibold"
                        >
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="block divide-y sm:table-row-group">
                    <template
                        v-for="(entry, index) in props.entries"
                        :key="entry.id"
                    >
                        <tr
                            :id="`${props.fieldId}-row-${entry.id}`"
                            class="block sm:table-row"
                            :class="
                                duplicateIds.has(entry.id)
                                    ? 'bg-amber-500/5'
                                    : ''
                            "
                        >
                            <!-- Edit mode: every column of the row becomes an
                                input, or an inline editor when the column keeps
                                formatting. -->
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="block space-y-1 p-3 align-top sm:table-cell sm:border-r"
                                :class="{
                                    'w-[40%]': column.key === 'example_html',
                                }"
                            >
                                <label
                                    :for="rowFieldId(entry.id, column.key)"
                                    class="text-xs font-medium text-muted-foreground sm:hidden"
                                >
                                    {{ column.header }}
                                </label>

                                <template v-if="editingId === entry.id">
                                    <EntryList
                                        v-if="column.key === 'example_html'"
                                        :entries="
                                            exampleEntries(entry.example_html)
                                        "
                                        :label="`${column.header}, row ${index + 1} of ${props.label}`"
                                        :field-id="
                                            rowFieldId(entry.id, column.key)
                                        "
                                        @update:entries="
                                            updateRow(entry.id, {
                                                [column.key]: $event,
                                            })
                                        "
                                    />

                                    <RichTextEditor
                                        v-else-if="column.formatted"
                                        :id="rowFieldId(entry.id, column.key)"
                                        :model-value="
                                            cellValue(column, entry) as string
                                        "
                                        variant="inline"
                                        :label="`${column.header}, row ${index + 1} of ${props.label}`"
                                        :placeholder="column.placeholder"
                                        @update:model-value="
                                            updateRow(entry.id, {
                                                [column.key]: $event,
                                            })
                                        "
                                    />
                                    <Input
                                        v-else
                                        :id="rowFieldId(entry.id, column.key)"
                                        :model-value="
                                            cellValue(column, entry) as string
                                        "
                                        :aria-label="`${column.header}, row ${index + 1} of ${props.label}`"
                                        class="h-11"
                                        @update:model-value="
                                            updateRow(entry.id, {
                                                [column.key]: String($event),
                                            })
                                        "
                                        @keydown.enter.prevent="confirmCell"
                                        @keydown.esc.prevent="stopEdit"
                                    />
                                </template>

                                <template v-else>
                                    <span
                                        class="text-xs font-medium text-muted-foreground sm:hidden"
                                    >
                                        {{ column.header }}
                                    </span>
                                    <!-- A formatted cell holds only bold and
                                        italic around escaped text, so this
                                        markup is safe to show. The example
                                        column is either a list of entries or
                                        one piece of markup, and each shape is
                                        shown the way it is held. -->
                                    <ul
                                        v-if="
                                            column.key === 'example_html' &&
                                            Array.isArray(
                                                cellValue(column, entry),
                                            )
                                        "
                                        class="list-none"
                                    >
                                        <li
                                            v-for="(
                                                part, partIndex
                                            ) in exampleParts(
                                                entry.example_html,
                                            )"
                                            :key="partIndex"
                                            v-html="part.html"
                                        ></li>
                                    </ul>

                                    <p
                                        v-else-if="
                                            column.formatted &&
                                            cellText(column, entry) !== ''
                                        "
                                        class="text-sm break-words"
                                        v-html="cellValue(column, entry)"
                                    ></p>
                                    <p
                                        v-else-if="
                                            cellValue(column, entry) !== ''
                                        "
                                        class="text-sm break-words"
                                    >
                                        {{ cellValue(column, entry) }}
                                    </p>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        —
                                    </p>
                                </template>
                            </td>

                            <td
                                class="block p-2 align-top sm:table-cell sm:border-r"
                            >
                                <div class="flex items-start justify-end gap-1">
                                    <Button
                                        v-if="editingId === entry.id"
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="min-h-11 min-w-11"
                                        :aria-label="`Done editing row ${index + 1}`"
                                        @click="stopEdit"
                                    >
                                        <CheckIcon aria-hidden="true" />
                                    </Button>
                                    <Button
                                        v-else
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="min-h-11 min-w-11"
                                        :aria-label="`Edit row ${index + 1}: ${cellText(columns[0], entry)}`"
                                        @click="startEdit(entry.id)"
                                    >
                                        <PencilIcon aria-hidden="true" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="min-h-11 min-w-11 text-destructive"
                                        :aria-label="`Remove row ${index + 1}: ${cellText(columns[0], entry)}`"
                                        @click="removeRow(entry.id)"
                                    >
                                        <Trash2Icon aria-hidden="true" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                attention.has(entry.id) ||
                                duplicateIds.has(entry.id)
                            "
                            class="block sm:table-row"
                            :class="
                                duplicateIds.has(entry.id)
                                    ? 'bg-amber-500/5'
                                    : ''
                            "
                        >
                            <td
                                :colspan="columns.length + 1"
                                class="block px-3 py-2 align-top sm:table-cell"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Focus can be sent here after finishing,
                                        so the note is addressable by id and
                                        focusable even though it is prose. -->
                                    <p
                                        v-if="attention.has(entry.id)"
                                        :id="
                                            attentionNoteId(
                                                props.fieldId,
                                                entry.id,
                                            )
                                        "
                                        tabindex="-1"
                                        class="flex items-center gap-1.5 text-xs font-medium text-amber-700 outline-none dark:text-amber-300"
                                    >
                                        <TriangleAlertIcon
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {{ attention.get(entry.id) }}
                                    </p>

                                    <Badge
                                        v-if="duplicateIds.has(entry.id)"
                                        variant="outline"
                                        class="gap-1 border-amber-500/60 text-amber-700 dark:text-amber-300"
                                    >
                                        <CopyIcon aria-hidden="true" />
                                        Duplicate
                                    </Badge>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p
            v-if="attention.size > 0"
            class="text-xs text-muted-foreground"
            :class="props.pasteFirst ? 'order-4' : ''"
        >
            {{ attention.size }}
            {{ attention.size === 1 ? 'row needs' : 'rows need' }} attention.
        </p>

        <UndoNotice
            v-if="notice"
            :class="props.pasteFirst ? 'order-2' : ''"
            :message="notice.message"
            :undo-label="notice.undoLabel"
            @undo="undo"
        />

        <div
            class="flex items-start gap-2"
            :class="props.pasteFirst ? 'order-1' : ''"
        >
            <Textarea
                :id="addFieldId"
                v-model="draft"
                :aria-label="`Add rows to ${props.label}`"
                :aria-invalid="Boolean(props.error) || limitMessage !== ''"
                :placeholder="props.placeholder"
                class="min-h-11 flex-1 resize-y"
                rows="2"
                @keydown.enter.exact.prevent="addFromDraft"
                @paste="handleAddPaste"
            />
            <Button
                type="button"
                variant="outline"
                size="lg"
                class="min-h-11"
                @click="addFromDraft"
            >
                <PlusIcon aria-hidden="true" />
                Add
            </Button>
        </div>

        <div
            class="flex flex-wrap items-center gap-2"
            :class="props.pasteFirst ? 'order-5' : ''"
        >
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addBlankRow"
            >
                <PlusIcon aria-hidden="true" />
                Add row
            </Button>
        </div>

        <p
            v-if="limitMessage !== ''"
            role="alert"
            class="text-xs font-medium text-destructive"
            :class="props.pasteFirst ? 'order-6' : ''"
        >
            {{ limitMessage }}
        </p>

        <p
            v-if="props.error"
            role="alert"
            class="text-xs font-medium text-destructive"
            :class="props.pasteFirst ? 'order-7' : ''"
        >
            {{ props.error }}
        </p>
    </div>
</template>
