<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import CustomReviewStatusControl from '@/components/CustomReviewStatusControl.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { reviewPageUrl } from '@/lib/custom-review';
import { customReviewCreatedLabel } from '@/lib/custom-review-listing';
import type { CustomReviewCardData } from '@/types';

const props = withDefaults(
    defineProps<{
        review: CustomReviewCardData;
        /** Where the review's Back button should return to. */
        from?: string;
        /** True while this card's status is on its way to the server. */
        busy?: boolean;
    }>(),
    { from: undefined, busy: false },
);

const emit = defineEmits<{
    (
        event: 'change-status',
        review: CustomReviewCardData,
        status: 'draft' | 'finished',
    ): void;
}>();

const href = computed(() => reviewPageUrl(props.review.id, props.from));

const createdLabel = computed(() =>
    customReviewCreatedLabel(props.review.created_at),
);

function changeStatus(status: 'draft' | 'finished'): void {
    emit('change-status', props.review, status);
}
</script>

<template>
    <Card class="py-0">
        <div class="grid gap-4 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold break-words">
                        <Link
                            :href="href"
                            class="rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            {{ props.review.name }}
                        </Link>
                    </h2>

                    <p
                        v-if="props.review.description_excerpt"
                        class="mt-1 line-clamp-2 max-w-prose text-sm break-words text-muted-foreground"
                    >
                        {{ props.review.description_excerpt }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge variant="secondary">
                        {{ props.review.status_label }}
                    </Badge>
                    <Badge v-if="props.review.too_many_items" variant="outline">
                        {{ props.review.too_many_label }}
                    </Badge>
                </div>
            </div>

            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
            >
                <span>{{ props.review.item_count_label }}</span>

                <template v-if="createdLabel !== ''">
                    <span aria-hidden="true">·</span>
                    <time
                        v-if="props.review.created_at"
                        :datetime="props.review.created_at"
                    >
                        {{ createdLabel }}
                    </time>
                </template>
            </div>

            <div class="grid gap-1 text-sm">
                <p class="font-medium">
                    {{ props.review.attempt_number_label }}
                </p>

                <p
                    v-if="props.review.previous_attempt_label"
                    class="text-muted-foreground"
                >
                    {{ props.review.previous_attempt_label }}
                </p>

                <p class="text-muted-foreground">
                    {{ props.review.attempt_summary_label }}

                    <Link
                        v-if="props.review.view_history_url"
                        :href="props.review.view_history_url"
                        class="font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        View history
                    </Link>
                </p>
            </div>

            <p
                v-if="props.review.practice_note"
                class="text-sm text-muted-foreground"
            >
                {{ props.review.practice_note }}
            </p>

            <!-- Start practice arrives with attempts in Step 4. -->

            <div class="border-t pt-3">
                <CustomReviewStatusControl
                    :status="props.review.status"
                    :can-finish="props.review.item_count > 0"
                    :busy="props.busy"
                    @change="changeStatus"
                />
            </div>
        </div>
    </Card>
</template>
