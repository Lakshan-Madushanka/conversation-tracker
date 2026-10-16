<script setup lang="ts">
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    number: number;
    questionHtml: string;
    answerHtml: string;
    questionError?: string;
    answerError?: string;
}>();

const emit = defineEmits<{
    (event: 'update:questionHtml', value: string): void;
    (event: 'update:answerHtml', value: string): void;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Question {{ props.number }}</CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <div class="space-y-1.5">
                <label
                    :for="`question-${props.number}`"
                    class="text-sm font-medium"
                >
                    Tutor’s question
                </label>
                <RichTextEditor
                    :id="`question-${props.number}`"
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
                <label
                    :for="`answer-${props.number}`"
                    class="text-sm font-medium"
                >
                    My answer
                </label>
                <RichTextEditor
                    :id="`answer-${props.number}`"
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
        </CardContent>
    </Card>
</template>
