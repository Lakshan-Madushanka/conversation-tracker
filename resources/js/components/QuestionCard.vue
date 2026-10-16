<script setup lang="ts">
import { Trash2Icon } from '@lucide/vue';
import FeedbackBox from '@/components/FeedbackBox.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { feedbackBoxes } from '@/lib/feedback';
import type { FeedbackType, FeedbackValues } from '@/types';

const props = defineProps<{
    number: number;
    /** Stable client key, used to build field ids that survive reordering. */
    uid: string;
    questionHtml: string;
    answerHtml: string;
    feedback: FeedbackValues;
    questionError?: string;
    answerError?: string;
    feedbackErrors?: Partial<Record<FeedbackType, string>>;
}>();

const emit = defineEmits<{
    (event: 'update:questionHtml', value: string): void;
    (event: 'update:answerHtml', value: string): void;
    (event: 'update:feedback', type: FeedbackType, value: string): void;
    (event: 'remove'): void;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between gap-2">
                <CardTitle class="text-base"
                    >Question {{ props.number }}</CardTitle
                >

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Remove question ${props.number}`"
                    @click="emit('remove')"
                >
                    <Trash2Icon aria-hidden="true" />
                </Button>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <div class="space-y-1.5">
                <label
                    :for="`question-${props.uid}`"
                    class="text-sm font-medium"
                >
                    Tutor’s question
                </label>
                <RichTextEditor
                    :id="`question-${props.uid}`"
                    :model-value="props.questionHtml"
                    label="Tutor’s question"
                    placeholder="Paste the tutor’s question here…"
                    @update:model-value="emit('update:questionHtml', $event)"
                />
                <p
                    v-if="props.questionError"
                    role="alert"
                    class="text-xs font-medium text-destructive"
                >
                    {{ props.questionError }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label :for="`answer-${props.uid}`" class="text-sm font-medium">
                    My answer
                </label>
                <RichTextEditor
                    :id="`answer-${props.uid}`"
                    :model-value="props.answerHtml"
                    label="My answer"
                    placeholder="Paste your own answer…"
                    @update:model-value="emit('update:answerHtml', $event)"
                />
                <p
                    v-if="props.answerError"
                    role="alert"
                    class="text-xs font-medium text-destructive"
                >
                    {{ props.answerError }}
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <FeedbackBox
                    v-for="box in feedbackBoxes"
                    :key="box.type"
                    :type="box.type"
                    :field-id="`feedback-${props.uid}-${box.type}`"
                    :question-number="props.number"
                    :model-value="props.feedback[box.type]"
                    :error="props.feedbackErrors?.[box.type]"
                    :class="
                        box.type === 'natural_version' ? 'md:col-span-2' : ''
                    "
                    @update:model-value="
                        emit('update:feedback', box.type, $event)
                    "
                />
            </div>
        </CardContent>
    </Card>
</template>
