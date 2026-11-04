<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { InfoIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import ReviewSuggestionCard from '@/components/ReviewSuggestionCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as collectionsIndex } from '@/routes/collections';
import type { TodayReviewData, WaitingForFixData } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** Today's review suggestion, or the "You've reviewed today" state. */
    todayReview: TodayReviewData;
    /** How many words and phrases cannot be practiced yet. */
    waitingForFix: WaitingForFixData;
}>();

const wordsLink = collectionsIndex.url({
    query: { tab: 'vocabulary', attention: '1' },
});
const phrasesLink = collectionsIndex.url({
    query: { tab: 'phrases', attention: '1' },
});

const waitingCount = computed(
    () => props.waitingForFix.vocabulary + props.waitingForFix.phrases,
);

/** The tail of the sentence, with correct grammar for a group of one. */
const waitingVerb = computed(() => {
    const total = waitingCount.value;

    if (total === 1) {
        return 'needs a meaning (or a use) before it can be practiced';
    }

    return 'need a meaning (or a use) before they can be practiced';
});
</script>

<template>
    <div>
        <Head title="Review" />

        <PageHeader
            title="Review"
            description="A calm few minutes with the words and phrases you want to keep."
        />

        <ReviewSuggestionCard :suggestion="props.todayReview" />

        <!-- Items waiting for a fix, by the same shared rule as the collections -->
        <Alert v-if="waitingCount > 0" class="mt-4">
            <InfoIcon aria-hidden="true" />
            <AlertTitle>
                <h2 class="font-medium">Waiting for a fix</h2>
            </AlertTitle>
            <AlertDescription>
                <p class="flex flex-wrap items-center gap-x-1.5">
                    <template v-if="props.waitingForFix.vocabulary > 0">
                        <Badge variant="secondary">{{
                            props.waitingForFix.vocabulary
                        }}</Badge>
                        {{
                            props.waitingForFix.vocabulary === 1
                                ? 'word'
                                : 'words'
                        }}
                    </template>
                    <template
                        v-if="
                            props.waitingForFix.vocabulary > 0 &&
                            props.waitingForFix.phrases > 0
                        "
                    >
                        and
                    </template>
                    <template v-if="props.waitingForFix.phrases > 0">
                        <Badge variant="secondary">{{
                            props.waitingForFix.phrases
                        }}</Badge>
                        {{
                            props.waitingForFix.phrases === 1
                                ? 'phrase'
                                : 'phrases'
                        }}
                    </template>
                    {{ waitingVerb }} — you can fix that in Collections.
                </p>
                <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                    <Link
                        v-if="props.waitingForFix.vocabulary > 0"
                        :href="wordsLink"
                        class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        Vocabulary: Needs attention only
                    </Link>
                    <Link
                        v-if="props.waitingForFix.phrases > 0"
                        :href="phrasesLink"
                        class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        Phrases: Needs attention only
                    </Link>
                </p>
            </AlertDescription>
        </Alert>
    </div>
</template>
