<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckCheckIcon, RotateCcwIcon } from '@lucide/vue';
import ReviewExerciseShell from '@/components/review/ReviewExerciseShell.vue';
import StatusSuggestionCard from '@/components/review/StatusSuggestionCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    finishEarly as reviewFinishEarly,
    index as reviewIndex,
    rate as reviewRate,
    store as reviewStore,
    undo as reviewUndo,
} from '@/routes/review';
import type {
    CompletedSection,
    CurrentReviewItem,
    ReviewCounts,
    ReviewSection,
    StatusSuggestionData,
} from '@/types';

const props = defineProps<{
    /** The review in the address does not exist (or was never named). */
    notFound: boolean;
    finished: boolean;
    /** One step per exercise kind, in the fixed friendly order. */
    sections: ReviewSection[];
    /** The card on screen, or null on a finished-section panel. */
    current: CurrentReviewItem | null;
    /** A finished section opened from the stepper. */
    completedSection: CompletedSection | null;
    position: { current: number; total: number };
    progress: number;
    counts: ReviewCounts;
    reviewedCount: number;
    undo: { kind: string; item_identity: string } | null;
    canReviewMore: boolean;
    /** Whether the sitting was started from the day's suggestion. */
    isDailyReview: boolean;
    reviewId: number | null;
    /** The statuses the finished sitting earned, at most two, never during one. */
    suggestions: StatusSuggestionData[];
}>();

const startAnotherForm = useForm({ more: true });

const reviewUrl = computed(() => reviewIndex.url());

const rateUrl = computed(() => reviewRate.url({ review: props.reviewId ?? 0 }));

const undoUrl = computed(() => reviewUndo.url({ review: props.reviewId ?? 0 }));

const finishEarlyUrl = computed(() =>
    reviewFinishEarly.url({ review: props.reviewId ?? 0 }),
);

function startAnotherReview(): void {
    startAnotherForm.post(reviewStore.url());
}
</script>

<template>
    <Head title="Review" />

    <ReviewExerciseShell
        :not-found="props.notFound"
        :finished="props.finished"
        :sections="props.sections"
        :current="props.current"
        :completed-section="props.completedSection"
        :position="props.position"
        :progress="props.progress"
        :counts="props.counts"
        :reviewed-count="props.reviewedCount"
        :undo="props.undo"
        :exit-href="reviewUrl"
        :back-href="reviewUrl"
        back-label="Back to Review"
        subject="review"
        not-found-title="We couldn’t find that review"
        not-found-description="It may have been finished already, or the address may have changed. Nothing is lost — your saved words are still in Collections."
        finish-label="Finish review"
        :rate-url="rateUrl"
        :undo-url="undoUrl"
        :finish-early-url="finishEarlyUrl"
    >
        <template #finish-confirm="{ unratedCount }">
            {{ unratedCount }}
            {{ unratedCount === 1 ? 'item is' : 'items are' }}
            still to rate. They will stay scheduled for another day, and this
            sitting will not count as your review for today.
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
                        <h1>Nicely done</h1>
                    </CardTitle>
                    <CardDescription>
                        You looked at {{ props.reviewedCount }}
                        {{ props.reviewedCount === 1 ? 'item' : 'items' }}. Take
                        your time — every visit helps.
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

                    <p
                        v-if="props.counts.hard > 0"
                        class="text-sm text-muted-foreground"
                    >
                        The ones you found hard are worth another look. They
                        will come back soon. That is how they become familiar.
                    </p>

                    <Separator v-if="props.isDailyReview" />

                    <div
                        v-if="props.isDailyReview"
                        class="rounded-lg border border-primary/30 bg-primary/5 p-3"
                    >
                        <p class="font-medium">You've reviewed today</p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            Nice work — a few minutes today keeps it fresh.
                        </p>
                    </div>

                    <!-- At most two status offers, never above the summary, and
                        never holding the finish screen up. -->
                    <div
                        v-if="
                            props.reviewId !== null &&
                            props.suggestions.length > 0
                        "
                        class="grid gap-3"
                        aria-label="Suggested marks"
                    >
                        <StatusSuggestionCard
                            v-for="(suggestion, index) in props.suggestions"
                            :key="`${suggestion.kind}-${suggestion.item_identity}`"
                            :review-id="props.reviewId"
                            :suggestion="suggestion"
                            :default-focus="index === 0"
                        />
                    </div>
                </CardContent>

                <CardFooter class="flex flex-wrap justify-center gap-2">
                    <Button size="lg" class="h-11 cursor-pointer px-4" as-child>
                        <Link :href="reviewUrl">Done</Link>
                    </Button>

                    <Button
                        v-if="props.canReviewMore"
                        size="lg"
                        variant="outline"
                        class="h-11 cursor-pointer px-4"
                        :disabled="startAnotherForm.processing"
                        @click="startAnotherReview"
                    >
                        <ArrowRightIcon aria-hidden="true" />
                        Review a little more
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
