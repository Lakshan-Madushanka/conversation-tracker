<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArchiveIcon,
    ArchiveRestoreIcon,
    ChevronDownIcon,
    ChevronsDownUpIcon,
    ChevronsUpDownIcon,
    InfoIcon,
    PencilIcon,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import BackToCollections from '@/components/BackToCollections.vue';
import BackToLibrary from '@/components/BackToLibrary.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReadingQuestion from '@/components/ReadingQuestion.vue';
import SessionDeleteDialog from '@/components/SessionDeleteDialog.vue';
import SessionMenu from '@/components/SessionMenu.vue';
import StatTile from '@/components/StatTile.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import AppLayout from '@/layouts/AppLayout.vue';
import { cameFromCollections } from '@/lib/collections';
import { shortDate } from '@/lib/format';
import {
    forgetLibrarySession,
    libraryReturnUrl,
    markLibraryRestore,
} from '@/lib/library';
import {
    readingEntrySummary,
    readingSections,
    type ReadingSection,
} from '@/lib/reading';
import { isRichTextBlank, richTextPreview } from '@/lib/rich-text';
import { questionDataHasContent } from '@/lib/feedback';
import {
    readSearchTarget,
    revealSearchTarget,
    type SearchTarget,
} from '@/lib/search-target';
import { restoreSession, archiveSession } from '@/lib/session-archive';
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
 * A session with more than one question opens with every question collapsed
 * into a summary, so the learner can scan it before opening what they need.
 */
const collapsible = computed(() => cards.value.length > 1);

/** The questions the learner has opened. The rest stay at their summary. */
const openQuestions = ref<Set<number>>(new Set());

/** Whether a question is showing its full content. */
function isExpanded(card: QuestionCard): boolean {
    return !collapsible.value || openQuestions.value.has(card.question.id);
}

function setExpanded(card: QuestionCard, open: boolean): void {
    if (open) {
        openQuestions.value.add(card.question.id);

        return;
    }

    openQuestions.value.delete(card.question.id);
}

function expandAll(): void {
    openQuestions.value = new Set(cards.value.map((card) => card.question.id));
}

function collapseAll(): void {
    openQuestions.value = new Set();
}

/** The opening words of a question, for its summary line. */
function questionPreview(question: PracticeQuestionData): string {
    return (
        richTextPreview(question.question_html) ||
        richTextPreview(question.answer_html)
    );
}

/** What a question holds, in one short line. */
function entrySummary(card: QuestionCard): string {
    return readingEntrySummary(card.sections);
}

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

const isArchived = computed(() => props.session.archived_at !== null);

const statusLabel = computed(() =>
    isFinished.value ? 'Finished' : 'Unfinished',
);

/** True while a Restore is on its way, so the button cannot be pressed twice. */
const restoring = ref(false);

/** True while an Archive is on its way, so the menu cannot repeat it. */
const archiving = ref(false);

/** Whether the permanent delete warning is on screen. */
const deleteOpen = ref(false);

/** The page heading, so focus can land somewhere sensible after a change. */
const HEADING_ID = 'session-heading';

/**
 * Archiving or restoring removes the control that was pressed. Move focus to
 * the heading so a keyboard or screen-reader user hears what the page is now,
 * instead of being dropped back at the top of the document.
 */
function focusHeading(): void {
    void nextTick(() => {
        document.getElementById(HEADING_ID)?.focus();
    });
}

/**
 * Bring the session back to the Library without leaving the page. The server
 * sends the session again, now editable, so the page switches to its normal
 * reading state in place.
 */
async function restore(): Promise<void> {
    if (restoring.value) {
        return;
    }

    restoring.value = true;

    try {
        const restored = await restoreSession(props.session.id);

        if (!restored) {
            toast.error(
                'We could not restore this session. Nothing was changed.',
            );

            return;
        }

        toast.success('Session restored.');
        router.reload({ onSuccess: () => focusHeading() });
    } finally {
        restoring.value = false;
    }
}

/**
 * Put the session away without leaving the page. The reading page turns into
 * its archived state, and the message offers Undo for a change of mind.
 */
async function archive(): Promise<void> {
    if (archiving.value) {
        return;
    }

    archiving.value = true;

    try {
        const outcome = await archiveSession(props.session.id);

        if (outcome === 'unfinished') {
            toast.error('Finish this session to archive it.');

            return;
        }

        if (outcome === 'failed') {
            toast.error(
                'We could not archive this session. Nothing was changed.',
            );

            return;
        }

        toast.success('Session archived.', {
            action: {
                label: 'Undo',
                onClick: () => {
                    void undoArchive();
                },
            },
        });

        router.reload({ onSuccess: () => focusHeading() });
    } finally {
        archiving.value = false;
    }
}

/** Undo an archive from the message, returning the page to its normal state. */
async function undoArchive(): Promise<void> {
    const restored = await restoreSession(props.session.id);

    if (!restored) {
        toast.error(
            'We could not restore this session. You can restore it from the Archived view.',
        );

        return;
    }

    toast.success('Session restored.');
    router.reload({ onSuccess: () => focusHeading() });
}

/**
 * The session was deleted from its reading page. Its saved card is dropped
 * from the list waiting behind this page, and the learner returns to the view
 * they came from — the Library or the Archived view — with its filters kept.
 */
function deletedFromReadingPage(deleted: { id: number; topic: string }): void {
    deleteOpen.value = false;
    forgetLibrarySession(deleted.id);
    markLibraryRestore();
    toast.success(`Deleted “${deleted.topic}”.`);
    router.visit(libraryReturnUrl(isArchived.value));
}

/** Someone else deleted the session before this warning was opened. */
function missingFromReadingPage(): void {
    deleteOpen.value = false;
    forgetLibrarySession(props.session.id);
    markLibraryRestore();
    toast.info('This session was already deleted.');
    router.visit(libraryReturnUrl(isArchived.value));
}

/** Archive was chosen instead of Delete, so the reading page turns read-only. */
function archivedFromDialog(): void {
    deleteOpen.value = false;

    toast.success('Session archived.', {
        action: {
            label: 'Undo',
            onClick: () => {
                void undoArchive();
            },
        },
    });

    router.reload({ onSuccess: () => focusHeading() });
}

const description = computed(() => {
    const count = `${cards.value.length} ${
        cards.value.length === 1 ? 'question' : 'questions'
    }`;

    return isFinished.value
        ? `Finished ${shortDate(props.session.finished_at ?? '')} · ${count}`
        : `Unfinished · ${count}`;
});

/** A calm note when a search result no longer points at anything. */
const targetNotice = ref('');

/** Whether this session was opened from a collection item. */
const fromCollection = ref(false);

onMounted(() => {
    fromCollection.value = cameFromCollections(window.location.href);
    revealTarget();
});

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

    // A long session starts with its questions collapsed; the one holding the
    // target has to open before its content exists to be found.
    if (collapsible.value) {
        openQuestions.value.add(card.question.id);
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

function itemHasMoved(): void {
    targetNotice.value =
        'The item you searched for has changed since you found it. The session has opened at the top so you can look around.';

    window.scrollTo({ top: 0 });
}
</script>

<template>
    <div>
        <Head :title="props.session.topic.name" />

        <BackToCollections v-if="fromCollection" />
        <BackToLibrary v-else :archived="isArchived" />

        <PageHeader
            :title="props.session.topic.name"
            :description="description"
            :heading-id="HEADING_ID"
        >
            <template #actions>
                <Badge
                    :variant="
                        isArchived
                            ? 'outline'
                            : isFinished
                              ? 'default'
                              : 'secondary'
                    "
                    class="hidden sm:inline-flex"
                >
                    {{ isArchived ? 'Archived' : statusLabel }}
                </Badge>

                <SessionMenu
                    :topic="props.session.topic.name"
                    :edit-href="
                        isArchived ? null : sessionsEdit.url(props.session.id)
                    "
                    :archived="isArchived"
                    :finished="isFinished"
                    @archive="archive"
                    @restore="restore"
                    @delete="deleteOpen = true"
                />

                <Button v-if="!isArchived" size="lg" as-child>
                    <Link :href="sessionsEdit.url(props.session.id)">
                        <PencilIcon aria-hidden="true" />
                        Edit session
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="isArchived" class="mb-6">
            <ArchiveIcon aria-hidden="true" />
            <AlertTitle>This session is archived</AlertTitle>
            <AlertDescription>
                <p>
                    It is read-only until you restore it. Its content, dates and
                    totals stay exactly as they are.
                </p>
                <Button
                    type="button"
                    :disabled="restoring"
                    class="mt-3"
                    @click="restore"
                >
                    <ArchiveRestoreIcon aria-hidden="true" />
                    {{ restoring ? 'Restoring…' : 'Restore session' }}
                </Button>
            </AlertDescription>
        </Alert>

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
            <!-- One control opens or shrinks every question at once, for a
                session with more than one. -->
            <div
                v-if="collapsible"
                class="flex flex-wrap items-center justify-end gap-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="expandAll"
                >
                    <ChevronsUpDownIcon aria-hidden="true" />
                    Expand all
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="collapseAll"
                >
                    <ChevronsDownUpIcon aria-hidden="true" />
                    Collapse all
                </Button>
            </div>

            <Collapsible
                v-for="(card, index) in cards"
                :key="card.question.id"
                :open="isExpanded(card)"
                @update:open="(open) => setExpanded(card, open)"
                as-child
            >
                <Card class="gap-0 py-0">
                    <CollapsibleTrigger
                        class="flex w-full items-start gap-3 rounded-xl px-4 py-3 text-left transition-colors outline-none hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <ChevronDownIcon
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': isExpanded(card) }"
                            aria-hidden="true"
                        />

                        <span class="min-w-0 flex-1">
                            <span class="block text-base font-semibold">
                                Question {{ index + 1 }}
                            </span>
                            <span
                                class="mt-0.5 block truncate text-xs text-muted-foreground"
                            >
                                {{
                                    questionPreview(card.question) ||
                                    'No question text'
                                }}
                            </span>
                            <span
                                v-if="entrySummary(card)"
                                class="mt-1 block text-xs text-muted-foreground sm:hidden"
                            >
                                {{ entrySummary(card) }}
                            </span>
                        </span>

                        <span
                            v-if="entrySummary(card)"
                            class="hidden shrink-0 text-right text-xs text-muted-foreground sm:block"
                        >
                            {{ entrySummary(card) }}
                        </span>
                    </CollapsibleTrigger>

                    <CollapsibleContent>
                        <CardContent class="px-4 pb-4">
                            <ReadingQuestion
                                :question="card.question"
                                :sections="card.sections"
                            />
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>
        </div>

        <p v-else class="text-sm text-muted-foreground">
            Nothing has been written into this session yet.
        </p>

        <!-- On a phone, the Edit button is repeated at the bottom so it is
            always within reach after reading a long session. -->
        <div v-if="!isArchived" class="mt-8 flex justify-center sm:hidden">
            <Button size="lg" as-child>
                <Link :href="sessionsEdit.url(props.session.id)">
                    <PencilIcon aria-hidden="true" />
                    Edit session
                </Link>
            </Button>
        </div>

        <SessionDeleteDialog
            :open="deleteOpen"
            :session-id="props.session.id"
            :topic="props.session.topic.name"
            :session-date="props.session.session_date"
            @update:open="deleteOpen = $event"
            @deleted="deletedFromReadingPage"
            @archived="archivedFromDialog"
            @missing="missingFromReadingPage"
        />
    </div>
</template>
