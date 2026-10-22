<script setup lang="ts">
import { computed, onBeforeUnmount, ref, type Ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    CheckIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    EyeIcon,
    PlusIcon,
    RefreshCwIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import EmptySessionDialog from '@/components/EmptySessionDialog.vue';
import FinishSessionSummary from '@/components/FinishSessionSummary.vue';
import PageHeader from '@/components/PageHeader.vue';
import QuestionCard from '@/components/QuestionCard.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import TopicField from '@/components/TopicField.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAutoSave } from '@/composables/useAutoSave';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AttentionFocus } from '@/lib/attention';
import { scanAttention } from '@/lib/attention';
import { focusElementById } from '@/lib/dom';
import {
    emptyFeedbackValues,
    feedbackBoxes,
    questionHasContent,
    setFeedbackValue,
    toQuestionForm,
} from '@/lib/feedback';
import { savePracticeSession } from '@/lib/session-autosave';
import { discardSession, finishSession } from '@/lib/session-finish';
import { home } from '@/routes';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type {
    FeedbackType,
    FinishSummary,
    PracticeQuestionData,
    PracticeQuestionForm,
    PracticeSessionData,
    TopicSummary,
} from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    topics: TopicSummary[];
    session?: PracticeSessionData | null;
}>();

/** Matches the server's limit so the button and message agree. */
const MAX_QUESTIONS = 50;

/** Past this many questions, cards collapse into one-line summaries. */
const COLLAPSE_THRESHOLD = 5;

let uidCounter = 0;

function nextUid(): string {
    uidCounter += 1;

    return `q${uidCounter}`;
}

function emptyQuestion(): PracticeQuestionForm {
    return {
        uid: nextUid(),
        question_html: '',
        answer_html: '',
        feedback: emptyFeedbackValues(),
    };
}

/** Saved questions become editable ones; a new session starts with one empty. */
function initialQuestions(): PracticeQuestionForm[] {
    const saved = props.session?.questions ?? [];

    if (saved.length === 0) {
        return [emptyQuestion()];
    }

    return saved.map((question) => toQuestionForm(question, nextUid()));
}

function questionsFromPayload(
    saved: PracticeQuestionData[],
): PracticeQuestionForm[] {
    if (saved.length === 0) {
        return [emptyQuestion()];
    }

    return saved.map((question) => toQuestionForm(question, nextUid()));
}

const topic = ref(props.session?.topic.name ?? '');
const questions = ref<PracticeQuestionForm[]>(initialQuestions());

/**
 * Collapsed question cards, for long sessions. A card the learner has opened
 * at least once stays mounted so collapsing never loses typed text.
 */
const collapsible = computed(() => questions.value.length > COLLAPSE_THRESHOLD);

const collapsed = ref<Set<string>>(
    new Set(
        collapsible.value ? questions.value.slice(1).map((q) => q.uid) : [],
    ),
);

/** Cards that have been expanded at least once, so they stay mounted. */
const opened = ref<Set<string>>(new Set());

/** Whether a card is showing its full editor. */
function isExpanded(uid: string): boolean {
    return !collapsed.value.has(uid);
}

/** Whether a card's QuestionCard should be mounted in the DOM. */
function isMounted(uid: string): boolean {
    return isExpanded(uid) || opened.value.has(uid);
}

function collapseCard(uid: string): void {
    opened.value.add(uid);
    collapsed.value.add(uid);
}

function expandCard(uid: string): void {
    opened.value.add(uid);
    collapsed.value.delete(uid);
}

function toggleCard(uid: string): void {
    if (isExpanded(uid)) {
        collapseCard(uid);
    } else {
        expandCard(uid);
    }
}

function expandAll(): void {
    for (const q of questions.value) {
        expandCard(q.uid);
    }
}

function collapseAll(): void {
    for (const q of questions.value) {
        collapseCard(q.uid);
    }
}

/** How many feedback entries a question's collapsed card summarizes. */
function feedbackCount(question: PracticeQuestionForm): number {
    const feedback = question.feedback;
    let count = 0;

    if (Array.isArray(feedback.mistake)) count += feedback.mistake.length;
    if (Array.isArray(feedback.vocabulary)) count += feedback.vocabulary.length;
    if (Array.isArray(feedback.phrase)) count += feedback.phrase.length;
    if (Array.isArray(feedback.grammar_note))
        count += feedback.grammar_note.length;
    if (
        typeof feedback.natural_version === 'string' &&
        feedback.natural_version.trim() !== ''
    )
        count += 1;

    return count;
}

/** The first few words of the tutor's question, for the collapsed card. */
function questionPreview(question: PracticeQuestionForm): string {
    const text = question.question_html
        .replace(/<[^>]*>/gu, ' ')
        .replace(/\s+/gu, ' ')
        .trim();

    return text.length > 80 ? `${text.slice(0, 80)}…` : text;
}

const hasTopic = (): boolean => topic.value.trim().length > 0;
const hasContent = (): boolean => questions.value.some(questionHasContent);

const {
    sessionId,
    version,
    status,
    errors,
    conflict,
    lastSavedAt,
    retry,
    loadLatest,
    keepMine,
    flush,
    saveNow,
} = useAutoSave({
    snapshot: () => ({ topic: topic.value, questions: questions.value }),
    save: savePracticeSession,
    hasTopic,
    hasContent,
    sessionId: props.session?.id ?? null,
    version: props.session?.version ?? null,
    onSessionCreated: (session) => {
        // Move the address to the saved session without reloading the page, so
        // focus and the caret stay where they are.
        window.history.replaceState(
            window.history.state,
            '',
            sessionsEdit.url(session.id),
        );
    },
    onLoadLatest: (session) => {
        topic.value = session.topic.name;
        questions.value = questionsFromPayload(session.questions);
    },
});

/** Set once a discard is on its way, so nothing is saved after it. */
let discarding = false;

// Leaving the editor (for example, tapping a link in the app) should not drop
// unsaved work: send a last best-effort save on the way out. A session being
// thrown away is the one thing that must not be saved again on the way out.
onBeforeUnmount(() => {
    if (discarding) {
        return;
    }

    flush();
});

function errorFor(key: string): string | undefined {
    return errors.value[key];
}

function feedbackErrors(index: number): Partial<Record<FeedbackType, string>> {
    const messages: Partial<Record<FeedbackType, string>> = {};

    for (const box of feedbackBoxes) {
        const message = errorFor(`questions.${index}.feedback.${box.type}`);

        if (message) {
            messages[box.type] = message;
        }
    }

    return messages;
}

const hasSession = computed(() => sessionId.value !== null);

/** When the session was finished, or null while it is still unfinished. */
const finishedAt = ref<string | null>(props.session?.finished_at ?? null);
const isFinished = computed(() => finishedAt.value !== null);

const description = computed(() => {
    if (isFinished.value) {
        return 'Saved and finished. Reopen it any time to add more.';
    }

    if (hasSession.value) {
        return 'Update the topic and questions. Your session stays unfinished until you finish it.';
    }

    return 'Add a topic, then paste the tutor’s questions and feedback.';
});

const questionCount = computed(() => questions.value.length);
const atLimit = computed(() => questionCount.value >= MAX_QUESTIONS);
const questionsError = computed(() => errorFor('questions'));

const needsTopicHighlight = computed(
    () => status.value === 'needs_topic' && hasContent(),
);

/** A calm, plain-language banner shown when a save did not go through. */
const failureMessage = computed(() => {
    if (Object.keys(errors.value).length > 0) {
        return 'Some changes were not saved. Check the highlighted fields, then try again.';
    }

    if (status.value === 'retrying') {
        return 'We could not save just now. We are trying again.';
    }

    return 'We could not save your changes. Your text is still here.';
});

const removalOpen = ref(false);
const pendingRemovalIndex = ref<number | null>(null);

const removalNumber = computed(() => (pendingRemovalIndex.value ?? 0) + 1);

function addQuestion(): void {
    if (atLimit.value) {
        return;
    }

    const question = emptyQuestion();

    questions.value.push(question);

    // A newly added card is always shown in full, ready to fill in.
    opened.value.add(question.uid);
    collapsed.value.delete(question.uid);

    focusElementById(`question-${question.uid}`);
}

function removeQuestion(index: number): void {
    // A session always keeps at least one question card.
    if (questions.value.length <= 1) {
        questions.value = [emptyQuestion()];

        return;
    }

    questions.value.splice(index, 1);
}

function requestRemove(index: number): void {
    const question = questions.value[index];

    if (!question || !questionHasContent(question)) {
        removeQuestion(index);

        return;
    }

    pendingRemovalIndex.value = index;
    removalOpen.value = true;
}

function confirmRemove(): void {
    const index = pendingRemovalIndex.value;

    removalOpen.value = false;
    pendingRemovalIndex.value = null;

    if (index === null) {
        return;
    }

    removeQuestion(index);
}

/** Rows still missing a correction or a meaning, and where the first one is. */
const attention = computed(() => scanAttention(questions.value));

const summaryOpen = ref(false);
const summaryResult = ref<FinishSummary | null>(null);
const emptyOpen = ref(false);
const finishing = ref(false);

/** The row "Review them" should send focus to once the summary is closed. */
const focusTarget = ref<AttentionFocus | null>(null);

/**
 * Brings the page in line with what the server kept: the new version, the
 * moment it was finished, and the cards the server found empty and removed.
 * Taking the version first means the change the pruning makes is not then
 * read as an edit made somewhere else.
 */
function applyFinished(result: FinishSummary): void {
    version.value = result.session.version;
    finishedAt.value = result.session.finished_at;

    // The server dropped the cards that held nothing; drop them here too, so
    // the next save does not put them back. Nothing is touched when every card
    // was worth keeping.
    const kept = questions.value.filter(questionHasContent);

    if (kept.length === questions.value.length) {
        return;
    }

    questions.value = kept.length > 0 ? kept : [emptyQuestion()];
}

/**
 * Finishes the session: everything on screen is saved first, so the summary
 * describes what is actually stored, and then the server is asked to finish it.
 * A session with nothing in it is never finished quietly — the learner is
 * offered the choice instead.
 */
async function finishNow(): Promise<void> {
    if (finishing.value) {
        return;
    }

    focusTarget.value = null;

    if (sessionId.value === null && !hasContent()) {
        emptyOpen.value = true;

        return;
    }

    if (!hasTopic()) {
        toast.error('Add a topic before finishing the session.');

        return;
    }

    // A change made elsewhere has to be settled first; the banner above says so.
    if (conflict.value !== null) {
        return;
    }

    finishing.value = true;

    try {
        if (!(await saveNow())) {
            // The banner above already says what stood in the way.
            return;
        }

        const id = sessionId.value;

        if (id === null) {
            return;
        }

        const result = await finishSession(id);

        if (result === null) {
            toast.error(
                'We could not finish this session. Your text is still here.',
            );

            return;
        }

        if (result.empty) {
            emptyOpen.value = true;

            return;
        }

        applyFinished(result);
        summaryResult.value = result;
        summaryOpen.value = true;
    } finally {
        finishing.value = false;
    }
}

/** Send focus to the row that still needs work, once the summary is gone. */
function reviewAttention(): void {
    focusTarget.value = attention.value.first;
}

/** Throw away a session that holds nothing, or go home if none was made. */
async function discardEmptySession(): Promise<void> {
    emptyOpen.value = false;

    const id = sessionId.value;

    if (id === null) {
        discarding = true;
        router.visit(home.url());

        return;
    }

    const outcome = await discardSession(id);

    if (outcome === 'has_content') {
        toast.error('This session now holds something, so it was kept.');

        return;
    }

    if (outcome === 'failed') {
        toast.error('The session could not be discarded. Nothing was removed.');

        return;
    }

    discarding = true;
    toast.success('Session discarded.');
    router.visit(home.url());
}
</script>

<template>
    <div>
        <PageHeader
            :title="hasSession ? 'Edit session' : 'New session'"
            :description="description"
        >
            <template #actions>
                <Badge v-if="hasSession" variant="outline" class="gap-1.5">
                    <span
                        class="size-1.5 rounded-full"
                        :class="isFinished ? 'bg-emerald-500' : 'bg-amber-500'"
                        aria-hidden="true"
                    />
                    {{ isFinished ? 'Finished' : 'Unfinished' }}
                </Badge>

                <!-- A finished session can always be read back in its own
                    calm page, without leaving the editor's address behind. -->
                <Link
                    v-if="isFinished && sessionId !== null"
                    :href="sessionsShow.url(sessionId)"
                    class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <EyeIcon class="size-4" aria-hidden="true" />
                    View session
                </Link>

                <SaveIndicator :status="status" :last-saved-at="lastSavedAt" />

                <Button type="button" :disabled="finishing" @click="finishNow">
                    <CheckIcon aria-hidden="true" />
                    Finish session
                </Button>
            </template>
        </PageHeader>

        <!-- A change made elsewhere stops saving until resolved in place. -->
        <div
            v-if="conflict"
            role="alert"
            class="mb-4 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-3 text-sm"
        >
            <p class="flex items-start gap-2 font-medium">
                <TriangleAlertIcon
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                This session was changed somewhere else.
            </p>
            <p class="mt-1 pl-6 text-muted-foreground">
                Load the latest version to see that work, or keep the text on
                this screen and save over it.
            </p>
            <div class="mt-3 flex flex-wrap gap-2 pl-6">
                <Button type="button" size="sm" @click="loadLatest">
                    Load latest version
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="keepMine"
                >
                    Keep what I have
                </Button>
            </div>
        </div>

        <!-- Saving is failing; keep the text on screen and offer a retry. -->
        <div
            v-else-if="status === 'retrying' || status === 'failed'"
            role="alert"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm"
        >
            <p class="flex items-start gap-2">
                <TriangleAlertIcon
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ failureMessage }}
            </p>
            <Button type="button" size="sm" variant="outline" @click="retry">
                <RefreshCwIcon aria-hidden="true" />
                Retry
            </Button>
        </div>

        <form class="grid gap-4" novalidate @submit.prevent>
            <TopicField
                v-model="topic"
                :suggestions="props.topics"
                :error="errors.topic"
                :highlight="needsTopicHighlight"
            />

            <div class="grid gap-4">
                <div v-for="(question, index) in questions" :key="question.uid">
                    <!-- Long sessions open with the first card expanded and
                        the rest collapsed into one-line summaries. -->
                    <template v-if="collapsible">
                        <button
                            v-if="!isExpanded(question.uid)"
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg border border-dashed bg-muted/20 px-3 py-2 text-left hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-expanded="false"
                            :aria-label="`Expand question ${index + 1}`"
                            @click="expandCard(question.uid)"
                        >
                            <ChevronDownIcon
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="text-sm font-medium">
                                Question {{ index + 1 }}
                            </span>
                            <span
                                class="min-w-0 flex-1 truncate text-xs text-muted-foreground"
                            >
                                {{
                                    questionPreview(question) ||
                                    'Empty question'
                                }}
                            </span>
                            <span
                                v-if="feedbackCount(question) > 0"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ feedbackCount(question) }}
                                {{
                                    feedbackCount(question) === 1
                                        ? 'entry'
                                        : 'entries'
                                }}
                            </span>
                        </button>

                        <div
                            v-if="isMounted(question.uid)"
                            :class="{ hidden: !isExpanded(question.uid) }"
                        >
                            <QuestionCard
                                :number="index + 1"
                                :uid="question.uid"
                                v-model:question-html="question.question_html"
                                v-model:answer-html="question.answer_html"
                                :feedback="question.feedback"
                                :question-error="
                                    errorFor(`questions.${index}.question_html`)
                                "
                                :answer-error="
                                    errorFor(`questions.${index}.answer_html`)
                                "
                                :feedback-errors="feedbackErrors(index)"
                                :focus-target="focusTarget"
                                @update:feedback="
                                    (type, value) =>
                                        setFeedbackValue(
                                            question.feedback,
                                            type,
                                            value,
                                        )
                                "
                                @remove="requestRemove(index)"
                            />

                            <!-- Collapse control sits under the expanded card
                                so the learner can shrink it without losing
                                anything. -->
                            <button
                                type="button"
                                class="mt-1 flex w-full items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :aria-label="`Collapse question ${index + 1}`"
                                @click="collapseCard(question.uid)"
                            >
                                <ChevronUpIcon
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Collapse
                            </button>
                        </div>
                    </template>

                    <QuestionCard
                        v-else
                        :number="index + 1"
                        :uid="question.uid"
                        v-model:question-html="question.question_html"
                        v-model:answer-html="question.answer_html"
                        :feedback="question.feedback"
                        :question-error="
                            errorFor(`questions.${index}.question_html`)
                        "
                        :answer-error="
                            errorFor(`questions.${index}.answer_html`)
                        "
                        :feedback-errors="feedbackErrors(index)"
                        :focus-target="focusTarget"
                        @update:feedback="
                            (type, value) =>
                                setFeedbackValue(question.feedback, type, value)
                        "
                        @remove="requestRemove(index)"
                    />
                </div>
            </div>

            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="atLimit"
                        @click="addQuestion"
                    >
                        <PlusIcon aria-hidden="true" />
                        Add question
                    </Button>

                    <!-- On long sessions, one control opens or shrinks every
                        card at once. -->
                    <template v-if="collapsible">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="expandAll"
                        >
                            Expand all
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="collapseAll"
                        >
                            Collapse all
                        </Button>
                    </template>
                </div>

                <p class="text-xs text-muted-foreground">
                    {{
                        atLimit
                            ? 'A session can hold up to 50 questions. Remove one to add another.'
                            : `${questionCount} of ${MAX_QUESTIONS} questions`
                    }}
                </p>
            </div>

            <p
                v-if="questionsError"
                role="alert"
                class="text-xs font-medium text-destructive"
            >
                {{ questionsError }}
            </p>
        </form>

        <AlertDialog v-model:open="removalOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Remove this question?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Question {{ removalNumber }} and its feedback will be
                        removed. This cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        @click="confirmRemove"
                    >
                        Remove question
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- What the session gave back, and a way straight to any row that
            still needs a correction or a meaning. -->
        <FinishSessionSummary
            v-if="summaryResult !== null"
            :open="summaryOpen"
            :result="summaryResult"
            :attention="attention"
            @update:open="summaryOpen = $event"
            @review="reviewAttention"
        />

        <!-- Nothing worth finishing: keep going, or throw the session away. -->
        <EmptySessionDialog
            v-model:open="emptyOpen"
            @discard="discardEmptySession"
        />
    </div>
</template>
