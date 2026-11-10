<script setup lang="ts">
import { computed } from 'vue';
import {
    ListFilterIcon,
    LoaderCircleIcon,
    PlusIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import CustomReviewCard from '@/components/CustomReviewCard.vue';
import CustomReviewCardSkeleton from '@/components/CustomReviewCardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { customReviewResultAnnouncement } from '@/lib/custom-review-listing';
import type { CustomReviewCardData } from '@/types';

/** The cards loaded so far, with the states that explain how loading is going. */
const props = withDefaults(
    defineProps<{
        reviews: CustomReviewCardData[];
        hasMore?: boolean;
        loading?: boolean;
        error?: boolean;
        total?: number;
        allTotal?: number;
        /** True when the search or any status is narrowing the list. */
        hasFilters?: boolean;
        /** True when a search is narrowing the list. */
        hasSearch?: boolean;
        /** True when a status chip is narrowing the list. */
        hasFilterChips?: boolean;
        /** True while a new filter state is on its way. */
        updating?: boolean;
        /** True when that filter request failed. */
        filterError?: boolean;
        /** The review whose status is on its way, if any. */
        busyId?: number | null;
        /** Where a review's Back button should return to. */
        from?: string;
    }>(),
    {
        hasMore: false,
        loading: false,
        error: false,
        total: 0,
        allTotal: 0,
        hasFilters: false,
        hasSearch: false,
        hasFilterChips: false,
        updating: false,
        filterError: false,
        busyId: null,
        from: undefined,
    },
);

const emit = defineEmits<{
    (event: 'load-more'): void;
    (event: 'clear-filters'): void;
    (event: 'clear-search'): void;
    (event: 'retry-filters'): void;
    (event: 'create'): void;
    (
        event: 'change-status',
        review: CustomReviewCardData,
        status: 'draft' | 'finished',
    ): void;
}>();

/** A short column of placeholders while the next page arrives. */
const skeletonCount = 3;

const emptyTitle = computed(() =>
    props.hasSearch
        ? 'No custom reviews match your search'
        : 'No custom reviews match these filters',
);

const emptyDescription = computed(() =>
    props.hasSearch
        ? 'Try different words, or clear the search to see every review again.'
        : 'Try a different status, or clear the filter to see every review again.',
);

/** A "Clear all" button is only useful when a status chip is on. */
const showClearFilters = computed(
    () => props.hasFilterChips || (!props.hasSearch && props.hasFilters),
);

/**
 * What a screen reader hears once the list has settled. It stays empty while
 * a request is on its way, so typing a word announces one result count rather
 * than a stream of them.
 */
const resultAnnouncement = computed(() => {
    if (props.updating || props.loading) {
        return '';
    }

    return customReviewResultAnnouncement(props.total, props.hasFilters);
});
</script>

<template>
    <div>
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ resultAnnouncement }}
        </p>

        <div
            v-if="props.updating"
            class="grid gap-3"
            aria-busy="true"
            aria-label="Updating the list"
        >
            <CustomReviewCardSkeleton
                v-for="skeleton in skeletonCount"
                :key="skeleton"
            />
        </div>

        <template v-else>
            <div
                v-if="props.reviews.length > 0 || props.loading"
                class="grid gap-3"
                :aria-busy="props.loading"
            >
                <CustomReviewCard
                    v-for="review in props.reviews"
                    :key="review.id"
                    :review="review"
                    :from="props.from"
                    :busy="props.busyId === review.id"
                    @change-status="
                        (chosen, status) =>
                            emit('change-status', chosen, status)
                    "
                />

                <CustomReviewCardSkeleton
                    v-for="skeleton in props.loading ? skeletonCount : 0"
                    :key="`skeleton-${skeleton}`"
                />
            </div>

            <EmptyState
                v-else-if="
                    props.allTotal === 0 &&
                    !props.loading &&
                    !props.error &&
                    !props.filterError
                "
                :icon="PlusIcon"
                title="No custom reviews yet"
                description="Build one from the items you choose, and practise it whenever you like."
            >
                <template #actions>
                    <Button
                        type="button"
                        size="lg"
                        class="min-h-11 md:min-h-9"
                        @click="emit('create')"
                    >
                        <PlusIcon aria-hidden="true" />
                        New custom review
                    </Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="
                    props.reviews.length === 0 &&
                    props.hasFilters &&
                    !props.error &&
                    !props.filterError
                "
                :icon="ListFilterIcon"
                :title="emptyTitle"
                :description="emptyDescription"
            >
                <template #actions>
                    <div
                        class="flex flex-wrap items-center justify-center gap-2"
                    >
                        <Button
                            v-if="props.hasSearch"
                            type="button"
                            variant="outline"
                            class="min-h-11 md:min-h-9"
                            @click="emit('clear-search')"
                        >
                            Clear search
                        </Button>

                        <Button
                            v-if="showClearFilters"
                            type="button"
                            variant="outline"
                            class="min-h-11 md:min-h-9"
                            @click="emit('clear-filters')"
                        >
                            Clear all filters
                        </Button>
                    </div>
                </template>
            </EmptyState>

            <Alert v-if="props.filterError" variant="destructive" class="mt-4">
                <TriangleAlertIcon aria-hidden="true" />
                <AlertTitle>Could not update the list</AlertTitle>
                <AlertDescription>
                    <p>
                        Your custom reviews are still here. Check your
                        connection and try again.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3 min-h-11 md:min-h-9"
                        @click="emit('retry-filters')"
                    >
                        Try again
                    </Button>
                </AlertDescription>
            </Alert>

            <Alert v-if="props.error" variant="destructive" class="mt-4">
                <TriangleAlertIcon aria-hidden="true" />
                <AlertTitle>Could not load more custom reviews</AlertTitle>
                <AlertDescription>
                    <p>
                        The reviews already on screen are still here. Check your
                        connection and try again.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3 min-h-11 md:min-h-9"
                        @click="emit('load-more')"
                    >
                        Try again
                    </Button>
                </AlertDescription>
            </Alert>
        </template>

        <div
            v-if="props.reviews.length > 0 && !props.updating"
            class="mt-5 flex flex-col items-center gap-3"
        >
            <Button
                v-if="props.hasMore"
                type="button"
                variant="outline"
                class="min-h-11 md:min-h-9"
                :disabled="props.loading"
                @click="emit('load-more')"
            >
                <LoaderCircleIcon
                    v-if="props.loading"
                    class="animate-spin"
                    aria-hidden="true"
                />
                {{ props.loading ? 'Loading…' : 'Load more custom reviews' }}
            </Button>

            <p v-else class="text-xs text-muted-foreground">
                {{
                    props.hasFilters
                        ? `That is every matching custom review — ${props.total} in total.`
                        : `That is every custom review — ${props.total} in total.`
                }}
            </p>

            <p v-if="props.hasMore" class="text-xs text-muted-foreground">
                Showing {{ props.reviews.length }} of {{ props.total }}
            </p>
        </div>
    </div>
</template>
