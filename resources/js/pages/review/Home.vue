<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useLink } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    PlayIcon,
    SlidersHorizontalIcon,
    ExitIcon,
    CheckCheckIcon,
    Loader2Icon,
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
import AppLayout from '@/layouts/AppLayout.vue';
import { useReview } from '@/composables/useReview';
import { progressStats, todayReview } from '@/lib/sample-data';
import { useStartReview } from '@/composables/useStartReview';
import { useConfirm } from '@/composables/useConfirm';

defineOptions({ layout: AppLayout });

const { hasFinishedSessions, reviewableCount, reviewableMessage, canStartReview } = useReview();
const { confirm } = useConfirm();
const { startReview, reviewData } = useStartReview();
</script>

<template>
    <div>
        <Head title="Review" />

        <PageHeader
            title="Review"
            description="Re-practice the words, phrases and mistakes you want to keep."
        />

        <!-- Today's suggestion -->
        <Card class="border-primary/30 bg-primary/5 mb-6">
            <CardHeader>
                <CardDescription>Today's suggestion</CardDescription>
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

        <!-- Review Home card -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle class="text-2xl">Review your saved words and phrases</CardTitle>
                <CardDescription>
                    {{ reviewableCount }} ready to review
                    {{ reviewableMessage }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <template v-if="canStartReview">
                    <Button size="lg" block @click="startReviewNow">
                        <PlayIcon aria-hidden="true" />
                        Start review
                    </Button>
                </template>

                <template v-else>
                    <p v-if="!hasFinishedSessions" class="text-sm text-muted-foreground mb-4">
                        Finish a practice session to start reviewing your words and phrases.
                    </p>
                    <p v-else class="text-sm text-muted-foreground mb-4">
                        Words and phrases need a meaning (or use) before they can be practiced.
                    </p>
                    <Button size="lg" block secondary @click="startNewSession">
                        <SquarePenIcon aria-hidden="true" />
                        Start a new session
                    </Button>
                </template>
            </CardContent>
        </Card>

        <!-- No reviewable items message -->
        <template v-else>
            <Card class="mt-6 border-secondary/30 bg-secondary/5">
                <CardHeader>
                    <CardTitle class="text-lg">Words and phrases need a meaning first</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        Words and phrases need a meaning (or use) before they can be practiced.
                        <a href="/collections" class="font-medium text-primary hover:underline">
                            Go to Vocabulary collection
                        </a> to add meanings and examples.
                    </p>
                </CardContent>
            </Card>
        </template>
    </div>
</template>

<script>
export default {
    name: 'ReviewHome',
    setup() {
        return useReview();
    },
    methods: {
        startReviewNow() {
            confirm({
                title: 'Start review?',
                description: 'Begin a review of your ready words and phrases.',
                cancelText: 'Keep browsing',
                onConfirm: () => {
                    startReview(1); // session_id would come from auth in production
                },
            });
        },
        startNewSession() {
            // In a full Inertia app, this would use Inertia.visit
            // For now, navigate traditionally
            window.location.href = '/sessions/create';
        },
    },
};
</script>