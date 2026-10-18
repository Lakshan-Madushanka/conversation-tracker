<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { ChevronDownIcon, ChevronUpIcon } from '@lucide/vue';
import EntryList from '@/components/EntryList.vue';
import FeedbackTable from '@/components/FeedbackTable.vue';
import GrammarNotesList from '@/components/GrammarNotesList.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import { Button } from '@/components/ui/button';
import { focusElementById } from '@/lib/dom';
import { feedbackBoxFor } from '@/lib/feedback';
import {
    rowAttention,
    type FeedbackTableConfig,
    type TableRow,
} from '@/lib/feedback-table';
import { isRichTextBlank, richTextToText } from '@/lib/rich-text';
import type {
    FeedbackEntry,
    FeedbackType,
    FeedbackValue,
    GrammarNote,
} from '@/types';

/**
 * One labelled, colour-tagged feedback box. While it is empty it stays a slim
 * row; it opens on tap or keyboard, and closes again when it loses focus
 * without any content. A box with content always stays open when focus moves
 * away, but the learner can still shrink it themselves.
 *
 * A table box holds rows of columns (mistakes and vocabulary), the list box
 * holds entries the learner can edit one by one, the grammar notes box holds
 * one formatted note per paste, and the natural version stays one piece of
 * formatted text.
 */
const props = defineProps<{
    type: FeedbackType;
    modelValue: FeedbackValue;
    /** Unique id for the input, so focus can be moved to it. */
    fieldId: string;
    /** Question this box belongs to, for its accessible name. */
    questionNumber: number;
    error?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: FeedbackValue): void;
}>();

const box = feedbackBoxFor(props.type);

/** The columns and rules of a table box; only read for the table kind. */
const tableConfig = computed<FeedbackTableConfig>(() => {
    if (box.table === undefined) {
        throw new Error(`The ${box.type} box has no table configuration.`);
    }

    return box.table;
});

const tableEntries = computed(() =>
    box.kind === 'table' ? (props.modelValue as TableRow[]) : [],
);

/** How many rows are still waiting for their attention column. */
const attentionCount = computed(
    () =>
        tableEntries.value.filter(
            (entry) => rowAttention(tableConfig.value, entry) !== null,
        ).length,
);

const listEntries = computed(() =>
    box.kind === 'list' ? (props.modelValue as FeedbackEntry[]) : [],
);

const notes = computed(() =>
    box.kind === 'notes' ? (props.modelValue as GrammarNote[]) : [],
);

const html = computed(() =>
    box.kind === 'text' ? (props.modelValue as string) : '',
);

/** True while nothing in the box holds content. */
const isBlank = computed(() => {
    if (box.kind === 'text') {
        return isRichTextBlank(html.value);
    }

    if (box.kind === 'notes') {
        return notes.value.every((note) => isRichTextBlank(note.html));
    }

    if (box.kind === 'table') {
        return tableEntries.value.length === 0;
    }

    return listEntries.value.length === 0;
});

const expanded = ref(isBlank.value === false || Boolean(props.error));

/** True once the box has held anything, so its fields are built only once. */
const opened = ref(expanded.value);

watch(isBlank, (blank) => {
    if (!blank) {
        expanded.value = true;
        opened.value = true;
    }
});

watch(
    () => props.error,
    (error) => {
        if (error) {
            expanded.value = true;
            opened.value = true;
        }
    },
);

const wrapper = ref<HTMLElement | null>(null);

/** Set while the box is opening. The re-render removes the focused trigger,
 * which the browser reports as a focusout with no new target; that must not
 * collapse the box we just opened.
 */
let isOpening = false;

const accessibleLabel = computed(
    () => `${box.label} for question ${props.questionNumber}`,
);

/**
 * What the collapsed row says about a box that holds something, so shrinking a
 * filled box never hides what is inside — the count is always visible.
 */
const summary = computed(() => {
    if (box.kind === 'table') {
        const count = tableEntries.value.length;
        const rows = `${count} ${count === 1 ? 'row' : 'rows'}`;

        if (attentionCount.value > 0) {
            return `${rows} · ${attentionCount.value} need attention`;
        }

        return rows;
    }

    if (box.kind === 'list') {
        const count = listEntries.value.length;

        return `${count} ${count === 1 ? 'entry' : 'entries'}`;
    }

    if (box.kind === 'notes') {
        const count = notes.value.filter(
            (note) => !isRichTextBlank(note.html),
        ).length;

        return `${count} ${count === 1 ? 'note' : 'notes'}`;
    }

    const text = richTextToText(html.value);

    return text.length > 60 ? `${text.slice(0, 60)}…` : text;
});

/** Rows from a table box are one of the box's value shapes; pass them on. */
function updateTableEntries(entries: TableRow[]): void {
    emit('update:modelValue', entries as unknown as FeedbackValue);
}

function open(): void {
    isOpening = true;
    opened.value = true;
    expanded.value = true;
    focusElementById(
        box.kind === 'table' ? `${props.fieldId}-add` : props.fieldId,
    );

    void nextTick(() => {
        requestAnimationFrame(() => {
            isOpening = false;
        });
    });
}

/** Shrink the box. Content is kept, only hidden, so nothing is lost. */ function collapse(): void {
    expanded.value = false;
}

/** The rule for losing focus: only an empty box closes itself. */
function collapseIfBlank(): void {
    if (isBlank.value) {
        collapse();
    }
}

/**
 * Whether focus is inside the box right now. `relatedTarget` says where focus
 * moved to, but Firefox omits it on focusout, so the currently focused element
 * is used as a fallback. Focus that only looks like it is inside — an element
 * the box hid when it shrank — does not count, since a hidden element stays
 * the active element until something else is focused.
 */
/**
 * The grammar notes box has no field of its own: it holds one editor per note,
 * and nothing at all until a note is added. So opening it focuses the box
 * itself, and the blur rule has to know that a notes box may hold focus with
 * no field inside it — otherwise the click that opens the box reads as the
 * learner leaving, and it shuts again at once.
 */
const holdsFocusWithoutField = computed(() => box.kind === 'notes');

function focusIsInside(event: FocusEvent): boolean {
    const related = event.relatedTarget as Node | null;

    if (related) {
        return wrapper.value?.contains(related) === true;
    }

    // With no new target, the box is treated as still focused, which keeps a
    // notes box open while the learner works in it.
    return holdsFocusWithoutField.value;
}

function handleFocusOut(event: FocusEvent): void {
    if (isOpening) {
        return;
    }

    if (focusIsInside(event)) {
        return;
    }

    collapseIfBlank();
}
</script>

<template>
    <div
        ref="wrapper"
        class="rounded-lg border transition-colors"
        :class="
            expanded
                ? 'border-border bg-card'
                : 'border-dashed bg-muted/20 hover:bg-muted/40'
        "
        @focusout="handleFocusOut"
    >
        <button
            v-show="!expanded"
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            :aria-expanded="false"
            @click="open"
        >
            <SectionLabel :tone="box.tone">{{ box.label }}</SectionLabel>

            <!-- A shrunk box still says what it holds, so nothing is hidden. -->
            <span class="truncate text-xs text-muted-foreground">{{
                isBlank ? box.hint : summary
            }}</span>

            <ChevronDownIcon
                class="ml-auto size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </button>

        <!-- Fields are built the first time the box is opened, then stay
            mounted while shrunk, so text the learner has typed but not saved
            survives being collapsed and reopened. -->
        <div v-if="opened" v-show="expanded">
            <div class="flex flex-wrap items-center gap-2 px-3 pt-3">
                <SectionLabel :tone="box.tone">{{ box.label }}</SectionLabel>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    class="ml-auto"
                    :aria-label="`Close ${accessibleLabel}`"
                    :aria-expanded="true"
                    @click="collapse"
                >
                    <ChevronUpIcon aria-hidden="true" />
                </Button>
            </div>

            <div class="p-3 pt-2">
                <EntryList
                    v-if="box.kind === 'list'"
                    :entries="listEntries"
                    :label="accessibleLabel"
                    :field-id="props.fieldId"
                    :placeholder="box.placeholder"
                    :error="props.error"
                    @update:entries="emit('update:modelValue', $event)"
                />

                <FeedbackTable
                    v-else-if="box.kind === 'table'"
                    :entries="tableEntries"
                    :config="tableConfig"
                    :label="accessibleLabel"
                    :field-id="props.fieldId"
                    :placeholder="box.placeholder"
                    :error="props.error"
                    @update:entries="updateTableEntries"
                />

                <GrammarNotesList
                    v-else-if="box.kind === 'notes'"
                    :notes="notes"
                    :label="accessibleLabel"
                    :field-id="props.fieldId"
                    :placeholder="box.placeholder"
                    :error="props.error"
                    @update:notes="emit('update:modelValue', $event)"
                />

                <RichTextEditor
                    v-else
                    :id="props.fieldId"
                    :model-value="html"
                    :label="accessibleLabel"
                    :placeholder="box.placeholder"
                    @update:model-value="emit('update:modelValue', $event)"
                />
            </div>
        </div>
    </div>
</template>
