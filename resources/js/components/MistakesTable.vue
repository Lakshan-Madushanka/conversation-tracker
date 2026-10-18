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
import { focusElementById } from '@/lib/dom';
import { newMistake } from '@/lib/feedback';
import {
    MAX_CELL_LENGTH,
    MAX_ROWS,
    mistakeAttention,
    mistakeDuplicateKey,
    mistakesFromPaste,
    type ParsedMistake,
} from '@/lib/mistakes';
import { plainTextFromPaste } from '@/lib/paste';
import { richTextToText } from '@/lib/rich-text';
import type { MistakeEntry } from '@/types';

/**
 * The mistakes box: a bordered table of what was said, the correction and an
 * optional why. Entries are shown read-only until their row is edited, and
 * every row can be removed.
 *
 * Pasting several lines — or a table with the same columns — adds one row each
 * and splits them by the separator rules, then can be undone. A row whose
 * correction is missing shows the reason beside it, and a row that repeats an
 * earlier one is tagged as a duplicate.
 */
const props = defineProps<{
    entries: MistakeEntry[];
    /** Accessible name for the fields, for example "Mistakes for question 1". */
    label: string;
    /** Unique id for the add field, so focus can be moved to it. */
    fieldId: string;
    placeholder?: string;
    error?: string;
}>();

const emit = defineEmits<{
    (event: 'update:entries', entries: MistakeEntry[]): void;
}>();

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
let restoreTo: MistakeEntry[] | null = null;

const addFieldId = computed(() => `${props.fieldId}-add`);

/** Which rows still need a correction, and why. */
const attention = computed(() => {
    const map = new Map<string, string>();

    for (const entry of props.entries) {
        const reason = mistakeAttention(entry);

        if (reason !== null) {
            map.set(entry.id, reason);
        }
    }

    return map;
});

/** Rows whose text and correction already appear together earlier in the box. */
const duplicateIds = computed(() => {
    const seen = new Set<string>();
    const duplicates = new Set<string>();

    for (const entry of props.entries) {
        const key = mistakeDuplicateKey(entry);

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

function replaceEntries(entries: MistakeEntry[]): void {
    emit('update:entries', entries);
}

function say(message: string): void {
    announcement.value = message;
}

function rowFieldId(id: string, cell: string): string {
    return `${props.fieldId}-${cell}-${id}`;
}

function updateRow(id: string, patch: Partial<Omit<MistakeEntry, 'id'>>): void {
    replaceEntries(
        props.entries.map((entry) =>
            entry.id === id ? { ...entry, ...patch } : entry,
        ),
    );
}

/** Open a row for editing and put the cursor in its first field. */
function startEdit(id: string): void {
    editingId.value = id;
    void nextTick(() => focusElementById(rowFieldId(id, 'text')));
}

/** Close the row being edited; its values are already saved as they changed. */
function stopEdit(): void {
    editingId.value = null;
}

/** Add one empty row, opened for typing or pasting into. */
function addBlankRow(): void {
    if (props.entries.length >= MAX_ROWS) {
        limitMessage.value = `A box can hold up to ${MAX_ROWS} rows. Remove one to add another.`;
        say(limitMessage.value);

        return;
    }

    const row = newMistake();

    replaceEntries([...props.entries, row]);
    limitMessage.value = '';
    say('Empty row added.');
    editingId.value = row.id;
    void nextTick(() => focusElementById(rowFieldId(row.id, 'text')));
}

/**
 * Add pasted rows, or explain why they were not added. The text the learner
 * pasted is never thrown away: on failure it stays in the add field.
 */
function addRows(rows: ParsedMistake[], pasted: boolean): boolean {
    if (rows.length === 0) {
        limitMessage.value = '';

        return false;
    }

    const tooLong = rows.find(
        (row) =>
            row.text.length > MAX_CELL_LENGTH ||
            row.reason.length > MAX_CELL_LENGTH,
    );

    if (tooLong !== undefined) {
        keepFailedPaste(pasted, rows);
        limitMessage.value = `Each cell can be up to ${MAX_CELL_LENGTH} characters. Split it into two rows.`;
        say(limitMessage.value);

        return false;
    }

    const room = MAX_ROWS - props.entries.length;

    if (rows.length > room) {
        keepFailedPaste(pasted, rows);
        limitMessage.value = `A box can hold up to ${MAX_ROWS} rows. Remove one to add another.`;
        say(limitMessage.value);

        return false;
    }

    limitMessage.value = '';
    restoreTo = [...props.entries];
    replaceEntries([...props.entries, ...rows.map((row) => newMistake(row))]);

    return true;
}

/**
 * Keep text that could not be added in the add field. A typed draft already
 * holds it; a paste was stopped before it reached the field, so it is put back
 * there — nothing the learner pasted is ever dropped.
 */
function keepFailedPaste(pasted: boolean, rows: ParsedMistake[]): void {
    if (!pasted) {
        return;
    }

    const pastedText = rows
        .map((row) => [row.text, row.reason].filter(Boolean).join(' '))
        .join('\n');

    draft.value =
        draft.value === '' ? pastedText : `${draft.value}\n${pastedText}`;
}

function addFromDraft(): void {
    const { rows } = mistakesFromPaste(draft.value);

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
    const { rows, headerSkipped } = mistakesFromPaste(text, html);

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
    say(`Row removed${removed ? `: ${removed.text}` : ''}. Undo is available.`);
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

const gridColumns =
    'sm:grid-cols-[minmax(0,1.1fr)_minmax(0,1.1fr)_minmax(0,0.9fr)_auto]';
</script>

<template>
    <div class="space-y-2" @keydown="handleKeyDown">
        <!-- The words above are also spoken, so the change is heard at once. -->
        <p role="status" aria-live="polite" class="sr-only">
            {{ announcement }}
        </p>

        <div
            role="table"
            :aria-label="props.label"
            class="overflow-hidden rounded-lg border bg-card"
        >
            <div
                role="row"
                class="hidden bg-muted/40 text-xs font-medium text-muted-foreground sm:grid"
                :class="gridColumns"
            >
                <span role="columnheader" class="border-r border-b px-3 py-2"
                    >What I said</span
                >
                <span role="columnheader" class="border-r border-b px-3 py-2"
                    >Correction</span
                >
                <span role="columnheader" class="border-r border-b px-3 py-2"
                    >Why (optional)</span
                >
                <span role="columnheader" class="sr-only border-b px-3 py-2"
                    >Actions</span
                >
            </div>

            <div
                v-for="(entry, index) in props.entries"
                :key="entry.id"
                role="row"
                class="grid grid-cols-1 border-b last:border-b-0"
                :class="[
                    gridColumns,
                    duplicateIds.has(entry.id) ? 'bg-amber-500/5' : '',
                ]"
            >
                <!-- Edit mode: every column of the row becomes an input. -->
                <template v-if="editingId === entry.id">
                    <div
                        role="cell"
                        class="min-w-0 space-y-1 border-b p-3 sm:border-r sm:border-b-0"
                    >
                        <label
                            :for="rowFieldId(entry.id, 'text')"
                            class="text-xs font-medium text-muted-foreground sm:hidden"
                        >
                            What I said
                        </label>
                        <Input
                            :id="rowFieldId(entry.id, 'text')"
                            :model-value="entry.text"
                            :aria-label="`What I said, row ${index + 1} of ${props.label}`"
                            class="h-11"
                            @update:model-value="
                                updateRow(entry.id, { text: String($event) })
                            "
                            @keydown.enter.prevent="confirmCell"
                            @keydown.esc.prevent="stopEdit"
                        />
                    </div>

                    <div
                        role="cell"
                        class="min-w-0 space-y-1 border-b p-3 sm:border-r sm:border-b-0"
                    >
                        <label
                            :for="rowFieldId(entry.id, 'correction')"
                            class="text-xs font-medium text-muted-foreground sm:hidden"
                        >
                            Correction
                        </label>
                        <RichTextEditor
                            :id="rowFieldId(entry.id, 'correction')"
                            :model-value="entry.correction_html"
                            variant="inline"
                            :label="`Correction, row ${index + 1} of ${props.label}`"
                            placeholder="e.g. I went"
                            @update:model-value="
                                updateRow(entry.id, {
                                    correction_html: $event,
                                })
                            "
                        />
                    </div>

                    <div
                        role="cell"
                        class="min-w-0 space-y-1 border-b p-3 sm:border-r sm:border-b-0"
                    >
                        <label
                            :for="rowFieldId(entry.id, 'reason')"
                            class="text-xs font-medium text-muted-foreground sm:hidden"
                        >
                            Why (optional)
                        </label>
                        <Input
                            :id="rowFieldId(entry.id, 'reason')"
                            :model-value="entry.reason"
                            :aria-label="`Why, row ${index + 1} of ${props.label}`"
                            class="h-11"
                            @update:model-value="
                                updateRow(entry.id, { reason: String($event) })
                            "
                            @keydown.enter.prevent="confirmCell"
                            @keydown.esc.prevent="stopEdit"
                        />
                    </div>
                </template>

                <!-- Read-only: the row's values, until the edit icon is used. -->
                <template v-else>
                    <div
                        role="cell"
                        class="min-w-0 space-y-1 border-b p-3 sm:border-r sm:border-b-0"
                    >
                        <span
                            class="text-xs font-medium text-muted-foreground sm:hidden"
                        >
                            What I said
                        </span>
                        <p class="text-sm break-words">
                            {{ entry.text !== '' ? entry.text : '—' }}
                        </p>
                    </div>

                    <div
                        role="cell"
                        class="min-w-0 space-y-1 border-b p-3 sm:border-r sm:border-b-0"
                    >
                        <span
                            class="text-xs font-medium text-muted-foreground sm:hidden"
                        >
                            Correction
                        </span>
                        <!-- The correction holds only bold and italic around
                            escaped text, so this markup is safe to show. -->
                        <p
                            v-if="richTextToText(entry.correction_html) !== ''"
                            class="text-sm break-words"
                            v-html="entry.correction_html"
                        ></p>
                        <p v-else class="text-sm text-muted-foreground">—</p>
                    </div>

                    <div
                        role="cell"
                        class="min-w-0 space-y-1 border-b p-3 sm:border-r sm:border-b-0"
                    >
                        <span
                            class="text-xs font-medium text-muted-foreground sm:hidden"
                        >
                            Why (optional)
                        </span>
                        <p class="text-sm break-words">
                            {{ entry.reason !== '' ? entry.reason : '—' }}
                        </p>
                    </div>
                </template>

                <div role="cell" class="flex items-start justify-end gap-1 p-2">
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
                        :aria-label="`Edit row ${index + 1}: ${entry.text}`"
                        @click="startEdit(entry.id)"
                    >
                        <PencilIcon aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="min-h-11 min-w-11 text-destructive"
                        :aria-label="`Remove row ${index + 1}: ${entry.text}`"
                        @click="removeRow(entry.id)"
                    >
                        <Trash2Icon aria-hidden="true" />
                    </Button>
                </div>

                <div
                    v-if="attention.has(entry.id) || duplicateIds.has(entry.id)"
                    role="cell"
                    class="flex flex-wrap items-center gap-2 border-t px-3 py-2 sm:col-span-full"
                >
                    <p
                        v-if="attention.has(entry.id)"
                        class="flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300"
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
            </div>
        </div>

        <p v-if="attention.size > 0" class="text-xs text-muted-foreground">
            {{ attention.size }}
            {{ attention.size === 1 ? 'row needs' : 'rows need' }} attention.
        </p>

        <UndoNotice
            v-if="notice"
            :message="notice.message"
            :undo-label="notice.undoLabel"
            @undo="undo"
        />

        <div class="flex items-start gap-2">
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

        <div class="flex flex-wrap items-center gap-2">
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
        >
            {{ limitMessage }}
        </p>

        <p
            v-if="props.error"
            role="alert"
            class="text-xs font-medium text-destructive"
        >
            {{ props.error }}
        </p>
    </div>
</template>
