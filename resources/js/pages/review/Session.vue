<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCheckIcon, EyeIcon } from '@lucide/vue';
import type { ReviewRating } from '@/components/RatingButtons.vue';
import RatingButtons from '@/components/RatingButtons.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import FocusLayout from '@/layouts/FocusLayout.vue';
import type { ReviewType } from '@/lib/sample-data';
import { reviewItems } from '@/lib/sample-data';
import { index as reviewIndex } from '@/routes/review';

const total = reviewItems.length;
const index = ref(0);
const revealed = ref(false);
const draft = ref('');

const counts = reactive({ hard: 0, okay: 0, easy: 0 });

const finished = computed(() => index.value >= total);
const current = computed(() => reviewItems[index.value]);

const progress = computed(() => Math.round((index.value / total) * 100));

const position = computed(() => ({
    current: Math.min(index.value + 1, total),
    total,
}));

const typeLabel: Record<ReviewType, string> = {
    flashcard: 'Flashcard',
    fix: 'Fix the mistake',
    'say-again': 'Say it again',
    'fill-blank': 'Fill in the blank',
};

function rate(rating: ReviewRating): void {
    counts[rating] += 1;
    revealed.value = false;
    draft.value = '';
    index.value += 1;
}
</script>

<template>
    <FocusLayout
        :progress="progress"
        :position="position"
        :exit-href="reviewIndex.url()"
    >
        <Head title="Review session" />

        <h1 class="sr-only">Review session</h1>

        <!-- Summary shown once every item is done -->
        <Card v-if="finished" class="text-center">
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
                <div class="grid grid-cols-3 gap-2 text-sm">
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ counts.easy }}
                        </p>
                        <p class="text-muted-foreground">Easy</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ counts.okay }}
                        </p>
                        <p class="text-muted-foreground">Okay</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ counts.hard }}
                        </p>
                        <p class="text-muted-foreground">Hard</p>
                    </div>
                </div>
            </CardContent>
            <CardFooter class="justify-center">
                <Button size="lg" as-child>
                    <Link :href="reviewIndex.url()">Done</Link>
                </Button>
            </CardFooter>
        </Card>

        <!-- Current exercise -->
        <Card v-else-if="current">
            <CardHeader>
                <CardDescription>
                    {{ typeLabel[current.type] }}
                </CardDescription>
                <CardTitle class="text-2xl text-balance">
                    {{ current.prompt }}
                </CardTitle>
            </CardHeader>

            <CardContent class="space-y-4">
                <Textarea
                    v-if="current.type === 'fix' && !revealed"
                    v-model="draft"
                    placeholder="Type the corrected version…"
                    aria-label="Your correction"
                />

                <Button
                    v-if="!revealed"
                    size="lg"
                    class="w-full"
                    @click="revealed = true"
                >
                    <EyeIcon aria-hidden="true" />
                    Show answer
                </Button>

                <div v-else class="space-y-3 rounded-lg border bg-muted/40 p-4">
                    <p class="text-lg font-medium">{{ current.answer }}</p>
                    <p
                        v-if="current.explanation"
                        class="text-sm text-muted-foreground"
                    >
                        {{ current.explanation }}
                    </p>
                    <SectionLabel tone="natural">Natural answer</SectionLabel>
                </div>
            </CardContent>

            <CardFooter v-if="revealed">
                <RatingButtons @rate="rate" />
            </CardFooter>
        </Card>
    </FocusLayout>
</template>
