<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    FlameIcon,
    SquarePenIcon,
    TrophyIcon,
} from '@lucide/vue';
import ContinueSessionCard from '@/components/ContinueSessionCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReviewSuggestionCard from '@/components/ReviewSuggestionCard.vue';
import SessionCard from '@/components/SessionCard.vue';
import StatTile from '@/components/StatTile.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    create as sessionsCreate,
    index as sessionsIndex,
} from '@/routes/sessions';
import type {
    ContinueCardData,
    ProgressNumbers,
    SessionCardData,
    TodayReviewData,
} from '@/types';

defineOptions({ layout: AppLayout });

withDefaults(
    defineProps<{
        /** Session-derived streak and practice-day totals. */
        progress?: ProgressNumbers;
        /** The most recent unfinished session, if there is one. */
        unfinishedSession?: ContinueCardData | null;
        /** The most recent finished sessions, newest first. */
        recentSessions?: SessionCardData[];
        /** Today's review suggestion, or the "You've reviewed today" state. */
        todayReview?: TodayReviewData;
    }>(),
    {
        progress: () => ({
            words: 0,
            phrases: 0,
            mistakes: 0,
            grammar_notes: 0,
            practice_days: 0,
            current_streak: 0,
            sessions: 0,
        }),
        recentSessions: () => [],
        todayReview: () => ({
            state: 'nothing',
            count: null,
            minutes: null,
            mix: null,
            canReviewMore: null,
        }),
    },
);
</script>

<template>
    <div>
        <Head title="Home" />

        <PageHeader
            title="Ready for today’s practice?"
            description="A few minutes a day is enough to keep your streak going."
        >
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="sessionsCreate.url()">
                        <SquarePenIcon aria-hidden="true" />
                        New session
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <ContinueSessionCard
            v-if="unfinishedSession"
            class="mb-6"
            :session="unfinishedSession"
        />

        <!-- Today's review suggestion -->
        <ReviewSuggestionCard :suggestion="todayReview" />

        <!-- Streak + practice days -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4">
            <StatTile
                label="Current streak"
                :value="`${progress.current_streak} ${progress.current_streak === 1 ? 'day' : 'days'}`"
                :icon="FlameIcon"
                :hint="
                    progress.current_streak === 0
                        ? 'Complete a conversation to begin'
                        : 'Keep it going!'
                "
            />
            <StatTile
                label="Total practice days"
                :value="progress.practice_days"
                :icon="TrophyIcon"
                hint="Days with a complete conversation"
            />
        </div>

        <!-- Recent sessions: the link to the Library is always here, so the
            full list is one step away even before anything is finished. -->
        <div class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Recent sessions</h2>
                <Link
                    :href="sessionsIndex.url()"
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    View all sessions
                    <ArrowRightIcon class="size-3.5" aria-hidden="true" />
                </Link>
            </div>

            <div
                v-if="recentSessions && recentSessions.length > 0"
                class="grid gap-3"
            >
                <SessionCard
                    v-for="session in recentSessions"
                    :key="session.id"
                    :session="session"
                    :show-menu="false"
                />
            </div>

            <p v-else class="text-sm text-muted-foreground">
                Finished sessions will appear here once you complete one.
            </p>
        </div>
    </div>
</template>
