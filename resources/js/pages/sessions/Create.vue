<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircleIcon, SaveIcon } from '@lucide/vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/PageHeader.vue';
import QuestionCard from '@/components/QuestionCard.vue';
import TopicField from '@/components/TopicField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    store as sessionsStore,
    update as sessionsUpdate,
} from '@/routes/sessions';
import type { PracticeSessionData, TopicSummary } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    topics: TopicSummary[];
    session?: PracticeSessionData | null;
}>();

const firstQuestion = props.session?.questions[0] ?? null;

const form = useForm({
    topic: props.session?.topic.name ?? '',
    question_html: firstQuestion?.question_html ?? '',
    answer_html: firstQuestion?.answer_html ?? '',
});

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
        onSuccess: () => {
            toast.success('Saved');
        },
        onError: (errors: Record<string, string>) => {
            // Field-level problems appear next to the field itself.
            if (Object.keys(errors).length === 0) {
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
                    ? 'Update the topic, question or answer. Your session stays unfinished until you finish it.'
                    : 'Add a topic and one question so you can keep practising.'
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

            <QuestionCard
                :number="firstQuestion?.position ?? 1"
                v-model:question-html="form.question_html"
                v-model:answer-html="form.answer_html"
                :question-error="form.errors.question_html"
                :answer-error="form.errors.answer_html"
            />
        </form>
    </div>
</template>
