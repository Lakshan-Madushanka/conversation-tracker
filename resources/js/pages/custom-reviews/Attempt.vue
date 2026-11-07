<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckCheckIcon, RotateCcwIcon } from '@lucide/vue';
import ReviewExerciseShell from '@/components/review/ReviewExerciseShell.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    CompletedSection,
    CurrentReviewItem,
    CustomReviewAttemptData,
    CustomReviewAttemptUrls,
    ReviewCounts,
    ReviewSection,
} from '@/types';

const props = defineProps<{
    /** The attempt has nothing to show, or no longer exists. */
    notFound: boolean;
    finished: boolean;
    /**
     * What the attempt cannot show right now, each kind said once: the ones
     * archived since it started, and the ones it started without.
     */
    notices: string[];
    sections: ReviewSection[];
    current: CurrentReviewItem | null;
    completedSection: CompletedSection | null;
    position: { current: number; total: number };
    progress: number;
    counts: ReviewCounts;
    reviewedCount: number;
    undo: { kind: string; item_identity: string } | null;
    /** "[Review name] · [n]th attempt", above the progress. */
    title: string;
    attempt: CustomReviewAttemptData;
    urls: CustomReviewAttemptUrls;
}>();

function startAnother(): void {
    if (props.urls.start_another !== null) {
        router.post(props.urls.start_another);
    }
}
</script>

<template>
    <Head :title="props.title" />

    <ReviewExerciseShell
        :not-found="props.notFound"
        :finished="props.finished"
        :notices="props.notices"
        :sections="props.sections"
        :current="props.current"
        :completed-section="props.completedSection"
        :position="props.position"
        :progress="props.progress"
        :counts="props.counts"
        :reviewed-count="props.reviewedCount"
        :undo="props.undo"
        :title="props.title"
        progress-label="Attempt progress"
        :exit-href="props.urls.exit"
        :back-href="props.urls.exit"
        back-label="Back to review"
        subject="attempt"
        not-found-title="We couldn’t find that attempt"
        not-found-description="It may have been removed, or the address may have changed. Your review and its other attempts are still there."
        finish-label="Finish attempt"
        :rate-url="props.urls.rate"
        :undo-url="props.urls.undo"
        :finish-early-url="props.urls.finish_early"
    >
        <template #finish-confirm="{ unratedCount }">
            {{ unratedCount }}
            {{ unratedCount === 1 ? 'item is' : 'items are' }}
            still to rate. They stay unrated in this attempt, and you can try
            them in another one whenever you like.
        </template>

        <template #finished="{ undoOffered, undoProcessing, undo }">
            <Card class="text-center">
                <CardHeader>
                    <span
                        class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                        aria-hidden="true"
                    >
                        <CheckCheckIcon class="size-6" />
                    </span>
                    <CardTitle class="text-xl">
                        <h1>
                            {{
                                props.attempt.status === 'completed'
                                    ? 'Nicely done'
                                    : 'A good place to stop'
                            }}
                        </h1>
                    </CardTitle>
                    <CardDescription>
                        {{ props.attempt.number_label }}
                        <span aria-hidden="true">·</span>
                        {{ props.attempt.status_label }}
                        <span aria-hidden="true">·</span>
                        you rated {{ props.attempt.rated }} of
                        {{ props.attempt.total }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <div class="rounded-lg border p-3">
                            <p class="text-2xl font-semibold tabular-nums">
                                {{ props.counts.easy }}
                            </p>
                            <p class="text-muted-foreground">Easy</p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-2xl font-semibold tabular-nums">
                                {{ props.counts.okay }}
                            </p>
                            <p class="text-muted-foreground">Okay</p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-2xl font-semibold tabular-nums">
                                {{ props.counts.hard }}
                            </p>
                            <p class="text-muted-foreground">Hard</p>
                        </div>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        {{
                            props.attempt.skipped === 0
                                ? 'No items were skipped.'
                                : props.attempt.skipped === 1
                                  ? '1 item was skipped.'
                                  : `${props.attempt.skipped} items were skipped.`
                        }}
                    </p>

                    <p class="text-sm text-muted-foreground">
                        {{ props.attempt.encouragement }}
                    </p>
                </CardContent>

                <CardFooter class="flex flex-wrap justify-center gap-2">
                    <Button size="lg" class="h-11 cursor-pointer px-4" as-child>
                        <Link :href="props.urls.done">Done</Link>
                    </Button>

                    <Button
                        v-if="props.urls.start_another !== null"
                        size="lg"
                        variant="outline"
                        class="h-11 cursor-pointer px-4"
                        @click="startAnother"
                    >
                        <ArrowRightIcon aria-hidden="true" />
                        Start another attempt
                    </Button>

                    <Button
                        v-if="undoOffered && props.undo"
                        size="lg"
                        variant="ghost"
                        class="h-11 cursor-pointer px-4"
                        :disabled="undoProcessing"
                        @click="undo"
                    >
                        <RotateCcwIcon aria-hidden="true" />
                        Undo last rating
                    </Button>
                </CardFooter>
            </Card>
        </template>
    </ReviewExerciseShell>
</template>
