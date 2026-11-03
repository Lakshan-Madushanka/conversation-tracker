<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    CheckCheckIcon,
    EyeIcon,
    ExternalLinkIcon,
    RotateCcwIcon,
} from '@lucide/vue';
import type { ReviewRating } from '@/components/RatingButtons.vue';
import RatingButtons from '@/components/RatingButtons.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import FocusLayout from '@/layouts/FocusLayout.vue';
import {
    index as reviewIndex,
    rate as reviewRate,
    store as reviewStore,
    undo as reviewUndo,
} from '@/routes/review';

interface ReviewCard {
    kind: 'vocabulary' | 'phrase';
    item_identity: string;
    label: string;
    front: string;
    meaning: string;
    example: string | null;
    session_url: string | null;
}

const props = defineProps<{
    /** The review in the address does not exist (or was never named). */
    notFound: boolean;
    finished: boolean;
    card: ReviewCard | null;
    position: { current: number; total: number };
    progress: number;
    counts: { hard: number; okay: number; easy: number };
    reviewedCount: number;
    undo: { kind: string; item_identity: string } | null;
    canReviewMore: boolean;
    /** Whether the sitting was started from the day's suggestion. */
    isDailyReview: boolean;
    reviewId: number | null;
}>();

const revealed = ref(false);
const undoOffered = ref(false);
const saveFailed = ref(false);

const rateForm = useForm({
    kind: '',
    item_identity: '',
    rating: '',
});

const undoForm = useForm({
    kind: '',
    item_identity: '',
});

const startAnotherForm = useForm({ more: true });

let undoTimer: number | undefined;

const announcement = computed(() => {
    if (props.notFound) {
        return 'This review could not be found.';
    }

    if (props.finished) {
        return 'Review complete.';
    }

    if (!props.card) {
        return '';
    }

    return revealed.value
        ? `${props.card.label} answer shown. Choose Hard, Okay, or Easy.`
        : `${props.card.label}, ${props.position.current} of ${props.position.total}. Try to recall it, then show the answer.`;
});

watch(
    () => props.card?.item_identity,
    () => {
        revealed.value = false;
        saveFailed.value = false;
    },
);

watch(
    () => props.undo,
    (value) => {
        undoOffered.value = value !== null;

        if (undoTimer !== undefined) {
            window.clearTimeout(undoTimer);
        }

        if (value !== null) {
            undoTimer = window.setTimeout(() => {
                undoOffered.value = false;
            }, 8000);
        }
    },
    { immediate: true },
);

function rate(rating: ReviewRating): void {
    if (!props.card || props.reviewId === null || rateForm.processing) {
        return;
    }

    saveFailed.value = false;
    rateForm.kind = props.card.kind;
    rateForm.item_identity = props.card.item_identity;
    rateForm.rating = rating;
    rateForm.post(reviewRate.url({ review: props.reviewId }), {
        preserveScroll: true,
        onError: () => {
            saveFailed.value = true;
        },
    });
}

function undoLastRating(): void {
    if (!props.undo || props.reviewId === null || undoForm.processing) {
        return;
    }

    undoForm.kind = props.undo.kind;
    undoForm.item_identity = props.undo.item_identity;
    undoForm.delete(reviewUndo.url({ review: props.reviewId }), {
        preserveScroll: true,
    });
}

function startAnotherReview(): void {
    startAnotherForm.post(reviewStore.url());
}

function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    // Shortcuts never interfere with typing.
    if (
        target !== null &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable)
    ) {
        return;
    }

    if (props.notFound || props.finished || !props.card) {
        return;
    }

    if (!revealed.value) {
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            revealed.value = true;
        }

        return;
    }

    const shortcuts: Record<string, ReviewRating> = {
        '1': 'hard',
        '2': 'okay',
        '3': 'easy',
    };

    const rating = shortcuts[event.key];

    if (rating) {
        event.preventDefault();
        rate(rating);
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);

    if (undoTimer !== undefined) {
        window.clearTimeout(undoTimer);
    }
});
</script>

<template>
    <FocusLayout
        :progress="props.progress"
        :position="props.position"
        :exit-href="reviewIndex.url()"
    >
        <Head title="Review" />

        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <Card v-if="props.notFound" class="text-center">
            <CardHeader>
                <CardTitle class="text-xl">
                    We couldn’t find that review
                </CardTitle>
                <CardDescription>
                    It may have been finished already, or the address may have
                    changed. Nothing is lost — your saved words are still in
                    Collections.
                </CardDescription>
            </CardHeader>
            <CardFooter class="justify-center">
                <Button size="lg" class="h-11 px-4" as-child>
                    <Link :href="reviewIndex.url()">Back to Review</Link>
                </Button>
            </CardFooter>
        </Card>

        <Card v-else-if="props.finished" class="text-center">
            <CardHeader>
                <span
                    class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <CheckCheckIcon class="size-6" />
                </span>
                <CardTitle class="text-xl">Nicely done</CardTitle>
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
                    The ones you found hard are worth another look. They will
                    come back soon. That is how they become familiar.
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
            </CardContent>

            <CardFooter class="flex flex-wrap justify-center gap-2">
                <Button size="lg" class="h-11 px-4" as-child>
                    <Link :href="reviewIndex.url()">Done</Link>
                </Button>

                <Button
                    v-if="props.canReviewMore"
                    size="lg"
                    variant="outline"
                    class="h-11 px-4"
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
                    class="h-11 px-4"
                    :disabled="undoForm.processing"
                    @click="undoLastRating"
                >
                    <RotateCcwIcon aria-hidden="true" />
                    Undo last rating
                </Button>
            </CardFooter>
        </Card>

        <Card v-else-if="props.card">
            <CardHeader>
                <div class="mb-1 flex items-center gap-2">
                    <Badge variant="secondary">{{ props.card.label }}</Badge>
                    <span class="text-xs text-muted-foreground tabular-nums">
                        {{ props.position.current }} of
                        {{ props.position.total }}
                    </span>
                </div>

                <CardTitle
                    class="text-2xl text-balance break-words sm:text-3xl"
                >
                    {{ props.card.front }}
                </CardTitle>
                <CardDescription v-if="!revealed">
                    Try to recall the meaning, then show the answer.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <Button
                    v-if="!revealed"
                    size="lg"
                    class="h-11 w-full"
                    @click="revealed = true"
                >
                    <EyeIcon aria-hidden="true" />
                    Show answer
                </Button>

                <div v-else class="space-y-4 rounded-lg border bg-muted/40 p-4">
                    <div>
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Meaning
                        </p>
                        <p class="mt-1 text-lg font-medium break-words">
                            {{ props.card.meaning }}
                        </p>
                    </div>

                    <div v-if="props.card.example">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Example
                        </p>
                        <p class="mt-1 text-sm break-words">
                            {{ props.card.example }}
                        </p>
                    </div>

                    <a
                        v-if="props.card.session_url"
                        :href="props.card.session_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        Open original session
                        <ExternalLinkIcon class="size-3.5" aria-hidden="true" />
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </div>

                <p
                    v-if="saveFailed"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    That rating was not saved. Please try again.
                </p>
            </CardContent>

            <CardFooter v-if="revealed" class="flex-col items-stretch gap-3">
                <p class="text-sm text-muted-foreground">How did that go?</p>

                <RatingButtons @rate="rate" />

                <p class="text-center text-xs text-muted-foreground">
                    Shortcuts: 1 Hard, 2 Okay, 3 Easy
                </p>

                <Button
                    v-if="undoOffered && props.undo"
                    variant="ghost"
                    size="sm"
                    class="self-center"
                    :disabled="undoForm.processing"
                    @click="undoLastRating"
                >
                    <RotateCcwIcon aria-hidden="true" />
                    Undo last rating
                </Button>
            </CardFooter>
        </Card>
    </FocusLayout>
</template>
