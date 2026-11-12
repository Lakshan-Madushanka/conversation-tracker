<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { EyeIcon, ExternalLinkIcon, RotateCcwIcon } from '@lucide/vue';
import type { ReviewRating } from '@/components/RatingButtons.vue';
import RatingButtons from '@/components/RatingButtons.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { SayItAgainCard } from '@/types';

const props = defineProps<{
    card: SayItAgainCard;
    /** The rating already given to this card, if it has one. */
    rating: ReviewRating | null;
    /** A rating is on its way to the server. */
    processing: boolean;
    undoOffered: boolean;
    undoProcessing: boolean;
}>();

const emit = defineEmits<{
    rate: [rating: ReviewRating];
    undo: [];
    /** Whether the 1/2/3 shortcuts may rate right now. */
    ready: [value: boolean];
}>();

const ratingLabels: Record<ReviewRating, string> = {
    hard: 'Hard',
    okay: 'Okay',
    easy: 'Easy',
};

/** The question is the first thing the card reads out. */
const questionRegion = ref<HTMLElement | null>(null);
const naturalHeading = ref<HTMLElement | null>(null);
const announcement = ref('');

/** An already-rated card opens with its answer showing. */
const revealed = ref(props.rating !== null);

/** A notebook card opens the notebook, not a session that happens to match. */
const sourceLabel = computed(() =>
    props.card.source_kind === 'notebook'
        ? 'Open notebook'
        : 'Open original session',
);

const canRate = computed(() => revealed.value && props.rating === null);

watch(
    [canRate, () => props.processing],
    () => emit('ready', canRate.value && !props.processing),
    { immediate: true },
);

/** Reduced motion keeps the phone's own scrolling calm. */
function scrollBehavior(): ScrollBehavior {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 'auto'
        : 'smooth';
}

/**
 * Show the natural answer in place and move focus to it, so a screen reader
 * reads the natural version next.
 */
function reveal(): void {
    if (revealed.value) {
        return;
    }

    revealed.value = true;
    announcement.value = '';

    nextTick(() => {
        announcement.value = 'Natural answer shown.';

        if (naturalHeading.value !== null) {
            naturalHeading.value.focus({ preventScroll: true });
            naturalHeading.value.scrollIntoView({
                block: 'start',
                behavior: scrollBehavior(),
            });
        }
    });
}

function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    if (
        target !== null &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.tagName === 'SELECT' ||
            target.isContentEditable)
    ) {
        return;
    }

    // Enter and Space belong to the control that has focus (a step, or Show
    // natural answer); only the bare page reveals the answer.
    if (
        !revealed.value &&
        target?.closest('button, a, [role="button"]') == null &&
        (event.key === ' ' || event.key === 'Enter')
    ) {
        event.preventDefault();
        reveal();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    nextTick(() => questionRegion.value?.focus({ preventScroll: true }));
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Card data-say-it-again-card>
        <CardHeader>
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ props.card.label }}</Badge>
                <Badge v-if="props.rating" variant="outline">
                    Rated {{ ratingLabels[props.rating] }}
                </Badge>
            </div>

            <CardTitle class="text-2xl sm:text-3xl">
                <div
                    ref="questionRegion"
                    role="group"
                    tabindex="-1"
                    aria-labelledby="say-it-again-question"
                    class="focus:outline-none"
                >
                    <p
                        id="say-it-again-question"
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Tutor’s question
                    </p>
                    <!-- The server reduces this to bold, italic, lists and
                        line breaks, so it is safe to show. -->
                    <div
                        class="mt-1 leading-relaxed font-medium break-words [&_li]:marker:text-muted-foreground [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-6"
                        v-html="props.card.question_html"
                    />
                </div>
            </CardTitle>
            <CardDescription v-if="!revealed">
                Answer out loud in your own words, then show the natural
                version.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4">
            <p class="sr-only" aria-live="polite">{{ announcement }}</p>

            <Button
                v-if="!revealed"
                size="lg"
                class="h-11 w-full cursor-pointer"
                type="button"
                @click="reveal"
            >
                <EyeIcon aria-hidden="true" />
                Show natural answer
            </Button>

            <div v-else class="space-y-4 rounded-lg border bg-muted/40 p-4">
                <section aria-labelledby="say-it-again-natural">
                    <h2
                        id="say-it-again-natural"
                        ref="naturalHeading"
                        tabindex="-1"
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase focus:outline-none"
                    >
                        Natural answer
                    </h2>
                    <!-- The server reduces this to bold, italic, lists and
                        line breaks, so it is safe to show. -->
                    <div
                        class="mt-1 text-lg leading-relaxed break-words [&_li]:marker:text-muted-foreground [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-6"
                        v-html="props.card.natural_html"
                    />
                </section>

                <p class="text-sm text-muted-foreground">
                    Compare it with what you said out loud. How close was it?
                </p>

                <a
                    v-if="props.card.session_url"
                    :href="props.card.session_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    {{ sourceLabel }}
                    <ExternalLinkIcon class="size-3.5" aria-hidden="true" />
                    <span class="sr-only">(opens in a new tab)</span>
                </a>
            </div>
        </CardContent>

        <CardFooter v-if="revealed" class="flex-col items-stretch gap-3">
            <template v-if="props.rating">
                <p class="text-sm text-muted-foreground">
                    You rated this one {{ ratingLabels[props.rating] }}.
                    <template v-if="props.undoOffered">
                        You can undo it below.
                    </template>
                    <template v-else>
                        Open another step whenever you are ready.
                    </template>
                </p>
            </template>

            <template v-else>
                <p class="text-sm text-muted-foreground">How did that go?</p>

                <RatingButtons
                    :disabled="props.processing"
                    @rate="emit('rate', $event)"
                />

                <p class="text-center text-xs text-muted-foreground">
                    Shortcuts: 1 Hard, 2 Okay, 3 Easy
                </p>

                <p v-if="props.processing" class="sr-only" aria-live="polite">
                    Saving your rating.
                </p>
            </template>

            <Button
                v-if="props.undoOffered"
                variant="ghost"
                size="sm"
                class="cursor-pointer self-center"
                type="button"
                :disabled="props.undoProcessing"
                @click="emit('undo')"
            >
                <RotateCcwIcon aria-hidden="true" />
                Undo last rating
            </Button>
        </CardFooter>
    </Card>
</template>
