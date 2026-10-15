<script setup lang="ts">
import { computed } from 'vue';
import { cn } from 'cn';

export type FeedbackTone =
    | 'mistakes'
    | 'vocabulary'
    | 'phrases'
    | 'grammar'
    | 'natural'
    | 'neutral';

const props = withDefaults(
    defineProps<{
        tone?: FeedbackTone;
        count?: number;
    }>(),
    {
        tone: 'neutral',
    },
);

const toneClasses: Record<FeedbackTone, string> = {
    mistakes:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
    vocabulary:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
    phrases:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/60 dark:text-violet-300',
    grammar:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300',
    natural:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300',
    neutral: 'border-border bg-muted text-muted-foreground',
};

const chipClasses = computed(() =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium',
        toneClasses[props.tone],
    ),
);
</script>

<template>
    <span :class="chipClasses">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        <slot />
        <span v-if="count !== undefined" class="tabular-nums opacity-70">{{
            count
        }}</span>
    </span>
</template>
