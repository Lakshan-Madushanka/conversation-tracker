<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, RefreshCwIcon, TriangleAlertIcon } from '@lucide/vue';
import FeedbackBox from '@/components/FeedbackBox.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import TopicField from '@/components/TopicField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAutoSave } from '@/composables/useAutoSave';
import AppLayout from '@/layouts/AppLayout.vue';
import { saveNotebook } from '@/lib/notebook-autosave';
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

function errorFor(key: string): string | undefined {
    return errors.value[key];
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
    </div>
</template>
