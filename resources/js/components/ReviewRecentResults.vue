<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { HistoryIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { shortDate } from '@/lib/format';
import type { RecentReviewData, RecentReviewKind } from '@/types';

/**
 * The last few results, newest first, exactly as their saved ratings came out.
 *
 * A daily sitting, a custom sitting and a finished attempt share the list; an
 * attempt also names the review it belonged to. A result whose items all came
 * from one notebook keeps a link back to it, so the learner can tell where the
 * words were saved from.
 *
 * A result with nothing rated is not here, an undone rating is gone, and a
 * re-rated item counts once. Every count is read as words, so nothing depends
 * on colour.
 */
const props = withDefaults(
    defineProps<{
        reviews: RecentReviewData[];
        /** Which kinds the list shows. */
        kind?: RecentReviewKind;
    }>(),
    { kind: 'all' },
);

const emit = defineEmits<{
    'update:kind': [kind: RecentReviewKind];
}>();

const hasResults = computed(() => props.reviews.length > 0);

const emptyCopy = computed(() => {
    switch (props.kind) {
        case 'daily':
            return 'No daily reviews yet. A sitting you finish from today’s suggestion will show up here.';
        case 'custom':
            return 'No custom reviews yet. Practice a saved review and its result will show up here.';
        default:
            return 'Your results appear here after your first review. Every sitting you finish, however short, will show what it came to.';
    }
});

function onKindChange(value: unknown): void {
    emit(
        'update:kind',
        value === 'daily' || value === 'custom' ? value : 'all',
    );
}

function ratedLabel(review: RecentReviewData): string {
    return review.rated === 1 ? '1 item rated' : `${review.rated} items rated`;
}

/** "Easy 4 · Okay 2 · Hard 1", with every number named. */
function countsLabel(review: RecentReviewData): string {
    return [
        `Easy ${review.counts.easy}`,
        `Okay ${review.counts.okay}`,
        `Hard ${review.counts.hard}`,
    ].join(' · ');
}

function openLabel(review: RecentReviewData): string {
    return `Open ${review.title ?? review.type_label} from ${shortDate(review.finished_at)} — ${ratedLabel(review)}, ${countsLabel(review)}`;
}
</script>

<template>
    <section class="mt-6" aria-labelledby="recent-results">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2
                id="recent-results"
                class="flex items-center gap-2 text-lg font-semibold"
            >
                <HistoryIcon
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                Recent results
            </h2>

            <div class="flex items-center gap-2">
                <label
                    for="recent-results-kind"
                    class="text-sm text-muted-foreground"
                >
                    Show
                </label>
                <NativeSelect
                    id="recent-results-kind"
                    :model-value="props.kind"
                    class="w-40"
                    @update:model-value="onKindChange"
                >
                    <NativeSelectOption value="all">
                        All results
                    </NativeSelectOption>
                    <NativeSelectOption value="daily">
                        Daily reviews
                    </NativeSelectOption>
                    <NativeSelectOption value="custom">
                        Custom reviews
                    </NativeSelectOption>
                </NativeSelect>
            </div>
        </div>

        <p v-if="!hasResults" class="text-sm text-muted-foreground">
            {{ emptyCopy }}
        </p>

        <!-- Stacked cards on a phone -->
        <div v-if="hasResults" class="grid gap-3 md:hidden">
            <Card v-for="review in props.reviews" :key="review.key">
                <Link
                    :href="review.url"
                    :aria-label="openLabel(review)"
                    class="block focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <CardHeader>
                        <CardTitle
                            class="flex flex-wrap items-center gap-2 text-base"
                        >
                            <span>{{ shortDate(review.finished_at) }}</span>
                            <Badge variant="secondary">{{
                                review.type_label
                            }}</Badge>
                        </CardTitle>
                        <CardDescription v-if="review.title">{{
                            review.title
                        }}</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <p class="text-sm font-medium">
                            {{ ratedLabel(review) }}
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ countsLabel(review) }}
                        </p>
                    </CardContent>
                </Link>

                <CardFooter
                    v-if="review.source"
                    class="text-sm text-muted-foreground"
                >
                    From
                    <Link
                        :href="review.source.url"
                        :aria-label="`Open notebook ${review.source.label}`"
                        class="ml-1 font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        {{ review.source.label }}
                    </Link>
                </CardFooter>
            </Card>
        </div>

        <!-- The same results as a table on a wide screen -->
        <Card v-if="hasResults" class="hidden md:block">
            <CardContent class="overflow-hidden p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>When</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Rated</TableHead>
                            <TableHead>Easy</TableHead>
                            <TableHead>Okay</TableHead>
                            <TableHead>Hard</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow
                            v-for="review in props.reviews"
                            :key="review.key"
                        >
                            <TableCell>
                                <Link
                                    :href="review.url"
                                    :aria-label="openLabel(review)"
                                    class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    {{ shortDate(review.finished_at) }}
                                </Link>
                                <span
                                    v-if="review.source"
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    From
                                    <Link
                                        :href="review.source.url"
                                        :aria-label="`Open notebook ${review.source.label}`"
                                        class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        {{ review.source.label }}
                                    </Link>
                                </span>
                            </TableCell>
                            <TableCell>
                                <span class="block">{{
                                    review.type_label
                                }}</span>
                                <span
                                    v-if="review.title"
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    {{ review.title }}
                                </span>
                            </TableCell>
                            <TableCell class="tabular-nums">
                                {{ review.rated }}
                            </TableCell>
                            <TableCell class="tabular-nums">
                                {{ review.counts.easy }}
                            </TableCell>
                            <TableCell class="tabular-nums">
                                {{ review.counts.okay }}
                            </TableCell>
                            <TableCell class="tabular-nums">
                                {{ review.counts.hard }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </section>
</template>
