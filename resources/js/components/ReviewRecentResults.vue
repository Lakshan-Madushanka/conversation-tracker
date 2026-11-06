<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { HistoryIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { shortDate } from '@/lib/format';
import type { RecentReviewData } from '@/types';

/**
 * The last few sittings, newest first, exactly as their saved ratings came out.
 *
 * A sitting with nothing rated is not here, an undone rating is gone, and a
 * re-rated item counts once. Every count is read as words, so nothing depends
 * on colour.
 */
const props = defineProps<{
    reviews: RecentReviewData[];
}>();

const hasResults = computed(() => props.reviews.length > 0);

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
    return `Open ${review.type_label} from ${shortDate(review.finished_at)} — ${ratedLabel(review)}, ${countsLabel(review)}`;
}
</script>

<template>
    <section class="mt-6" aria-labelledby="recent-results">
        <h2
            id="recent-results"
            class="mb-3 flex items-center gap-2 text-lg font-semibold"
        >
            <HistoryIcon
                class="size-4 text-muted-foreground"
                aria-hidden="true"
            />
            Recent results
        </h2>

        <p v-if="!hasResults" class="text-sm text-muted-foreground">
            Your results appear here after your first review. Every sitting you
            finish, however short, will show what it came to.
        </p>

        <!-- Stacked cards on a phone -->
        <div v-if="hasResults" class="grid gap-3 md:hidden">
            <Link
                v-for="review in props.reviews"
                :key="review.id"
                :href="review.url"
                :aria-label="openLabel(review)"
                class="block focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <Card class="transition-colors hover:border-primary/40">
                    <CardHeader>
                        <CardTitle
                            class="flex flex-wrap items-center gap-2 text-base"
                        >
                            <span>{{ shortDate(review.finished_at) }}</span>
                            <Badge variant="secondary">{{
                                review.type_label
                            }}</Badge>
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        <p class="text-sm font-medium">
                            {{ ratedLabel(review) }}
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ countsLabel(review) }}
                        </p>
                    </CardContent>
                </Card>
            </Link>
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
                            :key="review.id"
                        >
                            <TableCell>
                                <Link
                                    :href="review.url"
                                    :aria-label="openLabel(review)"
                                    class="font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    {{ shortDate(review.finished_at) }}
                                </Link>
                            </TableCell>
                            <TableCell>{{ review.type_label }}</TableCell>
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
