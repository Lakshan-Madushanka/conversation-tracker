<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { InfoIcon, PencilIcon } from '@lucide/vue';
import BackToLibrary from '@/components/BackToLibrary.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import StatTile from '@/components/StatTile.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    readSearchTarget,
    revealSearchTarget,
    type SearchTarget,
} from '@/lib/search-target';
import { edit as sessionsEdit } from '@/routes/sessions';
import type {
    FeedbackType,
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

/** A calm note when a search result no longer points at anything. */
const targetNotice = ref('');

onMounted(revealTarget);

/**
 * Take the learner to the item a search result named. The item is found
 * before anything is scrolled, so a session that has changed since the search
 * opens calmly at the top instead of jumping to the wrong place.
 */
function revealTarget(): void {
    const target = readSearchTarget(window.location.href);

    if (target === null || target.question === null) {
        return;
    }

    const card = cards.value.find(
        (candidate) => candidate.question.position === target.question,
    );

    if (card === undefined) {
        itemHasMoved();

        return;
    }

    const id = targetElementId(card, target);

    if (id === null) {
        itemHasMoved();

        return;
    }

    revealSearchTarget(id, (found) => {
        if (!found) {
            itemHasMoved();
        }
    });
}

/** The id of the element a target names, or null when it is gone. */
function targetElementId(
    card: QuestionCard,
    target: SearchTarget,
): string | null {
    const position = card.question.position;

    if (target.field === 'question') {
        return `reading-${position}-question`;
    }

    if (target.field === 'answer') {
        return `reading-${position}-answer`;
    }

    if (target.field !== 'entry' || target.type === null) {
        return null;
    }

    const section = card.sections.find(
        (candidate) => candidate.type === target.type,
    );

    if (section === undefined) {
        return null;
    }

    // The natural version is one piece of text, so its section is the item.
    if (section.type === 'natural_version') {
        return `reading-${position}-section-natural_version`;
    }

    const index = entryIndexFor(card, section.type, target);

    if (index === null) {
        return null;
    }

    const id = entryIds(card, section.type)[index];

    return id === undefined || id === ''
        ? `reading-${position}-section-${section.type}`
        : `reading-${position}-entry-${id}`;
}

/** The place of a matching entry in its box, by id first and position second. */
function entryIndexFor(
    card: QuestionCard,
    type: FeedbackType,
    target: { entry: string | null; position: number | null },
): number | null {
    const ids = entryIds(card, type);

    if (target.entry !== null) {
        const byId = ids.indexOf(target.entry);

        if (byId >= 0) {
            return byId;
        }
    }

    if (target.position === null) {
        return null;
    }

    // Entry positions are stored zero-based, matching the array order.
    return target.position < ids.length ? target.position : null;
}

/** The stored ids of one box's entries, in the order the page shows them. */
function entryIds(card: QuestionCard, type: FeedbackType): string[] {
    const value = card.question.feedback[type];

    return Array.isArray(value) ? value.map((entry) => entry.id) : [];
}

/** The id of one entry on the reading page, when it has one. */
function entryTargetId(
    card: QuestionCard,
    section: ReadingSection,
    index: number,
): string | undefined {
    const id = entryIds(card, section.type)[index];

    return id === undefined || id === ''
        ? undefined
        : `reading-${card.question.position}-entry-${id}`;
}

function itemHasMoved(): void {
    targetNotice.value =
        'The item you searched for has changed since you found it. The session has opened at the top so you can look around.';

    window.scrollTo({ top: 0 });
}
</script>

<template>
    <div>
        <Head :title="props.session.topic.name" />

        <BackToLibrary />

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

        <Alert v-if="targetNotice !== ''" class="mb-6" role="status">
            <InfoIcon aria-hidden="true" />
            <AlertTitle>That item has changed</AlertTitle>
            <AlertDescription>{{ targetNotice }}</AlertDescription>
        </Alert>

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
                            :id="`reading-${card.question.position}-question`"
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

                        <div
                            v-if="!isRichTextBlank(card.question.answer_html)"
                            :id="`reading-${card.question.position}-answer`"
                        >
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
                                :key="section.type"
                                :id="`reading-${card.question.position}-section-${section.type}`"
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
                                        :id="
                                            entryTargetId(
                                                card,
                                                section,
                                                entryIndex,
                                            )
                                        "
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
                                                class="sr-only"
                                            >
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
