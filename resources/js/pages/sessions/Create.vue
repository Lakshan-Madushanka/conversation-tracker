<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { PlusIcon, RefreshCwIcon, TriangleAlertIcon } from '@lucide/vue';
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
import { focusElementById } from '@/lib/dom';
import {
    emptyFeedbackValues,
    feedbackBoxes,
    questionHasContent,
    toQuestionForm,
} from '@/lib/feedback';
import { savePracticeSession } from '@/lib/session-autosave';
import { edit as sessionsEdit } from '@/routes/sessions';
import type {
    FeedbackType,
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

const hasTopic = (): boolean => topic.value.trim().length > 0;
const hasContent = (): boolean => questions.value.some(questionHasContent);

const {
    sessionId,
    status,
    errors,
    conflict,
    lastSavedAt,
    retry,
    loadLatest,
    keepMine,
    flush,
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

// Leaving the editor (for example, tapping a link in the app) should not drop
// unsaved work: send a last best-effort save on the way out.
onBeforeUnmount(() => {
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
</script>

<template>
    <div>
        <PageHeader
            :title="hasSession ? 'Edit session' : 'New session'"
            :description="
                hasSession
                    ? 'Update the topic and questions. Your session stays unfinished until you finish it.'
                    : 'Add a topic, then paste the tutor’s questions and feedback.'
            "
        >
            <template #actions>
                <Badge v-if="hasSession" variant="outline" class="gap-1.5">
                    <span
                        class="size-1.5 rounded-full bg-amber-500"
                        aria-hidden="true"
                    />
                    Unfinished
                </Badge>

                <SaveIndicator :status="status" :last-saved-at="lastSavedAt" />
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
                <QuestionCard
                    v-for="(question, index) in questions"
                    :key="question.uid"
                    :number="index + 1"
                    :uid="question.uid"
                    v-model:question-html="question.question_html"
                    v-model:answer-html="question.answer_html"
                    :feedback="question.feedback"
                    :question-error="
                        errorFor(`questions.${index}.question_html`)
                    "
                    :answer-error="errorFor(`questions.${index}.answer_html`)"
                    :feedback-errors="feedbackErrors(index)"
                    @update:feedback="
                        (type, value) => (question.feedback[type] = value)
                    "
                    @remove="requestRemove(index)"
                />
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="atLimit"
                    @click="addQuestion"
                >
                    <PlusIcon aria-hidden="true" />
                    Add question
                </Button>

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
    </div>
</template>
