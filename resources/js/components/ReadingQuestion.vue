<script setup lang="ts">
import SectionLabel from '@/components/SectionLabel.vue';
import { Separator } from '@/components/ui/separator';
import { isRichTextBlank } from '@/lib/rich-text';
import type {
    ReadingCell,
    ReadingEntry,
    ReadingPart,
    ReadingSection,
} from '@/lib/reading';
import type { FeedbackType, PracticeQuestionData } from '@/types';

/**
 * One saved question as it is read back: the tutor's question, the learner's
 * answer, and every feedback box that holds something. Table boxes are shown
 * with the same columns as the editor, so a word, phrase or mistake reads the
 * way it was recorded.
 */
const props = defineProps<{
    question: PracticeQuestionData;
    /** The boxes of this question that hold something, in reading order. */
    sections: ReadingSection[];
}>();

/** The stored ids of one box's entries, in the order the page shows them. */
function entryIds(type: FeedbackType): string[] {
    const value = props.question.feedback[type];

    return Array.isArray(value) ? value.map((entry) => entry.id) : [];
}

/** The id of one entry on this page, when it has one. */
function entryTargetId(
    section: ReadingSection,
    index: number,
): string | undefined {
    const id = entryIds(section.type)[index];

    return id === undefined || id === ''
        ? undefined
        : `reading-${props.question.position}-entry-${id}`;
}

/** What an entry shows, in order: what it is, then what explains it. */
function partsOf(entry: ReadingEntry): ReadingPart[] {
    return [entry.text, ...entry.details];
}

/** Whether a table cell holds anything at all. */
function cellHasContent(cell: ReadingCell): boolean {
    return cell.parts.some((part) => part.value.trim() !== '');
}
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-3">
            <div
                v-if="!isRichTextBlank(props.question.question_html)"
                :id="`reading-${props.question.position}-question`"
            >
                <p
                    class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Tutor’s question
                </p>
                <!-- Formatted text is bold and italic around escaped words,
                    so it is safe to show. -->
                <div
                    class="text-sm"
                    v-html="props.question.question_html"
                ></div>
            </div>

            <div
                v-if="!isRichTextBlank(props.question.answer_html)"
                :id="`reading-${props.question.position}-answer`"
            >
                <p
                    class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    My answer
                </p>
                <div
                    class="text-sm text-muted-foreground"
                    v-html="props.question.answer_html"
                ></div>
            </div>
        </div>

        <template v-if="props.sections.length > 0">
            <Separator />

            <dl class="space-y-3">
                <div
                    v-for="section in props.sections"
                    :key="section.type"
                    :id="`reading-${props.question.position}-section-${section.type}`"
                >
                    <dt class="mb-1">
                        <SectionLabel :tone="section.tone">
                            {{ section.label }}
                        </SectionLabel>
                    </dt>

                    <dd class="space-y-1.5">
                        <!-- A table box keeps the editor's columns, so every
                            word, phrase and correction lines up the same way. -->
                        <div
                            v-if="section.table"
                            class="overflow-hidden rounded-lg border bg-card"
                        >
                            <table
                                :aria-label="section.label"
                                class="block w-full border-collapse text-sm sm:table"
                            >
                                <thead
                                    class="hidden bg-muted/40 text-xs font-medium text-muted-foreground sm:table-header-group"
                                >
                                    <tr>
                                        <th
                                            v-for="column in section.table
                                                .columns"
                                            :key="column.header"
                                            scope="col"
                                            class="border-r border-b px-3 py-2 text-left font-semibold last:border-r-0"
                                        >
                                            {{ column.header }}
                                        </th>
                                    </tr>
                                </thead>

                                <tbody
                                    class="block divide-y sm:table-row-group"
                                >
                                    <tr
                                        v-for="(row, rowIndex) in section.table
                                            .rows"
                                        :id="entryTargetId(section, rowIndex)"
                                        :key="rowIndex"
                                        class="block sm:table-row"
                                    >
                                        <td
                                            v-for="(cell, cellIndex) in row"
                                            :key="cellIndex"
                                            class="block space-y-1 p-3 align-top sm:table-cell sm:border-r sm:last:border-r-0"
                                        >
                                            <span
                                                class="text-xs font-medium text-muted-foreground sm:hidden"
                                            >
                                                {{
                                                    section.table.columns[
                                                        cellIndex
                                                    ]?.header
                                                }}
                                            </span>

                                            <template
                                                v-if="cellHasContent(cell)"
                                            >
                                                <span
                                                    v-for="(
                                                        part, partIndex
                                                    ) in cell.parts"
                                                    :key="partIndex"
                                                    class="block break-words"
                                                >
                                                    <span
                                                        v-if="part.formatted"
                                                        v-html="part.value"
                                                    ></span>
                                                    <template v-else>{{
                                                        part.value
                                                    }}</template>
                                                </span>
                                            </template>
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                —
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Notes and the natural version are prose, not rows. -->
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="(entry, entryIndex) in section.entries"
                                :id="entryTargetId(section, entryIndex)"
                                :key="entryIndex"
                                class="text-sm leading-relaxed"
                            >
                                <template
                                    v-for="(part, partIndex) in partsOf(entry)"
                                    :key="partIndex"
                                >
                                    <span v-if="partIndex > 0" class="sr-only">
                                        ,
                                    </span>
                                    <span
                                        v-if="partIndex > 0"
                                        class="text-muted-foreground"
                                        aria-hidden="true"
                                    >
                                        —
                                    </span>
                                    <span
                                        v-if="part.formatted"
                                        v-html="part.value"
                                    ></span>
                                    <template v-else>{{ part.value }}</template>
                                </template>
                            </div>
                        </div>
                    </dd>
                </div>
            </dl>
        </template>
    </div>
</template>
