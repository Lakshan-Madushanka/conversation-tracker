<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { CopyIcon, PencilIcon, PlusIcon, Trash2Icon } from '@lucide/vue';
import UndoNotice from '@/components/UndoNotice.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { focusElementById } from '@/lib/dom';
import { newEntry } from '@/lib/feedback';
import {
    MAX_ENTRIES,
    MAX_LENGTH,
    normalizeEntryText,
    splitIntoEntries,
} from '@/lib/list-entries';
import { plainTextFromPaste } from '@/lib/paste';
import type { FeedbackEntry } from '@/types';

/**
 * A list of plain text entries: the mistakes, vocabulary and phrases boxes.
 *
 * Pasting several lines adds one entry per line, cleaned as it arrives. Every
 * entry can then be edited, removed or added to on its own, and a paste can be
 * undone before it is saved.
 */
const props = defineProps<{
    entries: FeedbackEntry[];
    /** Accessible name for the fields, for example "Mistakes for question 1". */
    label: string;
    /** Unique id for the add field, so focus can be moved to it. */
    fieldId: string;
    placeholder?: string;
    error?: string;
}>();

const emit = defineEmits<{
    (event: 'update:entries', entries: FeedbackEntry[]): void;
}>();

const draft = ref('');
const editingId = ref<string | null>(null);
const editText = ref('');

/** Set after a paste or a removal, until it is undone or replaced. */
const notice = ref<{ message: string; undoLabel: string } | null>(null);
/** Shown under the add field when a paste could not be added. */
const limitMessage = ref('');
/** Words repeated into the live region so a screen reader hears what happened. */
const announcement = ref('');

/** The entries as they were before the last paste or removal, for undo. */
let restoreTo: FeedbackEntry[] | null = null;

const addFieldId = computed(() => props.fieldId);

/** Entries whose text already appears earlier in the same box. */
const duplicateIds = computed(() => {
    const seen = new Set<string>();
    const duplicates = new Set<string>();

    for (const entry of props.entries) {
        const key = normalizeEntryText(entry.text);

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

const duplicateCount = computed(() => duplicateIds.value.size);

/** True while the add field holds text or an entry is being edited. */
const hasDraft = computed(
    () => draft.value.trim() !== '' || editingId.value !== null,
);

defineExpose({ hasDraft });

function replaceEntries(entries: FeedbackEntry[]): void {
    emit('update:entries', entries);
}

function say(message: string): void {
    announcement.value = message;
}

function showNotice(message: string, undoLabel: string): void {
    notice.value = { message, undoLabel };
    say(message);
}

function clearNotice(): void {
    notice.value = null;
    restoreTo = null;
}

/** Put the entries back the way they were before the last change. */
function undo(): void {
    if (restoreTo === null) {
        return;
    }

    replaceEntries(restoreTo);
    clearNotice();
    say('Change undone.');
}

/**
 * Add entries from cleaned lines, or explain why they were not added. The
 * text the learner pasted is never thrown away: on failure it stays in the
 * add field, with the reason shown beside it.
 */
function addLines(lines: string[], pasted: boolean): boolean {
    if (lines.length === 0) {
        limitMessage.value = '';

        return false;
    }

    const tooLong = lines.find((line) => line.length > MAX_LENGTH);

    if (tooLong !== undefined) {
        keepFailedPaste(pasted, lines);
        limitMessage.value = `Each entry can be up to ${MAX_LENGTH} characters. Split it into two entries.`;
        say(limitMessage.value);

        return false;
    }

    const room = MAX_ENTRIES - props.entries.length;

    if (lines.length > room) {
        keepFailedPaste(pasted, lines);
        limitMessage.value = `A box can hold up to ${MAX_ENTRIES} entries. Remove one to add another.`;
        say(limitMessage.value);

        return false;
    }

    limitMessage.value = '';
    restoreTo = [...props.entries];
    replaceEntries([...props.entries, ...lines.map((text) => newEntry(text))]);

    if (!pasted) {
        clearNotice();
        say(
            `${lines.length} ${lines.length === 1 ? 'entry' : 'entries'} added.`,
        );

        return true;
    }

    const message = `Pasted ${lines.length} ${lines.length === 1 ? 'line' : 'lines'}, ${lines.length} ${lines.length === 1 ? 'entry' : 'entries'} added.`;
    const duplicates = lines.filter(
        (line, index) =>
            lines.slice(0, index).includes(line) ||
            props.entries.some(
                (entry) =>
                    normalizeEntryText(entry.text) === normalizeEntryText(line),
            ),
    ).length;

    showNotice(message, 'Undo the paste');

    if (duplicates > 0) {
        say(
            `${message} ${duplicates} of them ${duplicates === 1 ? 'is' : 'are'} already in this list.`,
        );
    }

    return true;
}

function addFromDraft(): void {
    const lines = splitIntoEntries(draft.value);

    if (!addLines(lines, false)) {
        return;
    }

    draft.value = '';
    focusElementById(addFieldId.value);
}

/**
 * Keep text that could not be added in the add field. A typed draft already
 * holds it; a paste was stopped before it reached the field, so it is put
 * back there — nothing the learner pasted is ever dropped.
 */
function keepFailedPaste(pasted: boolean, lines: string[]): void {
    if (!pasted) {
        return;
    }

    const pastedText = lines.join('\n');

    draft.value =
        draft.value === '' ? pastedText : `${draft.value}\n${pastedText}`;
}

/** A paste of several lines becomes entries straight away, and can be undone. */
function handleAddPaste(event: ClipboardEvent): void {
    const lines = splitIntoEntries(plainTextFromPaste(event));

    // One line is left to the browser, so the caret stays where it is; it is
    // cleaned when the entry is added.
    if (lines.length < 2) {
        return;
    }

    event.preventDefault();
    addLines(lines, true);
    focusElementById(addFieldId.value);
}

/**
 * A paste into an entry being edited joins its lines into one, so editing
 * never splits an entry back into several.
 */
function handleEditPaste(event: ClipboardEvent): void {
    const text = plainTextFromPaste(event).replaceAll(/\s*\n+\s*/gu, ' ');

    event.preventDefault();
    editText.value += text;
}

function startEdit(entry: FeedbackEntry): void {
    editingId.value = entry.id;
    editText.value = entry.text;
    void nextTick(() => focusElementById(editFieldId(entry.id)));
}

function editFieldId(id: string): string {
    return `${props.fieldId}-edit-${id}`;
}

function commitEdit(): void {
    const id = editingId.value;

    if (id === null) {
        return;
    }

    editingId.value = null;

    const text = editText.value.trim();

    // Editing an entry down to nothing removes it, with the same undo as the
    // Remove button.
    if (text === '') {
        removeEntry(id);

        return;
    }

    replaceEntries(
        props.entries.map((entry) =>
            entry.id === id ? { ...entry, text } : entry,
        ),
    );
}

function cancelEdit(): void {
    editingId.value = null;
}

function removeEntry(id: string): void {
    restoreTo = [...props.entries];

    const removed = props.entries.find((entry) => entry.id === id);
    replaceEntries(props.entries.filter((entry) => entry.id !== id));

    showNotice('Entry removed.', 'Undo the removal');
    say(
        `Entry removed${removed ? `: ${removed.text}` : ''}. Undo is available.`,
    );
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
</script>

<template>
    <div class="space-y-2" @keydown="handleKeyDown">
        <!-- The words above are also spoken, so the change is heard at once. -->
        <p role="status" aria-live="polite" class="sr-only">
            {{ announcement }}
        </p>

        <ul v-if="props.entries.length > 0" class="space-y-1.5">
            <li
                v-for="(entry, index) in props.entries"
                :key="entry.id"
                class="flex items-start gap-2 rounded-lg border bg-card px-2.5 py-2"
                :class="
                    duplicateIds.has(entry.id)
                        ? 'border-amber-500/60 ring-1 ring-amber-500/40'
                        : 'border-border'
                "
            >
                <template v-if="editingId === entry.id">
                    <Input
                        :id="editFieldId(entry.id)"
                        v-model="editText"
                        :aria-label="`Edit entry ${index + 1} of ${props.label}`"
                        class="h-11 flex-1"
                        @paste="handleEditPaste"
                        @keydown.enter.prevent="commitEdit"
                        @keydown.esc.prevent="cancelEdit"
                        @blur="commitEdit"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="min-h-11 min-w-11"
                        :aria-label="`Save entry ${index + 1}`"
                        @mousedown.prevent="commitEdit"
                    >
                        <PlusIcon aria-hidden="true" />
                    </Button>
                </template>

                <template v-else>
                    <p class="min-w-0 flex-1 py-1.5 text-sm break-words">
                        {{ entry.text }}
                    </p>

                    <Badge
                        v-if="duplicateIds.has(entry.id)"
                        variant="outline"
                        class="mt-1.5 gap-1 border-amber-500/60 text-amber-700 dark:text-amber-300"
                    >
                        <CopyIcon aria-hidden="true" />
                        Duplicate
                    </Badge>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="min-h-11 min-w-11"
                        :aria-label="`Edit entry ${index + 1}: ${entry.text}`"
                        @click="startEdit(entry)"
                    >
                        <PencilIcon aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="min-h-11 min-w-11 text-destructive"
                        :aria-label="`Remove entry ${index + 1}: ${entry.text}`"
                        @click="removeEntry(entry.id)"
                    >
                        <Trash2Icon aria-hidden="true" />
                    </Button>
                </template>
            </li>
        </ul>

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
                :aria-label="`Add an entry to ${props.label}`"
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

        <p
            v-if="limitMessage !== ''"
            role="alert"
            class="text-xs font-medium text-destructive"
        >
            {{ limitMessage }}
        </p>

        <p v-if="duplicateCount > 0" class="text-xs text-muted-foreground">
            {{ duplicateCount }}
            {{ duplicateCount === 1 ? 'entry is' : 'entries are' }} repeated in
            this list.
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
