<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDownIcon } from '@lucide/vue';
import SectionLabel from '@/components/SectionLabel.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { isRichTextBlank } from '@/lib/rich-text';
import {
    readingEntryPreview,
    type ReadingEntry,
    type ReadingSection,
} from '@/lib/reading';
import type { FeedbackType, PracticeQuestionData } from '@/types';

/**
 * One saved question as it is read back: the tutor's question, the learner's
 * answer, and every feedback box that holds something. Each entry is its own
 * card: closed it shows what it leads with, opened it shows the columns the
 * editor named, so a word reads the way it was recorded.
 */
const props = withDefaults(
    defineProps<{
        question: PracticeQuestionData;
        /** The boxes of this question that hold something, in reading order. */
        sections: ReadingSection[];
        /**
         * The id of the entry a search result or collection asked to reveal.
         * Everything else stays closed, so the page stays calm and scannable.
         */
        revealEntryId?: string | null;
    }>(),
    { revealEntryId: null },
);

/** The entries the learner has opened, by their id on the page. */
const openEntries = ref<Set<string>>(
    new Set(props.revealEntryId === null ? [] : [props.revealEntryId]),
);

/** Whether an entry is showing its full details. */
function isEntryOpen(key: string): boolean {
    return openEntries.value.has(key);
}

function setEntryOpen(key: string, open: boolean): void {
    if (open) {
        openEntries.value.add(key);

        return;
    }

    openEntries.value.delete(key);
}

/** The stored ids of one box's entries, in the order the page shows them. */
function entryIds(type: FeedbackType): string[] {
    const value = props.question.feedback[type];

    return Array.isArray(value) ? value.map((entry) => entry.id) : [];
}

/** The key an entry is opened and highlighted by, on this page. */
function entryKey(section: ReadingSection, index: number): string {
    const id = entryIds(section.type)[index];

    return id === undefined || id === ''
        ? `reading-${props.question.position}-section-plain-${section.type}-${index}`
        : `reading-${props.question.position}-entry-${id}`;
}

/** The id an entry carries on the page, when it has one. */
function entryTargetId(
    section: ReadingSection,
    index: number,
): string | undefined {
    const key = entryKey(section, index);

    return key.includes('-plain-') ? undefined : key;
}

/** Whether a table cell holds anything at all. */
function partsHaveContent(entry: ReadingEntry): boolean {
    return entry.details.length > 0;
}

/** Reading pages drop "(optional)": the reader is past the question. */
function friendlyLabel(label: string): string {
    return label.replace(/\s*\(optional\)$/u, '');
}
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-4">
            <div
                v-if="!isRichTextBlank(props.question.question_html)"
                :id="`reading-${props.question.position}-question`"
            >
                <p
                    class="mb-1 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Tutor’s question
                </p>
                <!-- Formatted text is bold and italic around escaped words,
                    so it is safe to show. -->
                <div
                    class="text-base leading-relaxed"
                    v-html="props.question.question_html"
                ></div>
            </div>

            <div
                v-if="!isRichTextBlank(props.question.answer_html)"
                :id="`reading-${props.question.position}-answer`"
            >
                <p
                    class="mb-1 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    My answer
                </p>
                <div
                    class="rounded-lg border border-l-2 border-l-primary/25 bg-muted/40 px-3.5 py-2.5 text-sm leading-relaxed"
                    v-html="props.question.answer_html"
                ></div>
            </div>
        </div>

        <div v-if="props.sections.length > 0" class="space-y-5">
            <section
                v-for="section in props.sections"
                :key="section.type"
                :id="`reading-${props.question.position}-section-${section.type}`"
                class="scroll-mt-20 space-y-2"
            >
                <SectionLabel
                    :tone="section.tone"
                    :count="section.entries.length"
                >
                    {{ section.label }}
                </SectionLabel>

                <ul class="space-y-2">
                    <li
                        v-for="(entry, entryIndex) in section.entries"
                        :id="entryTargetId(section, entryIndex)"
                        :key="entryIndex"
                        class="scroll-mt-24"
                    >
                        <!-- An entry with details opens and closes: closed it
                            keeps the page scannable, opened it shows every
                            column under its editor name. -->
                        <Collapsible
                            v-if="partsHaveContent(entry)"
                            :open="isEntryOpen(entryKey(section, entryIndex))"
                            @update:open="
                                (open) =>
                                    setEntryOpen(
                                        entryKey(section, entryIndex),
                                        open,
                                    )
                            "
                            class="overflow-hidden rounded-lg border bg-card"
                        >
                            <CollapsibleTrigger
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left transition-colors outline-none hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring/50"
                            >
                                <ChevronDownIcon
                                    class="size-3.5 shrink-0 text-muted-foreground transition-transform"
                                    :class="{
                                        'rotate-180': isEntryOpen(
                                            entryKey(section, entryIndex),
                                        ),
                                    }"
                                    aria-hidden="true"
                                />

                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block text-sm font-medium text-foreground"
                                    >
                                        <span
                                            v-if="entry.text.formatted"
                                            v-html="entry.text.value"
                                        ></span>
                                        <template v-else>
                                            {{ entry.text.value }}
                                        </template>
                                    </span>
                                </span>

                                <span
                                    v-if="readingEntryPreview(entry) !== ''"
                                    class="hidden max-w-2/5 shrink-0 truncate text-xs text-muted-foreground sm:block"
                                >
                                    {{ readingEntryPreview(entry) }}
                                </span>
                            </CollapsibleTrigger>

                            <CollapsibleContent>
                                <dl
                                    class="space-y-2.5 border-t bg-muted/20 px-3 py-3"
                                >
                                    <div
                                        v-for="detail in entry.details"
                                        :key="detail.label"
                                        class="sm:grid-cols-[8.5rem_minmax(0,1fr) sm:items-baseline] grid gap-x-4 gap-y-0.5"
                                    >
                                        <dt
                                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{ friendlyLabel(detail.label) }}
                                        </dt>
                                        <dd
                                            class="space-y-1 text-sm leading-relaxed"
                                        >
                                            <template
                                                v-for="(
                                                    part, partIndex
                                                ) in detail.parts"
                                                :key="partIndex"
                                            >
                                                <span
                                                    v-if="
                                                        part.value.trim() === ''
                                                    "
                                                    aria-hidden="true"
                                                ></span>
                                                <span
                                                    v-else-if="part.formatted"
                                                    class="block break-words"
                                                    v-html="part.value"
                                                ></span>
                                                <span
                                                    v-else
                                                    class="block break-words"
                                                    >{{ part.value }}</span
                                                >
                                            </template>
                                        </dd>
                                    </div>
                                </dl>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Notes and the natural version are prose, not
                            cards: they read as the sentence they are. -->
                        <div
                            v-else
                            class="rounded-lg border bg-card px-3 py-2.5 text-sm leading-relaxed"
                        >
                            <span
                                v-if="entry.text.formatted"
                                v-html="entry.text.value"
                            ></span>
                            <template v-else>{{ entry.text.value }}</template>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
