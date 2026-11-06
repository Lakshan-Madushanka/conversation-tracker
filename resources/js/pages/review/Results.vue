<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, ExternalLinkIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { shortDate } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as reviewIndex } from '@/routes/review';
import type {
    ReviewCard,
    ReviewRating,
    ReviewResultsData,
    ReviewResultsSection,
} from '@/types';

/**
 * One past sitting, read only.
 *
 * The items are the ones the review was fixed with, shown as they read today
 * and with the rating they earned. Nothing here can be rated, taken back or
 * started again, and each item keeps its usual link back to the session it
 * came from.
 */
defineOptions({ layout: AppLayout });

const props = defineProps<{
    review: ReviewResultsData;
    sections: ReviewResultsSection[];
}>();

const ratingLabels: Record<ReviewRating, string> = {
    easy: 'Easy',
    okay: 'Okay',
    hard: 'Hard',
};

/** The whole sitting in named numbers, so no count is carried by colour. */
const summaryCounts = computed(() => [
    { label: ratingLabels.easy, value: props.review.counts.easy },
    { label: ratingLabels.okay, value: props.review.counts.okay },
    { label: ratingLabels.hard, value: props.review.counts.hard },
]);

/** The item in words, however the exercise showed it. */
function itemText(card: ReviewCard): string {
    switch (card.exercise) {
        case 'mistake':
            return card.said;
        case 'say_it_again':
            return card.question_text;
        case 'fill_in_the_blank':
            return card.answer_text;
        default:
            return card.front;
    }
}

function whenLabel(review: ReviewResultsData): string {
    return review.finished_at === null
        ? 'Not finished'
        : shortDate(review.finished_at);
}

function ratedLabel(section: ReviewResultsSection): string {
    return section.rated === section.total
        ? `${section.total} rated`
        : `${section.rated} of ${section.total} rated`;
}

function countsLabel(section: ReviewResultsSection): string {
    return [
        `Easy ${section.counts.easy}`,
        `Okay ${section.counts.okay}`,
        `Hard ${section.counts.hard}`,
    ].join(' · ');
}
</script>

<template>
    <div>
        <Head :title="`Result — ${props.review.type_label}`" />

        <PageHeader
            :title="props.review.type_label"
            description="What this sitting came to. It is here to read, not to rate again."
        >
            <template #actions>
                <Button
                    variant="ghost"
                    size="sm"
                    class="cursor-pointer"
                    as-child
                >
                    <Link :href="reviewIndex.url()">
                        <ArrowLeftIcon aria-hidden="true" />
                        Back to Review
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="mb-6">
            <CardHeader>
                <CardTitle class="flex flex-wrap items-center gap-2 text-base">
                    <span>{{ whenLabel(props.review) }}</span>
                    <Badge variant="secondary">{{
                        props.review.type_label
                    }}</Badge>
                </CardTitle>
                <CardDescription>
                    {{
                        props.review.rated === 1
                            ? '1 item rated'
                            : `${props.review.rated} items rated`
                    }}
                    of {{ props.review.total }} in this sitting.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <dl class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <div
                        v-for="count in summaryCounts"
                        :key="count.label"
                        class="flex items-center gap-1.5"
                    >
                        <dt class="text-muted-foreground">{{ count.label }}</dt>
                        <dd class="font-medium tabular-nums">
                            {{ count.value }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <p
            v-if="props.sections.length === 0"
            class="text-sm text-muted-foreground"
        >
            The items of this sitting are no longer there, so there is nothing
            left to read. Nothing was lost — your saved words are still in
            Collections.
        </p>

        <Card v-for="section in props.sections" :key="section.key" class="mb-4">
            <CardHeader>
                <CardTitle class="text-base">
                    <h2>{{ section.label }}</h2>
                </CardTitle>
                <CardDescription>
                    {{ ratedLabel(section) }} · {{ countsLabel(section) }}
                </CardDescription>
            </CardHeader>

            <CardContent class="grid gap-2">
                <div
                    v-for="item in section.items"
                    :key="`${item.kind}-${item.identity}`"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ itemText(item.card) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.card.label }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                item.rating === null ? 'secondary' : 'outline'
                            "
                        >
                            {{
                                item.rating === null
                                    ? 'Not rated'
                                    : ratingLabels[item.rating]
                            }}
                        </Badge>

                        <Link
                            v-if="item.card.session_url"
                            :href="item.card.session_url"
                            class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <ExternalLinkIcon
                                class="size-4"
                                aria-hidden="true"
                            />
                            Open at item
                        </Link>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
