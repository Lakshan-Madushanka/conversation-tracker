<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpenIcon,
    CalendarIcon,
    FlameIcon,
    GraduationCapIcon,
    MessageSquareIcon,
    TypeIcon,
} from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { create as sessionsCreate } from '@/routes/sessions';
import type { ProgressNumbers } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    progress: ProgressNumbers;
}>();

function dayCount(value: number): string {
    return `${value} ${value === 1 ? 'day' : 'days'}`;
}

const streakHint = computed(() =>
    props.progress.current_streak === 0
        ? 'Complete a conversation to begin'
        : 'Practice days in a row',
);
</script>

<template>
    <div>
        <Head title="Progress" />

        <PageHeader
            title="Progress"
            description="A gentle look at how your practice is adding up."
        />

        <h2 class="mb-3 text-sm font-semibold">Practice</h2>
        <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <StatTile
                label="Current streak"
                :value="dayCount(progress.current_streak)"
                :icon="FlameIcon"
                :hint="streakHint"
            />
            <StatTile
                label="Total practice days"
                :value="progress.practice_days"
                :icon="CalendarIcon"
                hint="Days with a complete conversation"
            />
        </div>

        <h2 class="mt-6 mb-3 text-sm font-semibold">Collected in sessions</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <StatTile
                label="Sessions"
                :value="progress.sessions"
                :icon="MessageSquareIcon"
                hint="Finished, archived included"
            />
            <StatTile label="Words" :value="progress.words" :icon="TypeIcon" />
            <StatTile
                label="Phrases"
                :value="progress.phrases"
                :icon="BookOpenIcon"
            />
            <StatTile
                label="Grammar"
                :value="progress.grammar_notes"
                :icon="GraduationCapIcon"
            />
        </div>

        <Card
            v-if="progress.sessions === 0 && progress.practice_days === 0"
            class="mt-6"
        >
            <CardHeader>
                <CardDescription>Your progress starts here</CardDescription>
                <CardTitle class="text-base">
                    Complete a conversation to see your progress grow
                </CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <p class="text-sm text-muted-foreground">
                    Your streak and practice days appear once a question has an
                    answer and a natural version, even before you finish the
                    session. Collected words appear after you finish it.
                    Notebook entries are saved separately in Notebooks and are
                    not practice conversations.
                </p>
                <Button class="w-fit" as-child>
                    <Link :href="sessionsCreate.url()">Start a session</Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
