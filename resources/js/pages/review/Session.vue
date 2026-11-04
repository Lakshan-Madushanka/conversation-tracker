<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
    type Component,
} from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    BookOpenIcon,
    CheckCheckIcon,
    CheckIcon,
    MessageCircleIcon,
    RotateCcwIcon,
    SquarePenIcon,
} from '@lucide/vue';
import type { ReviewRating } from '@/components/RatingButtons.vue';
import FlashcardExercise from '@/components/review/FlashcardExercise.vue';
import FixTheMistakeExercise from '@/components/review/FixTheMistakeExercise.vue';
import SayItAgainExercise from '@/components/review/SayItAgainExercise.vue';
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
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    Stepper,
    StepperItem,
    StepperSeparator,
    StepperTrigger,
} from '@/components/ui/stepper';
import FocusLayout from '@/layouts/FocusLayout.vue';
import {
    index as reviewIndex,
    rate as reviewRate,
    store as reviewStore,
    undo as reviewUndo,
} from '@/routes/review';
import type {
    CompletedSection,
    CurrentReviewItem,
    ReviewCard,
    ReviewCounts,
    ReviewSection,
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
}>();

const ratingLabels: Record<ReviewRating, string> = {
    hard: 'Hard',
    okay: 'Okay',
    easy: 'Easy',
};

const sectionIcons: Record<string, Component> = {
    'book-open': BookOpenIcon,
    'square-pen': SquarePenIcon,
    'message-circle': MessageCircleIcon,
};

/** The learner's unsent attempts and checks, kept while the page lives. */
const attempts = reactive<Record<string, string>>({});
const checkedItems = reactive<Record<string, boolean>>({});
const lastPosition = reactive<Record<string, number>>({});

const undoOffered = ref(false);
const saveFailed = ref(false);
const canRateByShortcut = ref(false);

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

function itemKey(card: ReviewCard): string {
    return `${card.kind}:${card.item_identity}`;
}

const currentKey = computed(() =>
    props.current === null ? '' : itemKey(props.current.card),
);

const activeSectionKey = computed(
    () =>
        props.current?.step ??
        props.completedSection?.key ??
        props.sections[0]?.key ??
        '',
);

const activeSectionNumber = computed(
    () =>
        props.sections.find((section) => section.key === activeSectionKey.value)
            ?.number ?? 1,
);

const unratedCount = computed(
    () => props.position.total - props.position.current,
);

const announcement = computed(() => {
    if (props.notFound) {
        return 'This review could not be found.';
    }

    if (props.finished) {
        return 'Review complete.';
    }

    if (props.completedSection !== null) {
        return `${props.completedSection.label} is complete. All done here.`;
    }

    if (props.current === null) {
        return '';
    }

    if (props.current.rating !== null) {
        return `${props.current.label}, ${props.current.stepPosition} of ${props.current.total}. You rated this ${ratingLabels[props.current.rating]}.`;
    }

    return `${props.current.label}, ${props.current.stepPosition} of ${props.current.total}.`;
});

watch(
    () => props.current,
    (current) => {
        if (current !== null) {
            lastPosition[current.step] = current.position;
        }
    },
    { immediate: true },
);

watch(currentKey, () => {
    canRateByShortcut.value = false;
    saveFailed.value = false;
});

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

function iconFor(name: string): Component {
    return sectionIcons[name] ?? BookOpenIcon;
}

function stepLabel(section: ReviewSection): string {
    const parts = [section.label, `${section.rated} of ${section.total} rated`];

    if (section.completed) {
        parts.push('completed');
    }

    if (section.key === activeSectionKey.value) {
        parts.push('current step');
    }

    return parts.join(', ');
}

/** Keep the item the learner was on when switching back to a section. */
function withRememberedPosition(url: string): string {
    const step = new URL(url, window.location.origin).searchParams.get('step');
    const position = step === null ? undefined : lastPosition[step];

    if (position === undefined) {
        return url;
    }

    const target = new URL(url, window.location.origin);
    target.searchParams.set('position', String(position));

    return `${target.pathname}${target.search}`;
}

/**
 * Open the step the learner picked. The sitting stays on the same page, so
 * the stepper keeps its place, and each section returns to its next unrated
 * item.
 */
function goToStep(step: number | undefined): void {
    const section = props.sections.find((item) => item.number === step);

    if (section === undefined || section.key === activeSectionKey.value) {
        return;
    }

    router.visit(withRememberedPosition(section.url), {
        preserveScroll: true,
        preserveState: true,
    });
}

function setAttempt(value: string): void {
    if (currentKey.value !== '') {
        attempts[currentKey.value] = value;
    }
}

function setChecked(value: boolean): void {
    if (currentKey.value !== '') {
        checkedItems[currentKey.value] = value;
    }
}

function onReady(value: boolean): void {
    canRateByShortcut.value = value;
}

function rate(rating: ReviewRating): void {
    // The first rating counts; an already-rated card is shown, not rated again.
    if (
        props.current === null ||
        props.reviewId === null ||
        props.current.rating !== null ||
        rateForm.processing
    ) {
        return;
    }

    saveFailed.value = false;
    rateForm.kind = props.current.card.kind;
    rateForm.item_identity = props.current.card.item_identity;
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

/** Leave the sitting early; nothing unrated is rescheduled. */
function finishEarly(): void {
    router.visit(reviewIndex.url());
}

function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    // Shortcuts never interfere with typing or browser/OS shortcuts.
    if (
        target !== null &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.tagName === 'SELECT' ||
            target.isContentEditable)
    ) {
        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (
        !canRateByShortcut.value ||
        rateForm.processing ||
        props.finished ||
        props.current === null ||
        props.current.rating !== null
    ) {
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

        <template #above>
            <section
                v-if="
                    !props.notFound &&
                    !props.finished &&
                    props.sections.length > 1
                "
                class="mb-4"
                aria-label="Review steps"
            >
                <Stepper
                    :model-value="activeSectionNumber"
                    :linear="false"
                    class="w-full items-start gap-1"
                    @update:model-value="goToStep"
                >
                    <StepperItem
                        v-for="(section, index) in props.sections"
                        :key="section.key"
                        :step="section.number"
                        class="relative min-w-0 flex-1 cursor-pointer flex-col items-center gap-0"
                    >
                        <StepperSeparator
                            v-if="index < props.sections.length - 1"
                            class="absolute top-[23px] left-[calc(50%+22px)] h-0.5 w-[calc(100%-44px)] rounded-full bg-border"
                        />

                        <StepperTrigger
                            class="min-h-11 w-full cursor-pointer flex-col gap-1 p-1"
                            :aria-current="
                                section.key === activeSectionKey
                                    ? 'step'
                                    : undefined
                            "
                            :aria-label="stepLabel(section)"
                        >
                            <span
                                class="z-10 flex size-10 shrink-0 items-center justify-center rounded-full border transition-colors"
                                :class="
                                    section.key === activeSectionKey
                                        ? 'border-primary bg-primary text-primary-foreground ring-2 ring-primary/30'
                                        : section.completed
                                          ? 'border-primary/40 bg-background text-primary'
                                          : 'border-border bg-background text-muted-foreground'
                                "
                            >
                                <CheckIcon
                                    v-if="section.completed"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <component
                                    :is="iconFor(section.icon)"
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </span>

                            <span class="w-full min-w-0">
                                <span
                                    class="block text-[11px] leading-tight font-medium text-balance"
                                    :class="
                                        section.key === activeSectionKey
                                            ? 'block'
                                            : 'hidden sm:block'
                                    "
                                >
                                    {{ section.label }}
                                </span>
                                <span
                                    class="block text-[10px] text-muted-foreground tabular-nums sm:text-xs"
                                >
                                    {{ section.rated }} of {{ section.total }}
                                </span>
                            </span>
                        </StepperTrigger>
                    </StepperItem>
                </Stepper>
            </section>

            <div
                v-if="
                    !props.notFound &&
                    !props.finished &&
                    props.position.current > 0
                "
                class="mb-4 flex justify-center"
            >
                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="cursor-pointer text-muted-foreground"
                        >
                            Finish review
                        </Button>
                    </AlertDialogTrigger>

                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Finish this review early?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ unratedCount }}
                                {{
                                    unratedCount === 1 ? 'item is' : 'items are'
                                }}
                                still to rate. They will stay scheduled for
                                another day, and this sitting will not count as
                                your review for today.
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <AlertDialogFooter>
                            <AlertDialogCancel class="cursor-pointer">
                                Keep reviewing
                            </AlertDialogCancel>
                            <AlertDialogAction
                                class="cursor-pointer"
                                @click="finishEarly"
                            >
                                Finish review
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </template>

        <Card v-if="props.notFound" class="text-center">
            <CardHeader>
                <CardTitle class="text-xl">
                    <h1>We couldn’t find that review</h1>
                </CardTitle>
                <CardDescription>
                    It may have been finished already, or the address may have
                    changed. Nothing is lost — your saved words are still in
                    Collections.
                </CardDescription>
            </CardHeader>
            <CardFooter class="justify-center">
                <Button size="lg" class="h-11 cursor-pointer px-4" as-child>
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
                <Button size="lg" class="h-11 cursor-pointer px-4" as-child>
                    <Link :href="reviewIndex.url()">Done</Link>
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
                    :disabled="undoForm.processing"
                    @click="undoLastRating"
                >
                    <RotateCcwIcon aria-hidden="true" />
                    Undo last rating
                </Button>
            </CardFooter>
        </Card>

        <Card v-else-if="props.completedSection" class="text-center">
            <CardHeader>
                <span
                    class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <CheckCheckIcon class="size-6" />
                </span>
                <CardTitle class="text-xl">
                    <h1>All done here</h1>
                </CardTitle>
                <CardDescription>
                    Every {{ props.completedSection.label.toLowerCase() }} item
                    in this review has a rating.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <div class="grid grid-cols-3 gap-2 text-sm">
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ props.completedSection.counts.easy }}
                        </p>
                        <p class="text-muted-foreground">Easy</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ props.completedSection.counts.okay }}
                        </p>
                        <p class="text-muted-foreground">Okay</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ props.completedSection.counts.hard }}
                        </p>
                        <p class="text-muted-foreground">Hard</p>
                    </div>
                </div>
            </CardContent>

            <CardFooter class="flex flex-col gap-2">
                <Button
                    v-if="props.completedSection.next_url"
                    size="lg"
                    class="h-11 w-full cursor-pointer"
                    as-child
                >
                    <Link :href="props.completedSection.next_url">
                        Continue with the next step
                    </Link>
                </Button>

                <Button
                    v-if="undoOffered && props.undo"
                    variant="ghost"
                    size="sm"
                    class="cursor-pointer self-center"
                    :disabled="undoForm.processing"
                    @click="undoLastRating"
                >
                    <RotateCcwIcon aria-hidden="true" />
                    Undo last rating
                </Button>

                <Button
                    variant="ghost"
                    size="sm"
                    class="cursor-pointer"
                    as-child
                >
                    <Link :href="reviewIndex.url()">Back to Review</Link>
                </Button>
            </CardFooter>
        </Card>

        <template v-else-if="props.current">
            <FlashcardExercise
                v-if="props.current.card.exercise === 'flashcard'"
                :key="currentKey"
                :card="props.current.card"
                :rating="props.current.rating"
                :processing="rateForm.processing"
                :undo-offered="undoOffered"
                :undo-processing="undoForm.processing"
                @rate="rate"
                @undo="undoLastRating"
                @ready="onReady"
            />

            <SayItAgainExercise
                v-else-if="props.current.card.exercise === 'say_it_again'"
                :key="currentKey"
                :card="props.current.card"
                :rating="props.current.rating"
                :processing="rateForm.processing"
                :undo-offered="undoOffered"
                :undo-processing="undoForm.processing"
                @rate="rate"
                @undo="undoLastRating"
                @ready="onReady"
            />

            <FixTheMistakeExercise
                v-else
                :key="currentKey"
                :card="props.current.card"
                :attempt="attempts[currentKey] ?? ''"
                :checked="checkedItems[currentKey] ?? false"
                :rating="props.current.rating"
                :processing="rateForm.processing"
                :undo-offered="undoOffered"
                :undo-processing="undoForm.processing"
                @update:attempt="setAttempt"
                @update:checked="setChecked"
                @rate="rate"
                @undo="undoLastRating"
                @ready="onReady"
            />

            <p
                v-if="saveFailed"
                class="mt-3 text-center text-sm text-destructive"
                role="alert"
            >
                That rating was not saved. Please try again.
            </p>
        </template>
    </FocusLayout>
</template>
