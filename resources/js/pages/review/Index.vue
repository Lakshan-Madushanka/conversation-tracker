<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    InfoIcon,
    LightbulbIcon,
    ListChecksIcon,
    SlidersHorizontalIcon,
} from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import ReviewRecentResults from '@/components/ReviewRecentResults.vue';
import ReviewRulesDialog from '@/components/review/ReviewRulesDialog.vue';
import ReviewSuggestionCard from '@/components/ReviewSuggestionCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as collectionsIndex } from '@/routes/collections';
import { index as customReviewsIndex } from '@/routes/customReviews';
import { custom as customReview } from '@/routes/review';
import type {
    GrammarNotesTipData,
    RecentReviewData,
    ReviewRulesData,
    TodayReviewData,
    WaitingForFixData,
} from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** Today's review suggestion, or the "You've reviewed today" state. */
    todayReview: TodayReviewData;
    /** How many words, phrases and mistakes cannot be practiced yet. */
    waitingForFix: WaitingForFixData;
    /** How many grammar notes cannot make a blank yet. */
    grammarNotesTip: GrammarNotesTipData;
    /** The last few sittings and what their ratings came to. */
    recentReviews: RecentReviewData[];
    /** The rules the info button explains, as the app runs them. */
    reviewRules: ReviewRulesData;
}>();

const waitingGroups = computed(() =>
    [
        {
            count: props.waitingForFix.vocabulary,
            singular: 'word',
            plural: 'words',
            link: collectionsIndex.url({
                query: { tab: 'vocabulary', attention: '1' },
            }),
            linkLabel: 'Vocabulary: Needs attention only',
        },
        {
            count: props.waitingForFix.phrases,
            singular: 'phrase',
            plural: 'phrases',
            link: collectionsIndex.url({
                query: { tab: 'phrases', attention: '1' },
            }),
            linkLabel: 'Phrases: Needs attention only',
        },
        {
            count: props.waitingForFix.mistakes,
            singular: 'mistake',
            plural: 'mistakes',
            link: collectionsIndex.url({
                query: { tab: 'mistakes', attention: '1' },
            }),
            linkLabel: 'My mistakes: Needs attention only',
        },
    ].filter((group) => group.count > 0),
);

const waitingCount = computed(() =>
    waitingGroups.value.reduce((total, group) => total + group.count, 0),
);

/** The sentence, with correct grammar for each group and for the total. */
const waitingSentence = computed(() => {
    const parts = waitingGroups.value.map(
        (group) =>
            `${group.count} ${group.count === 1 ? group.singular : group.plural}`,
    );

    const list =
        parts.length <= 1
            ? (parts[0] ?? '')
            : parts.length === 2
              ? `${parts[0]} and ${parts[1]}`
              : `${parts.slice(0, -1).join(', ')}, and ${parts.at(-1)}`;

    return `${list} ${waitingCount.value === 1 ? 'needs' : 'need'} attention before ${waitingCount.value === 1 ? 'it' : 'they'} can be practiced`;
});

/** The grammar-note nudge, with the right number wording. */
const grammarNotesSentence = computed(() =>
    props.grammarNotesTip.count === 1
        ? '1 grammar note cannot be used yet.'
        : `${props.grammarNotesTip.count} grammar notes cannot be used yet.`,
);

const grammarNotesLink = computed(() =>
    collectionsIndex.url({ query: { tab: 'grammar' } }),
);
</script>

<template>
    <div>
        <Head title="Review" />

        <PageHeader
            title="Review"
            description="A calm few minutes with the words, phrases and mistakes you want to keep."
        >
            <template #actions>
                <ReviewRulesDialog :rules="props.reviewRules" />
            </template>
        </PageHeader>

        <ReviewSuggestionCard :suggestion="props.todayReview" />

        <!-- Choosing what to review is its own page, never a saved profile.
             The saved custom reviews the learner keeps live on their own page. -->
        <div class="mt-4 flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="lg"
                class="h-11 cursor-pointer"
                as-child
            >
                <Link :href="customReview.url()">
                    <SlidersHorizontalIcon aria-hidden="true" />
                    Custom review
                </Link>
            </Button>

            <Button
                variant="outline"
                size="lg"
                class="h-11 cursor-pointer"
                as-child
            >
                <Link :href="customReviewsIndex.url()">
                    <ListChecksIcon aria-hidden="true" />
                    Custom reviews
                </Link>
            </Button>
        </div>

        <ReviewRecentResults :reviews="props.recentReviews" />

        <!-- Items waiting for a fix, by the same shared rule as the collections -->
        <Alert v-if="waitingCount > 0" class="mt-4">
            <InfoIcon aria-hidden="true" />
            <AlertTitle>
                <h2 class="font-medium">Waiting for a fix</h2>
            </AlertTitle>
            <AlertDescription>
                <p>{{ waitingSentence }} — you can fix that in Collections.</p>
                <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                    <Link
                        v-for="group in waitingGroups"
                        :key="group.linkLabel"
                        :href="group.link"
                        class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        {{ group.linkLabel }}
                    </Link>
                </p>
            </AlertDescription>
        </Alert>

        <!-- Grammar notes with nothing to blank yet, kept separate on purpose -->
        <Alert v-if="props.grammarNotesTip.count > 0" class="mt-4">
            <LightbulbIcon aria-hidden="true" />
            <AlertTitle>
                <h2 class="font-medium">{{ grammarNotesSentence }}</h2>
            </AlertTitle>
            <AlertDescription>
                <p>Bold the key part of a note to turn it into an exercise.</p>
                <p class="mt-1">
                    <Link
                        :href="grammarNotesLink"
                        class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        Grammar notes in Collections
                    </Link>
                </p>
            </AlertDescription>
        </Alert>
    </div>
</template>
