<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import type { FlashcardCard } from '@/types';

const props = defineProps<{
    card: FlashcardCard;
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

/** An already-rated card opens with its answer showing. */
const revealed = ref(props.rating !== null);

const canRate = computed(() => revealed.value && props.rating === null);

watch(
    [canRate, () => props.processing],
    () => emit('ready', canRate.value && !props.processing),
    { immediate: true },
);

function reveal(): void {
    revealed.value = true;
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
    // answer); only the bare page reveals the card.
    if (
        !revealed.value &&
        target?.closest('button, a, [role="button"]') == null &&
        (event.key === ' ' || event.key === 'Enter')
    ) {
        event.preventDefault();
        reveal();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Card>
        <CardHeader>
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ props.card.label }}</Badge>
                <Badge v-if="props.rating" variant="outline">
                    Rated {{ ratingLabels[props.rating] }}
                </Badge>
            </div>

            <CardTitle class="text-2xl text-balance break-words sm:text-3xl">
                <h1>{{ props.card.front }}</h1>
            </CardTitle>
            <CardDescription v-if="!revealed">
                Try to recall the meaning, then show the answer.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4">
            <Button
                v-if="!revealed"
                size="lg"
                class="h-11 w-full cursor-pointer"
                @click="reveal"
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
            </template>

            <Button
                v-if="props.undoOffered"
                variant="ghost"
                size="sm"
                class="cursor-pointer self-center"
                :disabled="props.undoProcessing"
                @click="emit('undo')"
            >
                <RotateCcwIcon aria-hidden="true" />
                Undo last rating
            </Button>
        </CardFooter>
    </Card>
</template>
