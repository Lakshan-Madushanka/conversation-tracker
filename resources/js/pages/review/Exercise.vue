<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    EyeIcon,
    Loader2Icon,
    AlertCircleIcon,
} from '@lucide/vue';
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
import FocusLayout from '@/layouts/FocusLayout.vue';
import { Text } from '@/components/ui/text';
import { Toaster } from '@/components/ui/sonner';
import RatingButtons from '@/components/RatingButtons.vue';
import { useRating } from '@/composables/useRating';
import { useConfirm } from '@/composables/useConfirm';

defineOptions({ layout: FocusLayout });

// Props passed from parent
const props = defineProps<{
    review: any;
    items: any[];
    currentIndex: number;
    frontText: string;
    backContent: string;
    itemLabel: string;
    hasExample: boolean;
}>();

const emit = defineEmits<{
    rate: [rating: 'hard' | 'okay' | 'easy'];
    undo: [];
    exit: [];
}>();

// State
const index = ref(props.currentIndex);
const revealed = ref(false);
const draft = ref('');
const showingUndo = ref(false);

// Counts
const counts = computed(() => ({
    hard: 0,
    okay: 0,
    easy: 0,
}));

// Progress
const total = computed(() => props.items.length);
const position = computed(() => ({
    current: Math.min(index.value + 1, total.value),
    total: total.value,
}));

// Progress text for display
const progressText = computed(() => `${position.value.current} of ${position.value.total}`);

// Rating counts update when a rating is given
watch(
    [index, revealed],
    () => {
        // Recalculate counts based on given ratings
        // This is simplified - in production we'd track counts from the server
    }
);

// Item currently being shown
const currentItem = computed(() => {
    if (index.value >= props.items.length) {
        return null;
    }
    return props.items[index.value];
});

// Rating status
const { giveRating, undoLastRating, hasPendingRating } = useRating(
    props.review,
    props.items,
    index
);

// Exit confirmation
const { confirm, reset } = useConfirm();

function handleRate(rating: 'hard' | 'okay' | 'easy'): void {
    giveRating(rating);
    revealed.value = false;
    index.value += 1;
}

function handleExit(): void {
    confirm({
        title: 'Leave this review?',
        description: 'Your ratings so far are saved.',
        cancelText: 'Keep reviewing',
        onConfirm: () => {
            emit('exit');
        },
    });
}

function handleUndo(): void {
    undoLastRating();
    revealed.value = true; // show the previous item's answer again
}

// Handle rating given from parent
emit('rate', (rating: 'hard' | 'okay' | 'easy') => {
    // This is called from RatingButtons
});
</script>

<template>
    <FocusLayout
        :progress="100"
        :position="position"
        :exit-href="route('review.index')"
    >
        <Head title="Review session" />

        <h1 class="sr-only">Review session</h1>

        <!-- Show finish screen when all items are done -->
        <Card v-if="isFinished" class="text-center">
            <CardHeader>
                <span
                    class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <CheckCheckIcon class="size-6" />
                </span>
                <CardTitle class="text-xl">Nice work!</CardTitle>
                <CardDescription>
                    You finished today’s review.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-3 gap-2 text-sm mt-4">
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">{{ counts.easy }}</p>
                        <p class="text-muted-foreground">Easy</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">{{ counts.okay }}</p>
                        <p class="text-muted-foreground">Okay</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">{{ counts.hard }}</p>
                        <p class="text-muted-foreground">Hard</p>
                    </div>
                </div>
            </CardContent>
            <CardFooter class="justify-center">
                <Button size="lg" as-child>
                    <Link :href="route('review.index')">Done</Link>
                </Button>
            </CardFooter>
        </Card>

        <!-- Current exercise card -->
        <Card v-else-if="currentItem" class="mt-4">
            <CardHeader>
                <CardDescription>
                    {{ itemLabel }} {{ position.value.current }} of {{ position.value.total }}
                </CardDescription>
                <CardTitle class="text-2xl text-balance">
                    {{ frontText }}
                </CardTitle>
            </CardHeader>

            <CardContent class="space-y-4 pt-6">
                <!-- Flashcard front -->
                <div v-if="!revealed" class="space-y-2">
                    <Badge
                        v-if="itemLabel === 'Word'"
                        variant="secondary"
                        class="text-sm"
                    >
                        Word
                    </Badge>
                    <Badge
                        v-if="itemLabel === 'Phrase'"
                        variant="secondary"
                        class="text-sm"
                    >
                        Phrase
                    </Badge>

                    <div class="prose max-w-none">
                        <p class="text-3xl font-medium line-height-relaxed">
                            {{ frontText }}
                        </p>
                    </div>

                    <Button
                        size="lg"
                        class="w-full"
                        @click="revealed = true"
                        aria-label="Show answer"
                    >
                        <EyeIcon aria-hidden="true" />
                        Show answer
                    </Button>
                </div>

                <!-- Flashcard back (answer revealed) -->
                <div v-else class="space-y-3 rounded-lg border bg-muted/40 p-4">
                    <p class="text-lg font-medium">{{ backContent }}</p>

                    <template v-if="hasExample">
                        <p
                            v-if="backContent.includes('example')"
                            class="text-sm text-muted-foreground"
                        >
                            Example: {{ exampleText }}
                        </p>
                    </template>

                    <SectionLabel tone="natural">Meaning (or use)</SectionLabel>

                    <p v-if="backContent" class="text-base">
                        {{ backContent }}
                    </p>

                    <a
                        v-if="openSessionLink"
                        :href="openSessionLink"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-primary hover:underline flex items-center gap-1"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"
                            />
                        </svg>
                        Open original session
                    </a>
                </div>
            </CardContent>

            <!-- Rating buttons (shown after answer is revealed) -->
            <CardFooter v-if="revealed && !isFinished">
                <RatingButtons @rate="handleRate" />
            </CardFooter>

            <!-- Undo message -->
            <Sonner
                v-if="showingUndo"
                title="Rated {{ lastRating }}. Undo"
                action="Undo"
                description="Undo this rating"
                cancel-label="Keep"
                time-out={5000}
                on-action="undoRating"
            />
        </Card>

        <!-- Empty state when item no longer exists -->
        <Card v-else class="mt-4 text-center">
            <CardHeader>
                <CardTitle class="text-lg">Item not available</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    This item is no longer available (session archived or deleted).
                    The review continues with the next item.
                </p>
            </CardContent>
        </Card>
    </FocusLayout>
</template>

<script>
export default {
    name: 'ReviewExercise',
    props: {
        review: {
            type: Object,
            required: true,
        },
        items: {
            type: Array,
            required: true,
        },
        currentIndex: {
            type: Number,
            required: true,
        },
        frontText: {
            type: String,
            required: true,
        },
        backContent: {
            type: String,
            required: true,
        },
        itemLabel: {
            type: String,
            required: true,
        },
        hasExample: {
            type: Boolean,
            required: true,
        },
    },
    emits: ['rate', 'undo', 'exit'],
    setup(props, { emit }) {
        // ... (state from previous snippet)
        return {};
    },
};
</script>