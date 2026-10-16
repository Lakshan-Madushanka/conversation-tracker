<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    LoaderCircleIcon,
    PlusIcon,
    SaveIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/PageHeader.vue';
import QuestionCard from '@/components/QuestionCard.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { focusElementById } from '@/lib/dom';
import {
    emptyFeedbackValues,
    feedbackBoxes,
    questionHasContent,
    toQuestionForm,
} from '@/lib/feedback';
import {
    store as sessionsStore,
    update as sessionsUpdate,
} from '@/routes/sessions';
import type {
    FeedbackType,
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

const form = useForm({
    topic: props.session?.topic.name ?? '',
    questions: initialQuestions(),
});

function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
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

const questionCount = computed(() => form.questions.length);
const atLimit = computed(() => questionCount.value >= MAX_QUESTIONS);
const questionsError = computed(() => errorFor('questions'));

const generalError = ref<string | null>(null);

const removalOpen = ref(false);
const pendingRemovalIndex = ref<number | null>(null);

const removalNumber = computed(() => (pendingRemovalIndex.value ?? 0) + 1);

function addQuestion(): void {
    if (atLimit.value) {
        return;
    }

    const question = emptyQuestion();

    form.questions.push(question);
    focusElementById(`question-${question.uid}`);
}

function removeQuestion(index: number): void {
    // A session always keeps at least one question card.
    if (form.questions.length <= 1) {
        form.questions.splice(0, form.questions.length, emptyQuestion());

        return;
    }

    form.questions.splice(index, 1);
}

function requestRemove(index: number): void {
    const question = form.questions[index];

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

/** A saved session with no edits made since it loaded is up to date. */
const isSaved = computed(() => Boolean(props.session) && !form.isDirty);

const saveState = computed<'saved' | 'saving' | 'unsaved'>(() => {
    if (form.processing) {
        return 'saving';
    }

    return isSaved.value ? 'saved' : 'unsaved';
});

const statusText = computed(() => {
    if (saveState.value === 'saving') {
        return 'Saving…';
    }

    if (saveState.value === 'saved') {
        return 'Saved';
    }

    return props.session ? 'Unsaved changes' : 'Not saved yet';
});

const statusDotClass = computed(() => {
    if (saveState.value === 'saving') {
        return 'animate-pulse bg-amber-500';
    }

    return saveState.value === 'saved' ? 'bg-emerald-500' : 'bg-amber-500';
});

function submit(): void {
    const options = {
        preserveScroll: true,
        onStart: () => {
            generalError.value = null;
        },
        onSuccess: () => {
            generalError.value = null;
            toast.success('Saved');
        },
        onError: (errors: Record<string, string>) => {
            // Field-level problems appear next to the field itself.
            if (Object.keys(errors).length === 0) {
                generalError.value =
                    'Could not save. Please check your connection and try again.';
                toast.error('Could not save. Please try again.');
            }
        },
    };

    if (props.session) {
        form.put(sessionsUpdate.url(props.session.id), options);

        return;
    }

    form.post(sessionsStore.url(), options);
}
</script>

<template>
    <div>
        <PageHeader
            :title="props.session ? 'Edit session' : 'New session'"
            :description="
                props.session
                    ? 'Update the topic and questions. Your session stays unfinished until you finish it.'
                    : 'Add a topic, then paste the tutor’s questions and feedback.'
            "
        >
            <template #actions>
                <Badge v-if="props.session" variant="outline" class="gap-1.5">
                    <span
                        class="size-1.5 rounded-full bg-amber-500"
                        aria-hidden="true"
                    />
                    Unfinished
                </Badge>

                <span
                    role="status"
                    aria-live="polite"
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :class="statusDotClass"
                        aria-hidden="true"
                    />
                    {{ statusText }}
                </span>

                <Button
                    type="submit"
                    form="session-form"
                    size="lg"
                    :disabled="form.processing"
                >
                    <LoaderCircleIcon
                        v-if="form.processing"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    <SaveIcon v-else aria-hidden="true" />
                    {{ form.processing ? 'Saving…' : 'Save session' }}
                </Button>
            </template>
        </PageHeader>

        <form
            id="session-form"
            class="grid gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <TopicField
                v-model="form.topic"
                :suggestions="props.topics"
                :error="form.errors.topic"
            />

            <p
                v-if="generalError"
                role="alert"
                class="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                <TriangleAlertIcon
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ generalError }}
            </p>

            <div class="grid gap-4">
                <QuestionCard
                    v-for="(question, index) in form.questions"
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
