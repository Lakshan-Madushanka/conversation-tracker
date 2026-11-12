<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import {
    CheckIcon,
    ChevronDownIcon,
    CopyIcon,
    ExternalLinkIcon,
    RotateCcwIcon,
} from '@lucide/vue';
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
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Textarea } from '@/components/ui/textarea';
import type { MistakeCard } from '@/types';

/** About 1,000 characters; longer attempts are shortened, never sent. */
const ATTEMPT_LIMIT = 1000;

const props = defineProps<{
    card: MistakeCard;
    /** The learner's words so far, kept in the page across step changes. */
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

const textarea = ref<InstanceType<typeof Textarea> | null>(null);
const resultHeading = ref<HTMLElement | null>(null);
const shortened = ref(false);
const announcement = ref('');
const moreNotesOpen = ref(false);

const hasAttempt = computed(() => props.attempt.trim() !== '');
const canRate = computed(() => props.checked && props.rating === null);
/** A notebook card opens the notebook, not a session that happens to match. */
const sourceLabel = computed(() =>
    props.card.source_kind === 'notebook'
        ? 'Open notebook'
        : 'Open original session',
);

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

function textareaElement(): HTMLTextAreaElement | null {
    return (textarea.value?.$el as HTMLTextAreaElement | undefined) ?? null;
}

/**
 * Keep the field and its actions in view when the phone keyboard opens or
 * changes height, so the learner never types behind the keyboard.
 */
function keepFieldInView(): void {
    const active = document.activeElement as HTMLElement | null;
    const card = active?.closest('[data-mistake-card]');

    if (active === null || card == null) {
        return;
    }

    active.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
}

function focusAttempt(): void {
    nextTick(() => {
        const element = textareaElement();

        if (element === null) {
            return;
        }

        element.focus({ preventScroll: true });
        element.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
    });
}

function onAttemptInput(value: string | number): void {
    let next = String(value);

    if (next.length > ATTEMPT_LIMIT) {
        next = next.slice(0, ATTEMPT_LIMIT);
        shortened.value = true;
    }

    emit('update:attempt', next);
}

/** Put the original sentence in the field, ready to edit. */
function copySentence(): void {
    shortened.value = false;
    emit('update:attempt', props.card.said);

    nextTick(() => {
        const element = textareaElement();

        if (element === null) {
            return;
        }

        element.focus({ preventScroll: true });
        element.setSelectionRange(element.value.length, element.value.length);
        element.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
    });
}

/**
 * Check always works, even with an empty field. The result is shown in place
 * and focus moves to it, so a screen reader reads the correction next.
 */
function check(): void {
    emit('update:checked', true);
    announcement.value = '';

    nextTick(() => {
        textareaElement()?.blur();
        announcement.value = 'Correction shown.';

        if (resultHeading.value !== null) {
            resultHeading.value.focus({ preventScroll: true });
            resultHeading.value.scrollIntoView({
                block: 'start',
                behavior: scrollBehavior(),
            });
        }

        window.setTimeout(keepFieldInView, 300);
    });
}

function onTextareaKeydown(event: KeyboardEvent): void {
    // Plain Enter is a new line; Ctrl or Cmd + Enter checks.
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        check();
    }
}

onMounted(() => {
    if (!props.checked && props.rating === null) {
        focusAttempt();
    }

    window.visualViewport?.addEventListener('resize', keepFieldInView);
});

onBeforeUnmount(() => {
    window.visualViewport?.removeEventListener('resize', keepFieldInView);
});
</script>

<template>
    <Card data-mistake-card>
        <CardHeader>
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <Badge variant="secondary">Fix the mistake</Badge>
                <Badge v-if="props.rating" variant="outline">
                    Rated {{ ratingLabels[props.rating] }}
                </Badge>
            </div>

            <CardTitle class="text-2xl text-balance break-words sm:text-3xl">
                <h1>{{ props.card.said }}</h1>
            </CardTitle>
            <CardDescription>
                Read it as you said it, then try it once more in your own words.
                This is optional — check whenever you are ready.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4">
            <p class="sr-only" aria-live="polite">{{ announcement }}</p>

            <div class="space-y-2">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-x-2"
                >
                    <label for="mistake-attempt" class="text-sm font-medium">
                        Your attempt
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        >
                    </label>

                    <span
                        class="text-xs text-muted-foreground tabular-nums"
                        aria-hidden="true"
                    >
                        {{ props.attempt.length }} / {{ ATTEMPT_LIMIT }}
                    </span>
                </div>

                <Textarea
                    id="mistake-attempt"
                    ref="textarea"
                    :model-value="props.attempt"
                    rows="3"
                    class="min-h-24 text-base"
                    placeholder="Say it again here, if you like."
                    aria-describedby="mistake-attempt-help"
                    @update:model-value="onAttemptInput"
                    @keydown="onTextareaKeydown"
                />

                <div
                    id="mistake-attempt-help"
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <p class="text-xs text-muted-foreground">
                        <template v-if="shortened">
                            Longer text was shortened to {{ ATTEMPT_LIMIT }}
                            characters. Your attempt stays on this page only.
                        </template>
                        <template v-else>
                            Your attempt is never saved or sent anywhere.
                        </template>
                    </p>

                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 cursor-pointer"
                        type="button"
                        @click="copySentence"
                    >
                        <CopyIcon aria-hidden="true" />
                        Copy the sentence to edit
                    </Button>
                </div>
            </div>

            <div
                v-if="props.checked"
                class="space-y-4 rounded-lg border bg-muted/40 p-4"
            >
                <div
                    class="grid gap-4"
                    :class="hasAttempt ? 'sm:grid-cols-2' : ''"
                >
                    <section v-if="hasAttempt" class="min-w-0">
                        <h2
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Your attempt
                        </h2>
                        <p class="mt-1 text-lg break-words whitespace-pre-wrap">
                            {{ props.attempt }}
                        </p>
                    </section>

                    <section class="min-w-0">
                        <h2
                            ref="resultHeading"
                            tabindex="-1"
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase focus:outline-none"
                        >
                            Correction
                        </h2>
                        <!-- The server reduces this to bold and italic only. -->
                        <p
                            class="mt-1 text-lg font-medium break-words"
                            v-html="props.card.correction_html"
                        />
                    </section>
                </div>

                <p class="text-sm text-muted-foreground">
                    Compare the two. How close was your version?
                </p>

                <section v-if="props.card.reason">
                    <h2
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Why
                    </h2>
                    <p class="mt-1 text-sm break-words">
                        {{ props.card.reason }}
                    </p>

                    <Collapsible
                        v-if="props.card.more_reasons.length > 0"
                        v-model:open="moreNotesOpen"
                        class="mt-2"
                    >
                        <CollapsibleTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-9 cursor-pointer px-2"
                                type="button"
                            >
                                More notes
                                <ChevronDownIcon
                                    class="size-4 transition-transform"
                                    :class="moreNotesOpen ? 'rotate-180' : ''"
                                    aria-hidden="true"
                                />
                            </Button>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <ul class="mt-1 grid gap-2 border-l-2 pl-3">
                                <li
                                    v-for="note in props.card.more_reasons"
                                    :key="note"
                                    class="text-sm break-words text-muted-foreground"
                                >
                                    {{ note }}
                                </li>
                            </ul>
                        </CollapsibleContent>
                    </Collapsible>
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
            <template v-if="!props.checked">
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
