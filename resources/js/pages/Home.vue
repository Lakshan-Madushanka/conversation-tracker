<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    FlameIcon,
    PlayIcon,
    SquarePenIcon,
    TrophyIcon,
} from '@lucide/vue';
import ContinueSessionCard from '@/components/ContinueSessionCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import FinishedSessionCard from '@/components/FinishedSessionCard.vue';
import StatTile from '@/components/StatTile.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { progressStats, todayReview } from '@/lib/sample-data';
import { index as reviewSession } from '@/routes/review';
import {
    create as sessionsCreate,
    index as sessionsIndex,
} from '@/routes/sessions';
import type { ContinueCardData, FinishedSessionData } from '@/types';

defineOptions({ layout: AppLayout });

withDefaults(
    defineProps<{
        /** The most recent unfinished session, if there is one. */
        unfinishedSession?: ContinueCardData | null;
        /** The most recent finished sessions, newest first. */
        recentSessions?: FinishedSessionData[];
    }>(),
    { recentSessions: () => [] },
);

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
});
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
        <Card class="border-primary/30 bg-primary/5">
            <CardHeader>
                <CardDescription>{{ greeting }}</CardDescription>
                <CardTitle class="text-xl">
                    A short review is ready for you
                </CardTitle>
            </CardHeader>
            <CardContent
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-sm text-muted-foreground">
                    <p class="font-medium text-foreground">
                        {{ todayReview.minutes }} minutes ·
                        {{ todayReview.items }} items
                    </p>
                    <p class="mt-1">{{ todayReview.mix }}</p>
                </div>

                <Button size="lg" as-child>
                    <Link :href="reviewSession.url()">
                        <PlayIcon aria-hidden="true" />
                        Start review
                    </Link>
                </Button>
            </CardContent>
        </Card>

        <!-- Streak + practice days -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4">
            <StatTile
                label="Current streak"
                :value="`${progressStats.streak} days`"
                :icon="FlameIcon"
                hint="Keep it going!"
            />
            <StatTile
                label="Total practice days"
                :value="progressStats.practiceDays"
                :icon="TrophyIcon"
                hint="Since you started"
            />
        </div>

        <!-- Recent sessions: only once there is something to show. -->
        <div v-if="recentSessions && recentSessions.length > 0" class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Recent sessions</h2>
                <Link
                    :href="sessionsIndex.url()"
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    View all
                    <ArrowRightIcon class="size-3.5" aria-hidden="true" />
                </Link>
            </div>

            <div class="grid gap-3">
                <FinishedSessionCard
                    v-for="session in recentSessions"
                    :key="session.id"
                    :session="session"
                />
            </div>
        </div>
    </div>
</template>
