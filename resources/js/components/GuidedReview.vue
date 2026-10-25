<script setup lang="ts">
import { computed } from 'vue';
import { CheckIcon, PlusIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { scanBoxAttention } from '@/lib/attention';
import { feedbackTypeFor, GUIDED_STEPS, guidedEntryCount } from '@/lib/guided';
import { isRichTextBlank, richTextToText } from '@/lib/rich-text';
import type { GuidedView } from '@/lib/guided';
import type { PracticeQuestionForm } from '@/types';

const props = withDefaults(
    defineProps<{
        question: PracticeQuestionForm;
        /** Whether the session is already holding as many as it can. */
        addDisabled?: boolean;
        /** Whether a finish is already on its way. */
        finishDisabled?: boolean;
    }>(),
    { addDisabled: false, finishDisabled: false },
);

const emit = defineEmits<{
    (event: 'change', view: GuidedView): void;
    (event: 'fix', view: GuidedView, entryId: string): void;
    (event: 'add'): void;
    (event: 'finish'): void;
}>();

/** One row of the review: what a section holds and where it can be revisited. */
interface ReviewSection {
    view: GuidedView;
    label: string;
    summary: string;
    attention: string | null;
    fixEntryId: string | null;
}

/** The opening words of a piece of rich text, or the fact that there are none. */
function proseSummary(html: string): string {
    const text = richTextToText(html).replace(/\s+/gu, ' ').trim();

    if (text === '') {
        return 'Nothing yet.';
    }

    return text.length > 80 ? `${text.slice(0, 80)}…` : text;
}

/** What a box holds, in one short line. */
function boxSummary(view: GuidedView): string {
    const type = feedbackTypeFor(view);

    if (type === null) {
        return '';
    }

    const count = guidedEntryCount(props.question, view) ?? 0;

    if (count === 0) {
        return 'Nothing yet.';
    }

    const kind = view === 'grammar_note' ? 'note' : 'row';

    return count === 1 ? `1 ${kind}` : `${count} ${kind}s`;
}

/** Whether a row of this box is waiting for a correction or a meaning. */
function boxAttention(view: GuidedView): {
    count: number;
    reason: string | null;
    entryId: string | null;
} {
    if (view !== 'mistake' && view !== 'vocabulary') {
        return { count: 0, reason: null, entryId: null };
    }

    const scan = scanBoxAttention(props.question, view);

    return {
        count: scan.count,
        reason: scan.reason,
        entryId: scan.firstEntryId,
    };
}

const sections = computed<ReviewSection[]>(() =>
    GUIDED_STEPS.map((step) => {
        const attention = boxAttention(step.id);

        return {
            view: step.id,
            label: step.label,
            summary:
                step.id === 'question'
                    ? proseSummary(props.question.question_html)
                    : step.id === 'answer'
                      ? proseSummary(props.question.answer_html)
                      : step.id === 'natural_version'
                        ? proseSummary(props.question.feedback.natural_version)
                        : boxSummary(step.id),
            attention:
                attention.count === 0
                    ? null
                    : attention.count === 1
                      ? attention.reason
                      : `${attention.count} rows still need attention.`,
            fixEntryId: attention.count > 0 ? attention.entryId : null,
        };
    }),
);

/** Ask to revisit one section, or to land on the row still waiting. */
function fix(section: ReviewSection): void {
    if (section.fixEntryId === null) {
        return;
    }

    emit('fix', section.view, section.fixEntryId);
}
</script>

<template>
    <div class="grid gap-4">
        <ol class="grid gap-2">
            <li
                v-for="section in sections"
                :key="section.view"
                class="flex flex-wrap items-start gap-3 rounded-lg border bg-card p-3"
            >
                <div class="min-w-0 flex-1 basis-40">
                    <p class="text-sm font-medium">{{ section.label }}</p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ section.summary }}
                    </p>
                    <p
                        v-if="section.attention"
                        class="mt-1 text-xs font-medium text-destructive"
                    >
                        {{ section.attention }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <Button
                        v-if="section.fixEntryId !== null"
                        type="button"
                        size="sm"
                        variant="secondary"
                        @click="fix(section)"
                    >
                        Fix
                        <span class="sr-only">{{ section.label }}</span>
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="emit('change', section.view)"
                    >
                        Change
                        <span class="sr-only">{{ section.label }}</span>
                    </Button>
                </div>
            </li>
        </ol>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                variant="outline"
                :disabled="props.addDisabled"
                @click="emit('add')"
            >
                <PlusIcon aria-hidden="true" />
                Add another question
            </Button>

            <Button
                type="button"
                class="ml-auto"
                :disabled="props.finishDisabled"
                @click="emit('finish')"
            >
                <CheckIcon aria-hidden="true" />
                Finish session
            </Button>
        </div>
    </div>
</template>
