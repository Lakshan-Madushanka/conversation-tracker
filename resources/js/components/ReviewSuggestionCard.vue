<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    CheckCheckIcon,
    GraduationCapIcon,
    PlayIcon,
    SquarePenIcon,
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
import { store as reviewStore } from '@/routes/review';
import { create as sessionsCreate } from '@/routes/sessions';
import type { TodayReviewData } from '@/types';

const props = defineProps<{
    /** The suggestion card's state, worked out on the server. */
    suggestion: TodayReviewData;
}>();

const dailyForm = useForm({ daily: true });
const moreForm = useForm({ more: true });

const estimate = computed(() => {
    const minutes = props.suggestion.minutes ?? 1;
    const minutesLabel =
        minutes === 1 ? 'About 1 minute' : `About ${minutes} minutes`;
    const count = props.suggestion.count ?? 0;
    const itemsLabel = count === 1 ? '1 item' : `${count} items`;

    return `${minutesLabel} · ${itemsLabel}`;
});

function startDailyReview(): void {
    dailyForm.post(reviewStore.url());
}

function reviewABitMore(): void {
    moreForm.post(reviewStore.url());
}
</script>

<template>
    <!-- Something to review today -->
    <Card
        v-if="props.suggestion.state === 'suggest'"
        class="border-primary/30 bg-primary/5"
    >
        <CardHeader>
            <span
                class="mb-1 flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary"
                aria-hidden="true"
            >
                <GraduationCapIcon class="size-5" />
            </span>

            <CardTitle class="text-xl font-semibold">
                <h2>Today's review is ready</h2>
            </CardTitle>
            <CardDescription>
                A short, calm few minutes. There is no timer and no score.
            </CardDescription>
        </CardHeader>

        <CardContent
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="text-sm text-muted-foreground">
                <p class="font-medium text-foreground">{{ estimate }}</p>
                <p v-if="props.suggestion.mix" class="mt-1">
                    <Badge variant="secondary">{{
                        props.suggestion.mix
                    }}</Badge>
                </p>
            </div>

            <Button
                size="lg"
                class="h-11 min-h-11"
                :disabled="dailyForm.processing"
                @click="startDailyReview"
            >
                <PlayIcon aria-hidden="true" />
                Start review
            </Button>
        </CardContent>
    </Card>

    <!-- The day's review is complete -->
    <Card
        v-else-if="props.suggestion.state === 'done'"
        class="border-primary/30 bg-primary/5"
    >
        <CardHeader>
            <span
                class="mb-1 flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary"
                aria-hidden="true"
            >
                <CheckCheckIcon class="size-5" />
            </span>

            <CardTitle class="text-xl font-semibold">
                <h2>You've reviewed today</h2>
            </CardTitle>
            <CardDescription>
                Nice work. A few minutes today keeps it fresh — see you
                tomorrow.
            </CardDescription>
        </CardHeader>

        <CardFooter
            v-if="props.suggestion.canReviewMore"
            class="flex flex-wrap gap-2"
        >
            <Button
                size="lg"
                variant="outline"
                class="h-11"
                :disabled="moreForm.processing"
                @click="reviewABitMore"
            >
                Review a bit more
            </Button>
        </CardFooter>

        <CardContent v-else>
            <p class="text-sm text-muted-foreground">
                Nothing more needs review right now. Come back tomorrow, or save
                a new session.
            </p>
        </CardContent>
    </Card>

    <!-- Nothing worth reviewing at this moment -->
    <Card
        v-else-if="props.suggestion.state === 'nothing'"
        class="border-dashed bg-card/50"
    >
        <CardHeader>
            <span
                class="mb-1 flex size-11 items-center justify-center rounded-full bg-muted"
                aria-hidden="true"
            >
                <CheckCheckIcon class="size-5 text-muted-foreground" />
            </span>

            <CardTitle class="text-xl font-semibold">
                <h2>Nothing needs review right now</h2>
            </CardTitle>
            <CardDescription>
                Come back tomorrow, or save a new session.
            </CardDescription>
        </CardHeader>

        <CardFooter>
            <Button size="lg" variant="outline" as-child class="h-11">
                <Link :href="sessionsCreate.url()">
                    <SquarePenIcon aria-hidden="true" />
                    Start a session
                </Link>
            </Button>
        </CardFooter>
    </Card>

    <!-- No finished sessions yet: Review fills up after the first one -->
    <Card v-else class="border-dashed bg-card/50">
        <CardHeader>
            <span
                class="mb-1 flex size-11 items-center justify-center rounded-full bg-muted"
                aria-hidden="true"
            >
                <GraduationCapIcon class="size-5 text-muted-foreground" />
            </span>

            <CardTitle class="text-xl font-semibold">
                <h2>Review fills up after your first session</h2>
            </CardTitle>
            <CardDescription>
                Finish a practice session and the words, phrases and mistakes
                you save will show up here as exercises.
            </CardDescription>
        </CardHeader>

        <CardFooter>
            <Button size="lg" as-child class="h-11">
                <Link :href="sessionsCreate.url()">
                    <SquarePenIcon aria-hidden="true" />
                    Start a session
                </Link>
            </Button>
        </CardFooter>
    </Card>
</template>
