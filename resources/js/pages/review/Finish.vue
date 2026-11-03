<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCheckIcon,
    PlayIcon,
    SlidersHorizontalIcon,
    ThumbsUpIcon,
    CircleXIcon,
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
import { useConfirm } from '@/composables/useConfirm';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    review: any;
    totalItems: number;
    hard: number;
    okay: number;
    easy: number;
}>();

const { confirm } = useConfirm();
</script>

<template>
    <div>
        <Head title="Review finished" />

        <PageHeader
            title="Review finished"
            description="Great job completing your review!"
        />

        <!-- Summary card -->
        <Card class="mt-6 text-center">
            <CardHeader>
                <span
                    class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <CheckCheckIcon class="size-6" />
                </span>
                <CardTitle class="text-3xl">Well done!</CardTitle>
            </CardHeader>

            <CardContent>
                <p class="text-2xl text-muted-foreground mt-4">
                    You reviewed {{ totalItems }} item{{ totalItems !== 1 ? 's' : '' }}
                </p>

                <div class="grid grid-cols-3 gap-2 mt-6">
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">{{ easy }}</p>
                        <p class="text-muted-foreground">Easy</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">{{ okay }}</p>
                        <p class="text-muted-foreground">Okay</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-2xl font-semibold tabular-nums">{{ hard }}</p>
                        <p class="text-muted-foreground">Hard</p>
                    </div>
                </div>

                <p v-if="hard > 0" class="mt-4 text-sm text-primary">
                    {{ hard }} item{{ hard !== 1 ? 's' : '' }} worth another look
                </p>
            </CardContent>

            <CardFooter class="justify-center">
                <!-- Done button -->
                <Button size="lg" as-child>
                    <Link :href="route('review.index')">Done</Link>
                </Button>

                <!-- Review a little more button -->
                <Button size="lg" block secondary @click="startAnotherReview">
                    <SlidersHorizontalIcon aria-hidden="true" />
                    Review a little more
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>

<script>
export default {
    name: 'ReviewFinish',
    props: {
        review: {
            type: Object,
            required: true,
        },
        totalItems: {
            type: Number,
            required: true,
        },
        hard: {
            type: Number,
            required: true,
        },
        okay: {
            type: Number,
            required: true,
        },
        easy: {
            type: Number,
            required: true,
        },
    },
    setup(props, { emit }) {
        return {};
    },
    methods: {
        startAnotherReview() {
            // Start a new review with the next items (never-reviewed first, then longest ago)
            // In a full implementation, this would navigate to Review Home or start a new review
            // For now, just show an alert
            alert('Starting another review...');
        },
    },
};
</script>