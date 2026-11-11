<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { SlidersHorizontalIcon } from '@lucide/vue';
import CollectionActiveFilters from '@/components/CollectionActiveFilters.vue';
import CollectionFilterControls from '@/components/CollectionFilterControls.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    collectionActiveFilterCount,
    removeCollectionFilterChip,
    type CollectionFilterChip,
    type CollectionFilters,
} from '@/lib/collection-filters';
import type {
    CollectionNotebookOption,
    CollectionTabKey,
    CollectionTopicOption,
} from '@/types';

const props = defineProps<{
    filters: CollectionFilters;
    topics: CollectionTopicOption[];
    notebooks?: CollectionNotebookOption[];
    tab: CollectionTabKey;
    attentionCount?: number;
    updating?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (event: 'change', filters: CollectionFilters): void;
    (event: 'clear'): void;
}>();

const mobileOpen = ref(false);

const activeCount = computed(() => collectionActiveFilterCount(props.filters));

const filtersLabel = computed(() =>
    activeCount.value === 0
        ? 'Filters'
        : `Filters, ${activeCount.value} active`,
);

/** A control is usable when it is on screen and not disabled. */
function canFocus(target: HTMLElement): boolean {
    if (target.getAttribute('aria-disabled') === 'true') {
        return false;
    }

    return !(
        'disabled' in target &&
        (target as { disabled?: boolean }).disabled === true
    );
}

/**
 * After Clear all, focus goes back to a control the learner can use: the
 * topic button on a computer, the Filters button on a phone.
 */
function focusFirstControl(): void {
    void nextTick(() => {
        const isDesktop =
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(min-width: 768px)').matches;

        // On a computer the source control is the first one; the topic and
        // star controls follow when it cannot take focus. On a phone the
        // Filters button is the way back into the controls.
        const candidates = isDesktop
            ? [
                  'collection-desktop-source-filter',
                  'collection-desktop-topic-filter',
                  'collection-desktop-starred',
              ]
            : ['collection-filters-button'];

        for (const id of candidates) {
            const target = document.getElementById(id);

            if (target instanceof HTMLElement && canFocus(target)) {
                target.focus();

                return;
            }
        }
    });
}

function clearAndFocus(): void {
    emit('clear');
    focusFirstControl();
}

function clearFromSheet(): void {
    emit('clear');
    mobileOpen.value = false;
}

function change(filters: CollectionFilters): void {
    emit('change', filters);
}

function remove(chip: CollectionFilterChip): void {
    emit('change', removeCollectionFilterChip(props.filters, chip));
}

defineExpose({ focusFirstControl });
</script>

<template>
    <div class="mb-4 grid gap-3">
        <div class="hidden flex-wrap items-center gap-2 md:flex">
            <CollectionFilterControls
                id-prefix="collection-desktop"
                :filters="props.filters"
                :topics="props.topics"
                :notebooks="props.notebooks"
                :tab="props.tab"
                :attention-count="props.attentionCount"
                :disabled="props.disabled"
                @change="change"
            />
        </div>

        <div class="flex items-center gap-2 md:hidden">
            <Button
                id="collection-filters-button"
                type="button"
                variant="outline"
                class="h-11"
                :disabled="props.disabled"
                :aria-label="filtersLabel"
                @click="mobileOpen = true"
            >
                <SlidersHorizontalIcon aria-hidden="true" />
                Filters
                <Badge
                    v-if="activeCount > 0"
                    variant="secondary"
                    class="tabular-nums"
                >
                    {{ activeCount }}
                </Badge>
            </Button>
        </div>

        <CollectionActiveFilters
            :filters="props.filters"
            :topics="props.topics"
            :notebooks="props.notebooks"
            :disabled="props.disabled"
            @remove="remove"
            @clear="clearAndFocus"
            @focus-fallback="focusFirstControl"
        />

        <Sheet v-model:open="mobileOpen">
            <SheetContent
                side="bottom"
                class="max-h-[85dvh] gap-0 overflow-y-auto p-4 pb-24"
            >
                <SheetHeader class="mb-4 text-left">
                    <SheetTitle>Filters</SheetTitle>
                    <SheetDescription>
                        Narrow this collection by its source, marks, status or
                        the topics and notebooks it came from.
                    </SheetDescription>
                </SheetHeader>

                <CollectionFilterControls
                    id-prefix="collection-mobile"
                    stacked
                    :filters="props.filters"
                    :topics="props.topics"
                    :notebooks="props.notebooks"
                    :tab="props.tab"
                    :attention-count="props.attentionCount"
                    :disabled="props.disabled"
                    @change="change"
                />

                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        class="min-h-11 flex-1"
                        @click="mobileOpen = false"
                    >
                        Done
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="activeCount === 0"
                        @click="clearFromSheet"
                    >
                        Clear all
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
