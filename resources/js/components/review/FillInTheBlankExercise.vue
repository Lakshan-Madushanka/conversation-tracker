<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { CheckIcon, ExternalLinkIcon, RotateCcwIcon } from '@lucide/vue';
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
import { Input } from '@/components/ui/input';
import type { FillInTheBlankCard } from '@/types';

const props = defineProps<{
    card: FillInTheBlankCard;
    /** The learner's answer so far, kept in the page across step changes. */
    attempt: string;
    /** Whether the learner has already checked this attempt. */
    checked: boolean;
    /** The rating already given to this card, if it has one. */
    rating: ReviewRating | null;
    /** A rating is on its way to the server. */
    processing: boolean;
    undoOffered: boolean;
    undoProcessing: boolean;
}>();

const emit = defineEmits<{
    'update:attempt': [value: string];
    'update:checked': [value: boolean];
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

const field = ref<InstanceType<typeof Input> | null>(null);
const answerHeading = ref<HTMLElement | null>(null);
const announcement = ref('');

const hasAttempt = computed(() => props.attempt.trim() !== '');
const isNote = computed(() => props.card.kind === 'grammar_note');
/** A notebook card opens the notebook, not a session that happens to match. */
const sourceLabel = computed(() =>
    props.card.source_kind === 'notebook'
        ? 'Open notebook'
        : 'Open original session',
);
/** A rated card opens already checked, as if it had been checked before. */
const answered = computed(() => props.checked || props.rating !== null);
const canRate = computed(() => answered.value && props.rating === null);

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

function fieldElement(): HTMLInputElement | null {
    return (field.value?.$el as HTMLInputElement | undefined) ?? null;
}

/**
 * Keep the field in view when the phone keyboard opens or changes height, so
 * the learner never types behind the keyboard.
 */
function keepFieldInView(): void {
    const active = document.activeElement as HTMLElement | null;
    const card = active?.closest('[data-blank-card]');

    if (active === null || card == null) {
        return;
    }

    active.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
}

function focusField(): void {
    nextTick(() => {
        const element = fieldElement();

        if (element === null) {
            return;
        }

        element.focus({ preventScroll: true });
        element.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
    });
}

/**
 * Check always works, even with an empty field. The answer is shown in place
 * and focus moves to it, so a screen reader reads it next.
 */
function check(): void {
    emit('update:checked', true);
    announcement.value = '';

    nextTick(() => {
        fieldElement()?.blur();
        announcement.value = 'Answer shown.';

        if (answerHeading.value !== null) {
            answerHeading.value.focus({ preventScroll: true });
            answerHeading.value.scrollIntoView({
                block: 'start',
                behavior: scrollBehavior(),
            });
        }

        window.setTimeout(keepFieldInView, 300);
    });
}

function onKeydown(event: KeyboardEvent): void {
    // Ctrl or Cmd + Enter also checks.
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        check();
    }
}

onMounted(() => {
    if (!answered.value) {
        focusField();
    }

    window.visualViewport?.addEventListener('resize', keepFieldInView);
});

onBeforeUnmount(() => {
    window.visualViewport?.removeEventListener('resize', keepFieldInView);
});
</script>

<template>
    <Card data-blank-card>
        <CardHeader>
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <Badge variant="secondary">Fill in the blank</Badge>
                <Badge v-if="props.rating" variant="outline">
                    Rated {{ ratingLabels[props.rating] }}
                </Badge>
            </div>

            <CardTitle
                class="text-2xl text-balance break-words sm:text-3xl [&_[data-blank]]:font-semibold [&_[data-blank]]:text-primary"
            >
                <!-- The server builds the safe prompt; the blank is part of it. -->
                <h1 v-html="props.card.prompt_html" />
            </CardTitle>
            <CardDescription>
                Type the missing word, then check. This is optional — check
                whenever you are ready.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4">
            <p class="sr-only" aria-live="polite">{{ announcement }}</p>

            <p
                v-if="props.card.hint"
                class="rounded-lg border bg-muted/40 p-3 text-sm break-words"
            >
                <span class="font-medium">{{ props.card.hint_label }}:</span>
                {{ props.card.hint }}
            </p>

            <div class="space-y-2">
                <label for="blank-attempt" class="text-sm font-medium">
                    Your answer
                    <span class="font-normal text-muted-foreground"
                        >(optional)</span
                    >
                </label>

                <Input
                    id="blank-attempt"
                    ref="field"
                    :model-value="props.attempt"
                    class="h-11 text-base"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    aria-describedby="blank-attempt-help"
                    @update:model-value="emit('update:attempt', String($event))"
                    @keydown="onKeydown"
                />

                <p
                    id="blank-attempt-help"
                    class="text-xs text-muted-foreground"
                >
                    Your answer stays on this page only. It is never saved or
                    sent anywhere.
                </p>
            </div>

            <div
                v-if="answered"
                class="space-y-4 rounded-lg border bg-muted/40 p-4"
            >
                <section v-if="hasAttempt" class="min-w-0">
                    <h2
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Your answer
                    </h2>
                    <!-- Never the server's HTML for the attempt: text only. -->
                    <p class="mt-1 text-lg break-words whitespace-pre-wrap">
                        {{ props.attempt }}
                    </p>
                </section>

                <section class="min-w-0">
                    <h2
                        ref="answerHeading"
                        tabindex="-1"
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase focus:outline-none"
                    >
                        Answer
                    </h2>
                    <!-- The server builds this safe answer with <mark>. -->
                    <div
                        class="mt-1 text-lg break-words [&_mark]:rounded [&_mark]:bg-primary/15 [&_mark]:px-0.5 [&_mark]:font-semibold [&_mark]:text-foreground [&_mark]:underline [&_mark]:decoration-2 [&_mark]:underline-offset-2"
                        :class="
                            isNote
                                ? 'max-h-80 overflow-y-auto focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none'
                                : ''
                        "
                        :tabindex="isNote ? 0 : undefined"
                        :aria-label="isNote ? 'Full grammar note' : undefined"
                        v-html="props.card.answer_html"
                    />
                    <p class="sr-only">
                        The underlined word is the missing word.
                    </p>
                </section>

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

        <CardFooter class="flex-col items-stretch gap-3">
            <template v-if="!answered">
                <Button
                    size="lg"
                    class="h-11 w-full cursor-pointer"
                    type="button"
                    @click="check"
                >
                    <CheckIcon aria-hidden="true" />
                    Check
                </Button>

                <p class="text-center text-xs text-muted-foreground">
                    Ctrl + Enter (or ⌘ + Enter) also checks.
                </p>
            </template>

            <template v-else>
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
                    <p class="text-sm text-muted-foreground">
                        How did that go?
                    </p>

                    <RatingButtons
                        :disabled="props.processing"
                        @rate="emit('rate', $event)"
                    />

                    <p class="text-center text-xs text-muted-foreground">
                        Shortcuts: 1 Hard, 2 Okay, 3 Easy
                    </p>

                    <p
                        v-if="props.processing"
                        class="sr-only"
                        aria-live="polite"
                    >
                        Saving your rating.
                    </p>
                </template>
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
