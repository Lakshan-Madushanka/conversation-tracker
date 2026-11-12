<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, RefreshCwIcon, TriangleAlertIcon } from '@lucide/vue';
import FeedbackBox from '@/components/FeedbackBox.vue';
import NotebookReviewDialog from '@/components/NotebookReviewDialog.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import TopicField from '@/components/TopicField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAutoSave } from '@/composables/useAutoSave';
import AppLayout from '@/layouts/AppLayout.vue';
import { saveNotebook } from '@/lib/notebook-autosave';
import { readSearchTarget, revealSearchTarget } from '@/lib/search-target';
import { index as notebooksIndex } from '@/routes/notebooks';
import type {
    NotebookData,
    NotebookFeedback,
    NotebookSnapshot,
} from '@/types/notebook';
import type { TopicSummary } from '@/types/practice';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    notebook: NotebookData;
    topics: TopicSummary[];
}>();

const name = ref(props.notebook.name);
const topic = ref(props.notebook.topic.name);
const description = ref(props.notebook.description_html);
const feedback = ref<NotebookFeedback>({
    vocabulary: [...props.notebook.feedback.vocabulary],
    phrase: [...props.notebook.feedback.phrase],
    grammar_note: [...props.notebook.feedback.grammar_note],
});

const {
    status,
    errors,
    conflict,
    lastSavedAt,
    retry,
    loadLatest,
    keepMine,
    flush,
    saveNow,
} = useAutoSave<NotebookSnapshot, NotebookData>({
    snapshot: () => ({
        name: name.value,
        topic: topic.value,
        description: description.value,
        feedback: feedback.value,
    }),
    save: saveNotebook,
    hasTopic: () => topic.value.trim().length > 0,
    hasContent: () => true,
    sessionId: props.notebook.id,
    version: props.notebook.version,
    onLoadLatest: (saved) => {
        name.value = saved.name;
        topic.value = saved.topic.name;
        description.value = saved.description_html;
        feedback.value = {
            vocabulary: [...saved.feedback.vocabulary],
            phrase: [...saved.feedback.phrase],
            grammar_note: [...saved.feedback.grammar_note],
        };
    },
});

onBeforeUnmount(() => {
    flush();
});

/** The boxes a collection item can point at, as the board names them. */
const notebookFieldIds = {
    vocabulary: 'nb-vocabulary',
    phrase: 'nb-phrase',
    grammar_note: 'nb-grammar',
} as const;

type NotebookFeedbackType = keyof typeof notebookFieldIds;

/** A calm note when a collection item no longer points at anything. */
const targetNotice = ref('');

/**
 * Take the learner to the entry a Collections item named. The entry is found
 * before anything is scrolled, so a board changed since then opens calmly at
 * the top instead of jumping to the wrong row.
 */
function revealTargetFromAddress(): void {
    const target = readSearchTarget(window.location.href);

    if (
        target === null ||
        target.field !== 'entry' ||
        !isNotebookFeedbackType(target.type)
    ) {
        return;
    }

    const fieldId = notebookFieldIds[target.type];
    const entries = feedback.value[target.type];

    const byId =
        target.entry === null
            ? undefined
            : entries.find((entry) => entry.id === target.entry);

    const entry =
        byId ??
        (target.position === null ? undefined : entries[target.position]);

    if (entry === undefined) {
        targetHasMoved();

        return;
    }

    const id =
        entry.id === ''
            ? fieldId
            : target.type === 'grammar_note'
              ? `${fieldId}-${entry.id}`
              : `${fieldId}-row-${entry.id}`;

    revealSearchTarget(id, (found) => {
        if (!found) {
            targetHasMoved();
        }
    });
}

function isNotebookFeedbackType(
    type: string | null,
): type is NotebookFeedbackType {
    return (
        type === 'vocabulary' || type === 'phrase' || type === 'grammar_note'
    );
}

function targetHasMoved(): void {
    targetNotice.value =
        'The entry you came from has changed since it was saved. You are at the top of the notebook.';

    window.scrollTo({ top: 0 });
}

onMounted(revealTargetFromAddress);

function errorFor(key: string): string | undefined {
    return errors.value[key];
}

/** The review dialog is only opened once the board is confirmed saved. */
const reviewOpen = ref(false);
const reviewPreparing = ref(false);
const reviewNotice = ref('');

/**
 * Practising reads the notebook's saved items, so pending edits are saved
 * first. A save that cannot be confirmed blocks the shortcut rather than
 * starting from content the server has not seen.
 */
async function prepareReview(): Promise<void> {
    if (reviewPreparing.value) {
        return;
    }

    reviewPreparing.value = true;
    reviewNotice.value = '';

    try {
        const saved = await saveNow();

        if (!saved) {
            reviewNotice.value =
                status.value === 'needs_topic'
                    ? 'Give this notebook a topic before practising.'
                    : 'Your latest changes are not saved yet. Retry saving before practising.';

            return;
        }

        reviewOpen.value = true;
    } finally {
        reviewPreparing.value = false;
    }
}
</script>

<template>
    <Head :title="name || 'Notebook'" />

    <div class="mx-auto w-full max-w-3xl px-4 py-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="notebooksIndex.url()">
                    <ArrowLeftIcon aria-hidden="true" />
                    Notebooks
                </Link>
            </Button>
            <SaveIndicator :status="status" :last-saved-at="lastSavedAt" />
        </div>

        <div
            v-if="targetNotice"
            role="status"
            class="mb-4 flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border bg-muted/40 px-3 py-3 text-sm"
        >
            <p class="text-muted-foreground">{{ targetNotice }}</p>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="targetNotice = ''"
            >
                Dismiss
            </Button>
        </div>

        <!-- Conflict: another tab saved first. -->
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
                This notebook was changed somewhere else.
            </p>
            <p class="mt-1 pl-6 text-muted-foreground">
                Load the latest version to see that work, or keep what is on
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

        <!-- Retry / failed -->
        <div
            v-else-if="status === 'retrying' || status === 'failed'"
            role="alert"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm"
        >
            <p class="flex items-start gap-2">
                <TriangleAlertIcon class="size-4 shrink-0" aria-hidden="true" />
                Saving did not go through. Your text is still here.
            </p>
            <Button type="button" size="sm" variant="outline" @click="retry">
                <RefreshCwIcon aria-hidden="true" />
                Retry
            </Button>
        </div>

        <!-- Deleted -->
        <div
            v-else-if="status === 'deleted'"
            role="alert"
            class="mb-4 rounded-lg border border-border bg-muted/40 px-3 py-3 text-sm"
        >
            <p class="font-medium">This notebook was deleted.</p>
            <p class="mt-1 text-muted-foreground">
                Your text is still on this screen. Return to the listing to
                continue with another notebook.
            </p>
            <div class="mt-3">
                <Button type="button" size="sm" as-child>
                    <Link :href="notebooksIndex.url()">Go to Notebooks</Link>
                </Button>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border bg-muted/30 px-3 py-3">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium">
                            Practise this notebook
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Saves a new review from these items and starts it.
                        </p>
                    </div>
                    <Button
                        type="button"
                        class="min-h-11 md:min-h-9"
                        :disabled="
                            reviewPreparing ||
                            status === 'deleted' ||
                            status === 'archived'
                        "
                        @click="prepareReview"
                    >
                        Create review and practise
                    </Button>
                </div>
                <p
                    v-if="reviewNotice"
                    role="alert"
                    class="mt-2 text-sm text-destructive"
                >
                    {{ reviewNotice }}
                </p>
            </div>

            <div>
                <label for="nb-name" class="mb-1 block text-sm font-medium">
                    Name
                </label>
                <Input
                    id="nb-name"
                    v-model="name"
                    placeholder="Notebook name"
                    maxlength="120"
                    :aria-invalid="!!errorFor('name')"
                />
                <p
                    v-if="errorFor('name')"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ errorFor('name') }}
                </p>
            </div>

            <TopicField
                v-model="topic"
                :suggestions="topics"
                :error="errorFor('topic')"
                :highlight="status === 'needs_topic'"
            />

            <div>
                <label
                    for="nb-description"
                    class="mb-1 block text-sm font-medium"
                >
                    Description
                </label>
                <RichTextEditor
                    id="nb-description"
                    v-model="description"
                    label="Notebook description"
                    placeholder="What this notebook is about (optional)"
                />
            </div>

            <FeedbackBox
                type="vocabulary"
                v-model="feedback.vocabulary"
                field-id="nb-vocabulary"
                :question-number="1"
                :error="errorFor('feedback.vocabulary')"
            />

            <FeedbackBox
                type="phrase"
                v-model="feedback.phrase"
                field-id="nb-phrase"
                :question-number="1"
                :error="errorFor('feedback.phrase')"
            />

            <FeedbackBox
                type="grammar_note"
                v-model="feedback.grammar_note"
                field-id="nb-grammar"
                :question-number="1"
                :error="errorFor('feedback.grammar_note')"
            />
        </div>

        <NotebookReviewDialog
            v-model:open="reviewOpen"
            :notebook-id="props.notebook.id"
            :notebook-name="props.notebook.name"
        />
    </div>
</template>
