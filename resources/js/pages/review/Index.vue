<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClockIcon, PlayIcon, SlidersHorizontalIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { recentReviews, todayReview } from '@/lib/sample-data';
import { session as reviewSession } from '@/routes/review';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div>
        <PageHeader
            title="Review"
            description="Re-practise the words, phrases and mistakes you want to keep."
        />

        <!-- Today's suggestion -->
        <Card class="border-primary/30 bg-primary/5">
            <CardHeader>
                <CardDescription>Today’s suggestion</CardDescription>
                <CardTitle class="text-xl">
                    {{ todayReview.minutes }} minutes ·
                    {{ todayReview.items }} items
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    {{ todayReview.mix }}
                </p>
            </CardContent>
            <CardFooter>
                <Button size="lg" as-child>
                    <Link :href="reviewSession.url()">
                        <PlayIcon aria-hidden="true" />
                        Start review
                    </Link>
                </Button>
            </CardFooter>
        </Card>

        <!-- Custom review -->
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <SlidersHorizontalIcon
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Custom review
                    </CardTitle>
                    <CardDescription>
                        Choose a topic, a collection, or starred items only.
                    </CardDescription>
                </CardHeader>
                <CardFooter>
                    <Button variant="outline" as-child>
                        <Link :href="reviewSession.url()">
                            Build a custom set
                        </Link>
                    </Button>
                </CardFooter>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ClockIcon
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Recent reviews
                    </CardTitle>
                    <CardDescription>
                        Your last few sessions at a glance.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2">
                        <li
                            v-for="review in recentReviews"
                            :key="review.date"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span>{{ review.date }}</span>
                            <span class="flex items-center gap-2">
                                <span class="text-muted-foreground">{{
                                    review.counts
                                }}</span>
                                <Badge variant="secondary">{{
                                    review.result
                                }}</Badge>
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
