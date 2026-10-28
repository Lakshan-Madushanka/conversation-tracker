<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    EyeIcon,
    InfoIcon,
    ListOrderedIcon,
    PlusIcon,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import BackToLibrary from '@/components/BackToLibrary.vue';
import EmptySessionDialog from '@/components/EmptySessionDialog.vue';
import FeedbackBox from '@/components/FeedbackBox.vue';
import FinishSessionSummary from '@/components/FinishSessionSummary.vue';
import GuidedReview from '@/components/GuidedReview.vue';
import GuidedStep from '@/components/GuidedStep.vue';
import PageHeader from '@/components/PageHeader.vue';
import QuestionCard from '@/components/QuestionCard.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import SaveNotices from '@/components/SaveNotices.vue';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAutoSave } from '@/composables/useAutoSave';
import AppLayout from '@/layouts/AppLayout.vue';
import FocusLayout from '@/layouts/FocusLayout.vue';
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
import {
    editorUrl,
    feedbackTypeFor,
    GUIDED_STEPS,
    GUIDED_TOPIC,
    guidedCountLine,
    guidedEntryCount,
    guidedReviewHeading,
    guidedUrl,
    readGuidedLocation,
} from '@/lib/guided';
import type { GuidedView } from '@/lib/guided';
import { isRichTextBlank, richTextPreview } from '@/lib/rich-text';
import {
    editorEntryId,
    readSearchTarget,
    revealSearchTarget,
    type SearchTarget,
} from '@/lib/search-target';
import { savePracticeSession } from '@/lib/session-autosave';
import { restoreSession } from '@/lib/session-archive';
import { discardSession, finishSession } from '@/lib/session-finish';
import { home } from '@/routes';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type {
    FeedbackType,
    FeedbackValue,
    FinishSummary,
    PracticeQuestionData,
    PracticeQuestionForm,
    PracticeSessionData,
    TopicSummary,
} from '@/types';

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

/** A calm note when a search result no longer points at anything. */
const targetNotice = ref('');

/**
 * Take the learner to the item a search result named. The item is found before
 * anything is scrolled, so a session that has changed since the search opens
 * calmly at the top instead of jumping to the wrong card.
 */
function revealTargetFromAddress(): void {
    const target = readSearchTarget(window.location.href);

    if (target === null || target.question === null) {
        return;
    }

    const index = questionIndexForPosition(target.question);
    const question = questions.value[index];

    if (question === undefined) {
        targetHasMoved();

        return;
    }

    // A long session collapses most cards; the target's card must be open
    // before its fields exist to be found.
    expandCard(question.uid);

    const id = targetElementId(question, target);

    if (id === null) {
        targetHasMoved();

        return;
    }

    revealSearchTarget(id, (found) => {
        if (!found) {
            targetHasMoved();
        }
    });
}

/** The editor card for a saved question, by its stored position. */
function questionIndexForPosition(position: number): number {
    const saved = props.session?.questions ?? [];
    const index = saved.findIndex((question) => question.position === position);

    return index >= 0 ? index : position - 1;
}

/** The field a target names inside one question, or null when it is gone. */
function targetElementId(
    question: PracticeQuestionForm,
    target: SearchTarget,
): string | null {
    if (target.field === 'question') {
        return `question-${question.uid}`;
    }

    if (target.field === 'answer') {
        return `answer-${question.uid}`;
    }

    if (target.field !== 'entry' || target.type === null) {
        return null;
    }

    // The natural version is one editor, so its box is the item.
    if (target.type === 'natural_version') {
        return `feedback-${question.uid}-natural_version`;
    }

    const entries = question.feedback[target.type];

    if (!Array.isArray(entries)) {
        return null;
    }

    const byId =
        target.entry === null
            ? undefined
            : entries.find((entry) => entry.id === target.entry);

    const entry =
        byId ??
        (target.position === null ? undefined : entries[target.position]);

    return entry === undefined
        ? null
        : editorEntryId(question.uid, target.type, entry.id);
}

function targetHasMoved(): void {
    targetNotice.value =
        'The item you searched for has changed since you found it. You are at the top of the session.';

    window.scrollTo({ top: 0 });
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
    return richTextPreview(question.question_html);
}

const hasTopic = (): boolean => topic.value.trim().length > 0;
const hasContent = (): boolean => questions.value.some(questionHasContent);

const {
    sessionId,
    version,
    status,
    errors,
    conflict,
    archivedMessage,
    lastSavedAt,
    retry,
    loadLatest,
    keepMine,
    resumeAfterRestore,
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
        // focus and the caret stay where they are. Guided mode keeps its place
        // in the address across the change of path.
        const base = sessionsEdit.url(session.id);
        const href = guided.value
            ? guidedUrl(base, {
                  question: guidedQuestion.value + 1,
                  view: guidedView.value,
              })
            : base;

        writeAddress(href, 'replace');
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

/**
 * The session was archived while this editor was open, so saving was refused.
 * Restore it first, then save the text that stayed on screen.
 */
async function restoreArchivedSession(): Promise<void> {
    const id = sessionId.value;

    if (id === null) {
        return;
    }

    const restored = await restoreSession(id);

    if (!restored) {
        toast.error(
            'We could not restore this session. Your text is still here.',
        );

        return;
    }

    resumeAfterRestore();
    toast.success('Session restored. Saving your text again.');
}

/**
 * Send focus to the row that still needs work, once the summary is gone. In
 * guided mode it becomes the step holding that row instead.
 */
function reviewAttention(): void {
    const first = attention.value.first;

    if (guided.value && first !== null) {
        guidedFixId.value = { view: first.boxType, entryId: first.entryId };
        moveTo({ question: guidedQuestion.value, view: first.boxType });

        return;
    }

    focusTarget.value = first;
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

/* ----------------------------------------------------------------------- */
/* Guided "paste in order": the same fields, one fixed step at a time.     */
/* ----------------------------------------------------------------------- */

const guided = ref(false);
const guidedQuestion = ref(0);
const guidedView = ref<GuidedView>('question');
/** A row the review sent us to fix, until the step moves somewhere else. */
const guidedFixId = ref<{ view: GuidedView; entryId: string } | null>(null);

/** What the review screen says above its list of sections. */
const REVIEW_HINT =
    'Look over the question, then add another or finish the session.';

/** The question the guided step is working through. */
const guidedQuestionForm = computed(
    () =>
        questions.value[
            Math.min(guidedQuestion.value, questions.value.length - 1)
        ]!,
);

/** The feedback box this step shows, or null for the topic and the review. */
const guidedBoxType = computed(() => feedbackTypeFor(guidedView.value));

/** The row to focus once a Fix has brought its step up. */
const guidedFocusEntryId = computed(() =>
    guidedFixId.value !== null && guidedFixId.value.view === guidedView.value
        ? guidedFixId.value.entryId
        : null,
);

/** Whether this step has something in it, so the button says Next or Skip. */
const guidedFilled = computed(() => {
    const view = guidedView.value;

    if (view === 'topic') {
        return hasTopic();
    }

    if (view === 'review') {
        return false;
    }

    const question = guidedQuestionForm.value;

    if (view === 'question') {
        return !isRichTextBlank(question.question_html);
    }

    if (view === 'answer') {
        return !isRichTextBlank(question.answer_html);
    }

    if (view === 'natural_version') {
        return !isRichTextBlank(question.feedback.natural_version);
    }

    return (guidedEntryCount(question, view) ?? 0) > 0;
});

/** How far through a question the bar is: the topic starts it, the review ends it. */
const guidedProgress = computed(() => {
    if (guidedView.value === 'topic') {
        return 0;
    }

    if (guidedView.value === 'review') {
        return 100;
    }

    const index = GUIDED_STEPS.findIndex(
        (step) => step.id === guidedView.value,
    );

    return Math.round(((index + 1) / (GUIDED_STEPS.length + 1)) * 100);
});

const guidedProgressLabel = computed(
    () => `Question ${guidedQuestion.value + 1} progress`,
);

/** Where the exit control would go if it were a link; the slot replaces it. */
const guidedExitHref = computed(() =>
    typeof window === 'undefined' ? '/' : editorUrl(window.location.pathname),
);

/** What the guided frame shows above the field: chip, counter, heading, hint. */
const guidedMeta = computed(() => {
    const view = guidedView.value;

    if (view === 'topic') {
        return {
            label: GUIDED_TOPIC.label,
            stepNumber: null,
            heading: GUIDED_TOPIC.heading,
            hint: GUIDED_TOPIC.hint,
            detail: null,
            canGoBack: false,
        };
    }

    if (view === 'review') {
        return {
            label: 'Review',
            stepNumber: null,
            heading: guidedReviewHeading(guidedQuestion.value + 1),
            hint: REVIEW_HINT,
            detail: null,
            canGoBack: backTarget() !== null,
        };
    }

    const index = GUIDED_STEPS.findIndex((step) => step.id === view);
    const step = GUIDED_STEPS[index]!;

    return {
        label: step.label,
        stepNumber: index + 1,
        heading: step.heading,
        hint: step.hint,
        detail: guidedCountLine(guidedQuestionForm.value, step.id),
        canGoBack: backTarget() !== null,
    };
});

/**
 * Writes the address bar and the history entry it belongs to, keeping the
 * entry's copy of the page in step so Back and Forward land on matching text.
 */
function writeAddress(href: string, mode: 'push' | 'replace'): void {
    if (typeof window === 'undefined') {
        return;
    }

    const state = (window.history.state ?? {}) as {
        page?: Record<string, unknown>;
    };
    const next = { ...state, page: { ...state.page, url: href } };

    if (mode === 'push') {
        window.history.pushState(next, '', href);
    } else {
        window.history.replaceState(next, '', href);
    }
}

/** Point the address at the guided view (or the plain editor) we are on. */
function setAddress(mode: 'push' | 'replace'): void {
    if (typeof window === 'undefined') {
        return;
    }

    const href = guided.value
        ? guidedUrl(window.location.pathname, {
              question: guidedQuestion.value + 1,
              view: guidedView.value,
          })
        : editorUrl(window.location.pathname);

    writeAddress(href, mode);
}

/** Where Back leads: one step behind, or the review before this question. */
function backTarget(): { question: number; view: GuidedView } | null {
    const view = guidedView.value;

    if (view === 'topic') {
        return null;
    }

    if (view === 'review') {
        return {
            question: guidedQuestion.value,
            view: GUIDED_STEPS[GUIDED_STEPS.length - 1]!.id,
        };
    }

    const index = GUIDED_STEPS.findIndex((step) => step.id === view);

    if (index > 0) {
        return {
            question: guidedQuestion.value,
            view: GUIDED_STEPS[index - 1]!.id,
        };
    }

    if (guidedQuestion.value > 0) {
        return { question: guidedQuestion.value - 1, view: 'review' };
    }

    return hasTopic() ? null : { question: 0, view: 'topic' };
}

/** Where Next leads: the step after this one, or the question's review. */
function nextTarget(): GuidedView | null {
    const view = guidedView.value;

    if (view === 'topic') {
        return 'question';
    }

    if (view === 'review') {
        return null;
    }

    const index = GUIDED_STEPS.findIndex((step) => step.id === view);

    return index + 1 < GUIDED_STEPS.length
        ? GUIDED_STEPS[index + 1]!.id
        : 'review';
}

/** Show one step: the address follows, and focus lands where work happens. */
function moveTo(target: { question: number; view: GuidedView } | null): void {
    if (target === null) {
        return;
    }

    guidedQuestion.value = target.question;
    guidedView.value = target.view;
    setAddress('replace');
    focusGuidedStep();
}

function guidedBack(): void {
    moveTo(backTarget());
}

function guidedNext(): void {
    const view = nextTarget();

    if (view === null) {
        return;
    }

    moveTo({ question: guidedQuestion.value, view });
}

/** Go back to one section, from the review's Change buttons. */
function reviewChange(view: GuidedView): void {
    moveTo({ question: guidedQuestion.value, view });
}

/** Land on the row that is still waiting, from the review's Fix buttons. */
function reviewFix(view: GuidedView, entryId: string): void {
    guidedFixId.value = { view, entryId };
    moveTo({ question: guidedQuestion.value, view });
}

/** Another question, opened straight on its first step. */
function guidedAdd(): void {
    if (atLimit.value) {
        return;
    }

    const question = emptyQuestion();

    questions.value.push(question);
    opened.value.add(question.uid);
    collapsed.value.delete(question.uid);

    moveTo({
        question: questions.value.length - 1,
        view: 'question',
    });
}

/** Take over the address bar with the guided view of this session. */
function enterGuided(): void {
    guidedQuestion.value = 0;
    guidedView.value = hasTopic() ? 'question' : 'topic';
    guided.value = true;
    setAddress('push');
    focusGuidedStep();
}

/** Leave the guided view for the plain editor, focused on this question. */
function exitGuided(): void {
    const uid = guidedQuestionForm.value.uid;

    guided.value = false;
    guidedFixId.value = null;
    setAddress('replace');
    expandCard(uid);
    focusElementById(`question-${uid}`);
}

/** Put the cursor where the learner will work in this step. */
function focusGuidedStep(): void {
    const view = guidedView.value;

    if (view === 'topic') {
        focusElementById('topic');

        return;
    }

    if (view === 'review') {
        focusElementById('guided-heading');

        return;
    }

    if (view === 'question') {
        focusElementById(`question-${guidedQuestionForm.value.uid}`);

        return;
    }

    if (view === 'answer') {
        focusElementById(`answer-${guidedQuestionForm.value.uid}`);
    }

    // A feedback box hands the cursor to its own first place to paste.
}

/** Hand a pasted value to whichever box the step is showing. */
function updateGuidedBox(value: FeedbackValue): void {
    const type = guidedBoxType.value;

    if (type === null) {
        return;
    }

    setFeedbackValue(guidedQuestionForm.value.feedback, type, value);
}

// A step change is written to the address bar; leaving a Fix's row behind
// lets the row give up the cursor it was holding.
watch(guidedView, (view) => {
    if (guidedFixId.value !== null && guidedFixId.value.view !== view) {
        guidedFixId.value = null;
    }
});

// The server can drop or return questions under us; the step always stays
// on a question that exists.
watch(
    () => questions.value.length,
    () => {
        const last = Math.max(questions.value.length - 1, 0);

        if (guidedQuestion.value > last) {
            guidedQuestion.value = last;

            if (guided.value) {
                setAddress('replace');
            }
        }
    },
);

let stopGuidedNavigation = (): void => {};

onMounted(() => {
    // Re-ask the server after a Back or Forward landing, when the history
    // entry can hold a stale copy of this session.
    stopGuidedNavigation = router.on('navigate', (event) => {
        if (event.detail.type !== 'history' || event.detail.cached === true) {
            return;
        }

        if (event.detail.page.component !== 'sessions/Create') {
            return;
        }

        if (conflict.value !== null) {
            return;
        }

        router.visit(window.location.href, {
            replace: true,
            preserveState: false,
            preserveScroll: true,
        });
    });

    const location = readGuidedLocation(window.location.href);

    if (location === null) {
        revealTargetFromAddress();

        return;
    }

    guided.value = true;
    guidedQuestion.value = Math.min(
        Math.max(location.question - 1, 0),
        Math.max(questions.value.length - 1, 0),
    );

    if (!hasTopic()) {
        // Work starts at the topic, wherever the address points.
        guidedView.value = 'topic';
    } else if (location.view === 'topic') {
        guidedView.value = 'question';
    } else {
        guidedView.value = location.view;
    }

    setAddress('replace');
    focusGuidedStep();
});

onBeforeUnmount(() => {
    stopGuidedNavigation();
});
</script>

<template>
    <div>
        <Head :title="hasSession ? 'Edit session' : 'New session'" />

        <!-- Guided mode: one step of one question at a time, with nothing
            on screen but the field being filled in. -->
        <FocusLayout
            v-if="guided"
            :progress="guidedProgress"
            :exit-href="guidedExitHref"
            :progress-label="guidedProgressLabel"
        >
            <template #exit>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="exitGuided"
                >
                    <ArrowLeftIcon aria-hidden="true" />
                    Exit
                </Button>
            </template>

            <SaveNotices
                :conflict="conflict"
                :status="status"
                :failure-message="failureMessage"
                :archived-message="archivedMessage"
                @load-latest="loadLatest"
                @keep-mine="keepMine"
                @retry="retry"
                @restore="restoreArchivedSession"
            />

            <GuidedStep
                :label="guidedMeta.label"
                :step-number="guidedMeta.stepNumber"
                :heading="guidedMeta.heading"
                :hint="guidedMeta.hint"
                :detail="guidedMeta.detail"
                :status="status"
                :last-saved-at="lastSavedAt"
                :can-go-back="guidedMeta.canGoBack"
                :filled="guidedFilled"
                :next-visible="guidedView !== 'review'"
                heading-id="guided-heading"
                @back="guidedBack"
                @next="guidedNext"
            >
                <TopicField
                    v-if="guidedView === 'topic'"
                    v-model="topic"
                    :suggestions="props.topics"
                    :error="errors.topic"
                    :highlight="needsTopicHighlight"
                />

                <GuidedReview
                    v-else-if="guidedView === 'review'"
                    :question="guidedQuestionForm"
                    :add-disabled="atLimit"
                    :finish-disabled="finishing"
                    @change="reviewChange"
                    @fix="reviewFix"
                    @add="guidedAdd"
                    @finish="finishNow"
                />

                <template v-else-if="guidedView === 'question'">
                    <RichTextEditor
                        :id="`question-${guidedQuestionForm.uid}`"
                        :model-value="guidedQuestionForm.question_html"
                        label="Tutor’s question"
                        placeholder="Paste the tutor’s question here…"
                        @update:model-value="
                            guidedQuestionForm.question_html = $event
                        "
                    />
                    <p
                        v-if="
                            errorFor(
                                `questions.${guidedQuestion}.question_html`,
                            )
                        "
                        role="alert"
                        class="text-xs font-medium text-destructive"
                    >
                        {{
                            errorFor(
                                `questions.${guidedQuestion}.question_html`,
                            )
                        }}
                    </p>
                </template>

                <template v-else-if="guidedView === 'answer'">
                    <RichTextEditor
                        :id="`answer-${guidedQuestionForm.uid}`"
                        :model-value="guidedQuestionForm.answer_html"
                        label="My answer"
                        placeholder="Paste your own answer…"
                        @update:model-value="
                            guidedQuestionForm.answer_html = $event
                        "
                    />
                    <p
                        v-if="
                            errorFor(`questions.${guidedQuestion}.answer_html`)
                        "
                        role="alert"
                        class="text-xs font-medium text-destructive"
                    >
                        {{
                            errorFor(`questions.${guidedQuestion}.answer_html`)
                        }}
                    </p>
                </template>

                <FeedbackBox
                    v-else-if="guidedBoxType"
                    :type="guidedBoxType"
                    :field-id="`feedback-${guidedQuestionForm.uid}-${guidedBoxType}`"
                    :question-number="guidedQuestion + 1"
                    :model-value="guidedQuestionForm.feedback[guidedBoxType]"
                    :error="feedbackErrors(guidedQuestion)[guidedBoxType]"
                    :focus-entry-id="guidedFocusEntryId"
                    guided
                    @update:model-value="updateGuidedBox"
                />
            </GuidedStep>
        </FocusLayout>

        <AppLayout v-else>
            <BackToLibrary />

            <!-- The plain editor: every question on one page. -->
            <PageHeader
                :title="hasSession ? 'Edit session' : 'New session'"
                :description="description"
            >
                <template #actions>
                    <Badge v-if="hasSession" variant="outline" class="gap-1.5">
                        <span
                            class="size-1.5 rounded-full"
                            :class="
                                isFinished ? 'bg-emerald-500' : 'bg-amber-500'
                            "
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

                    <SaveIndicator
                        :status="status"
                        :last-saved-at="lastSavedAt"
                    />

                    <!-- One question at a time, in the order a tutor's chat
                        arrives, for anyone who would rather not juggle cards. -->
                    <Button
                        type="button"
                        variant="outline"
                        @click="enterGuided"
                    >
                        <ListOrderedIcon aria-hidden="true" />
                        Paste in order
                    </Button>

                    <Button
                        type="button"
                        :disabled="finishing"
                        @click="finishNow"
                    >
                        <CheckIcon aria-hidden="true" />
                        Finish session
                    </Button>
                </template>
            </PageHeader>

            <SaveNotices
                :conflict="conflict"
                :status="status"
                :failure-message="failureMessage"
                :archived-message="archivedMessage"
                @load-latest="loadLatest"
                @keep-mine="keepMine"
                @retry="retry"
                @restore="restoreArchivedSession"
            />

            <Alert v-if="targetNotice !== ''" class="mb-4" role="status">
                <InfoIcon aria-hidden="true" />
                <AlertTitle>That item has changed</AlertTitle>
                <AlertDescription>{{ targetNotice }}</AlertDescription>
            </Alert>

            <form class="grid grid-cols-1 gap-4" novalidate @submit.prevent>
                <TopicField
                    v-model="topic"
                    :suggestions="props.topics"
                    :error="errors.topic"
                    :highlight="needsTopicHighlight"
                />

                <div class="grid grid-cols-1 gap-4">
                    <div
                        v-for="(question, index) in questions"
                        :key="question.uid"
                    >
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
                                    v-model:question-html="
                                        question.question_html
                                    "
                                    v-model:answer-html="question.answer_html"
                                    :feedback="question.feedback"
                                    :question-error="
                                        errorFor(
                                            `questions.${index}.question_html`,
                                        )
                                    "
                                    :answer-error="
                                        errorFor(
                                            `questions.${index}.answer_html`,
                                        )
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
                                    setFeedbackValue(
                                        question.feedback,
                                        type,
                                        value,
                                    )
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
        </AppLayout>

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
