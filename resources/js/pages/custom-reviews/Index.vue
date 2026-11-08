<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { PlusIcon } from '@lucide/vue';
import { toast } from 'vue-sonner';
import CustomReviewFilterBar from '@/components/CustomReviewFilterBar.vue';
import CustomReviewList from '@/components/CustomReviewList.vue';
import LibrarySearch from '@/components/LibrarySearch.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useCustomReviews } from '@/composables/useCustomReviews';
import AppLayout from '@/layouts/AppLayout.vue';
import { updateReviewStatus } from '@/lib/custom-review';
import {
    customReviewCountLabel,
    hasCustomReviewFilters,
    hasCustomReviewSearch,
    hasCustomReviewStatuses,
} from '@/lib/custom-review-listing';
import { store as customReviewsStore } from '@/routes/customReviews';
import type { CustomReviewCardData, CustomReviewsPageData } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<CustomReviewsPageData>();

const {
    reviews,
    hasMore,
    total,
    allTotal,
    filters,
    loading,
    error,
    updating,
    filterError,
    loadMore,
    refresh,
    retryFilters,
    setSort,
    setSearch,
    clearSearch,
    clearStatuses,
    setStatuses,
    removeChip,
} = useCustomReviews(props);

/** The card whose status is on its way, so only that card waits. */
const busyId = ref<number | null>(null);

/** A calm line a screen reader hears after a status change. */
const statusNotice = ref('');

/**
 * A delete answers here, in the server's own words, because the review leaves
 * the listing the moment it is confirmed. Said once, on arrival, and never
 * again: the message is not re-shown by a later refresh of the list.
 */
onMounted(() => {
    if (props.notice !== null) {
        toast.info(props.notice);
    }
});

const search = ref<InstanceType<typeof LibrarySearch> | null>(null);
const filterBar = ref<InstanceType<typeof CustomReviewFilterBar> | null>(null);

const narrowed = computed(() => hasCustomReviewFilters(filters.value));

const countLabel = computed(() =>
    customReviewCountLabel(total.value, allTotal.value, narrowed.value),
);

/** Clearing the search from the empty state leaves the box ready to type. */
function clearSearchAndFocus(): void {
    clearSearch();
    search.value?.focus();
}

/** Clearing the statuses from the empty state returns focus to the filter. */
function clearStatusesAndFocus(): void {
    clearStatuses();
    filterBar.value?.focusFirstControl();
}

function focusFirstFilter(): void {
    filterBar.value?.focusFirstControl();
}

/**
 * Apply the learner's Draft-or-Finished choice from a card, then read the
 * list again so an active status filter and the practice note stay truthful.
 */
async function changeStatus(
    review: CustomReviewCardData,
    status: 'draft' | 'finished',
): Promise<void> {
    if (busyId.value !== null) {
        return;
    }

    busyId.value = review.id;
    statusNotice.value = '';

    try {
        const saved = await updateReviewStatus(review.id, status);

        reviews.value = reviews.value.map((card) =>
            card.id === saved.id
                ? {
                      ...card,
                      status: saved.status,
                      status_label: saved.status_label,
                      item_count: saved.item_count,
                      item_count_label: saved.item_count_label,
                      too_many_items: saved.too_many_items,
                      too_many_label: saved.too_many_label,
                  }
                : card,
        );

        // The filter and the practice note come from the server, so ask it
        // again and let a card leave the list when its new status no longer
        // matches what the learner is looking at.
        await refresh();

        statusNotice.value = `“${saved.name}” is now ${saved.status_label}.`;
    } catch (failure) {
        const message =
            failure instanceof Error
                ? failure.message
                : 'This review could not be moved just now.';

        statusNotice.value = message;
        toast.error(message);
    } finally {
        busyId.value = null;
    }
}
</script>

<template>
    <div>
        <Head title="Custom reviews" />

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ statusNotice }}
        </p>

        <PageHeader
            title="Custom reviews"
            description="The sets you build from your own words, phrases, notes and mistakes — ready whenever you are."
        >
            <template #actions>
                <Button size="lg" class="min-h-11" as-child>
                    <Link :href="customReviewsStore.url()" method="post">
                        <PlusIcon aria-hidden="true" />
                        New custom review
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <LibrarySearch
            ref="search"
            :model-value="filters.q"
            :updating="updating"
            label="Search custom reviews by name or description"
            @search="setSearch"
        />

        <CustomReviewFilterBar
            ref="filterBar"
            :filters="filters"
            :disabled="updating"
            @statuses="setStatuses"
            @sort="setSort"
            @remove-chip="removeChip"
            @clear-statuses="clearStatusesAndFocus"
            @focus-fallback="focusFirstFilter"
        />

        <p class="mb-3 text-sm text-muted-foreground">
            {{ countLabel }}
        </p>

        <CustomReviewList
            :reviews="reviews"
            :has-more="hasMore"
            :loading="loading"
            :error="error"
            :total="total"
            :all-total="allTotal"
            :has-filters="narrowed"
            :has-search="hasCustomReviewSearch(filters)"
            :has-filter-chips="hasCustomReviewStatuses(filters)"
            :updating="updating"
            :filter-error="filterError"
            :busy-id="busyId"
            @load-more="loadMore"
            @clear-filters="clearStatusesAndFocus"
            @clear-search="clearSearchAndFocus"
            @retry-filters="retryFilters"
            @change-status="changeStatus"
        />
    </div>
</template>
