<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, PencilIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import StatTile from '@/components/StatTile.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { shortDate } from '@/lib/format';
import {
    readingSections,
    type ReadingEntry,
    type ReadingPart,
    type ReadingSection,
} from '@/lib/reading';
import { isRichTextBlank } from '@/lib/rich-text';
import { questionDataHasContent } from '@/lib/feedback';
import {
    edit as sessionsEdit,
    index as sessionsIndex,
} from '@/routes/sessions';
import type {
    PracticeQuestionData,
    PracticeSessionData,
    SessionCounts,
} from '@/types';

defineOptions({ layout: AppLayout });

interface QuestionCard {
    question: PracticeQuestionData;
    /** The boxes that hold something, ready to be shown. */
    sections: ReadingSection[];
}

const props = defineProps<{
    /** A saved session, in the same shape the editor opens it. */
    session: PracticeSessionData;
}>();

/**
 * The questions worth showing. An unfinished session can still hold a card
 * the learner never wrote anything into, and there is nothing to read there.
 */
const cards = computed<QuestionCard[]>(() =>
    props.session.questions
        .map((question) => ({
            question,
            sections: readingSections(question),
        }))
        .filter(
            ({ question, sections }) =>
                sections.length > 0 ||
                !isRichTextBlank(question.question_html) ||
                !isRichTextBlank(question.answer_html),
        ),
);

/**
 * How much the session holds, counted from the entries on this page so the
 * tiles match what is shown. Only non-empty entries and questions count.
 */
const counts = computed<SessionCounts>(() => {
    const result: SessionCounts = {
        questions: 0,
        words: 0,
        phrases: 0,
        mistakes: 0,
        grammar_notes: 0,
    };

    for (const { question } of cards.value) {
        if (questionDataHasContent(question)) {
            result.questions += 1;
        }

        const feedback = question.feedback;

        result.mistakes += nonBlank(feedback.mistake).length;
        result.words += nonBlank(feedback.vocabulary).length;
        result.phrases += nonBlank(feedback.phrase).length;
        result.grammar_notes += nonBlank(feedback.grammar_note).length;
    }

    return result;
});

/** The entries of one box that hold something. */
function nonBlank(entries: unknown): unknown[] {
    if (!Array.isArray(entries)) {
        return [];
    }

    return entries.filter((entry) => {
        if (typeof entry !== 'object' || entry === null) {
            return false;
        }

        return Object.entries(entry).some(
            ([key, value]) => key !== 'id' && String(value ?? '').trim() !== '',
        );
    });
}

const isFinished = computed(() => props.session.finished_at !== null);

const statusLabel = computed(() =>
    isFinished.value ? 'Finished' : 'Unfinished',
);

const description = computed(() => {
    const count = `${cards.value.length} ${
        cards.value.length === 1 ? 'question' : 'questions'
    }`;

    return isFinished.value
        ? `Finished ${shortDate(props.session.finished_at ?? '')} · ${count}`
        : `Unfinished · ${count}`;
});

/** What an entry shows, in order: what it is, then what explains it. */
function partsOf(entry: ReadingEntry): ReadingPart[] {
    return [entry.text, ...entry.details];
}
</script>

<template>
    <div>
        <Link
            :href="sessionsIndex.url()"
            class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <ArrowLeftIcon class="size-4" aria-hidden="true" />
            Library
        </Link>

        <PageHeader
            :title="props.session.topic.name"
            :description="description"
        >
            <template #actions>
                <Badge
                    :variant="isFinished ? 'default' : 'secondary'"
                    class="hidden sm:inline-flex"
                >
                    {{ statusLabel }}
                </Badge>
                <Button size="lg" as-child>
                    <Link :href="sessionsEdit.url(props.session.id)">
                        <PencilIcon aria-hidden="true" />
                        Edit session
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Small tiles for what the session holds, matching the counts the
            editor shows when finishing. -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <StatTile
                label="Questions"
                :value="counts.questions"
                :hint="counts.questions === 1 ? '1 question' : 'All questions'"
            />
            <StatTile
                label="Words"
                :value="counts.words"
                hint="New vocabulary"
            />
            <StatTile
                label="Phrases"
                :value="counts.phrases"
                hint="New expressions"
            />
            <StatTile
                label="Mistakes"
                :value="counts.mistakes"
                hint="Learning opportunities"
            />
            <StatTile
                label="Grammar notes"
                :value="counts.grammar_notes"
                hint="To remember"
            />
        </div>

        <div v-if="cards.length > 0" class="grid gap-4">
            <Card v-for="(card, index) in cards" :key="card.question.id">
                <CardHeader>
                    <CardTitle class="text-base">
                        Question {{ index + 1 }}
                    </CardTitle>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="space-y-3">
                        <div
                            v-if="!isRichTextBlank(card.question.question_html)"
                        >
                            <p
                                class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Tutor’s question
                            </p>
                            <!-- Formatted text is bold and italic around
                                escaped words, so it is safe to show. -->
                            <div
                                class="text-sm"
                                v-html="card.question.question_html"
                            ></div>
                        </div>

                        <div v-if="!isRichTextBlank(card.question.answer_html)">
                            <p
                                class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                My answer
                            </p>
                            <div
                                class="text-sm text-muted-foreground"
                                v-html="card.question.answer_html"
                            ></div>
                        </div>
                    </div>

                    <template v-if="card.sections.length > 0">
                        <Separator />

                        <dl class="space-y-3">
                            <div
                                v-for="section in card.sections"
                                :key="section.label"
                            >
                                <dt class="mb-1">
                                    <SectionLabel :tone="section.tone">
                                        {{ section.label }}
                                    </SectionLabel>
                                </dt>

                                <dd class="space-y-1.5">
                                    <div
                                        v-for="(
                                            entry, entryIndex
                                        ) in section.entries"
                                        :key="entryIndex"
                                        class="text-sm leading-relaxed"
                                    >
                                        <template
                                            v-for="(part, partIndex) in partsOf(
                                                entry,
                                            )"
                                            :key="partIndex"
                                        >
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
                                            <template v-else>{{
                                                part.value
                                            }}</template>
                                        </template>
                                    </div>
                                </dd>
                            </div>
                        </dl>
                    </template>
                </CardContent>
            </Card>
        </div>

        <p v-else class="text-sm text-muted-foreground">
            Nothing has been written into this session yet.
        </p>

        <!-- On a phone, the Edit button is repeated at the bottom so it is
            always within reach after reading a long session. -->
        <div class="mt-8 flex justify-center sm:hidden">
            <Button size="lg" as-child>
                <Link :href="sessionsEdit.url(props.session.id)">
                    <PencilIcon aria-hidden="true" />
                    Edit session
                </Link>
            </Button>
        </div>
    </div>
</template>
