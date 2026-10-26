<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { SlidersHorizontalIcon } from '@lucide/vue';
import LibraryActiveFilters from '@/components/LibraryActiveFilters.vue';
import LibraryFilterControls from '@/components/LibraryFilterControls.vue';
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
    activeFilterCount,
    removeFilterChip,
    type LibraryFilterChip,
    type LibraryFilters,
} from '@/lib/library-filters';
import type { TopicSummary } from '@/types';

const props = defineProps<{
    filters: LibraryFilters;
    topics: TopicSummary[];
    updating?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (event: 'change', filters: LibraryFilters): void;
    (event: 'clear'): void;
}>();

const mobileOpen = ref(false);

const activeCount = computed(() => activeFilterCount(props.filters));

const filtersLabel = computed(() =>
    activeCount.value === 0
        ? 'Filters'
        : `Filters, ${activeCount.value} active`,
);

/**
 * After Clear all, focus goes back to a control the learner can use: the
 * topic button on a computer, the Filters button on a phone.
 */
function focusFirstControl(): void {
    void nextTick(() => {
        const isDesktop =
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(min-width: 768px)').matches;

        const target = isDesktop
            ? document.getElementById('library-desktop-topic-filter')
            : document.getElementById('library-filters-button');

        target?.focus();
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

function change(filters: LibraryFilters): void {
    emit('change', filters);
}

function remove(chip: LibraryFilterChip): void {
    emit('change', removeFilterChip(props.filters, chip));
}

defineExpose({ focusFirstControl });
</script>

<template>
    <div class="mb-4 grid gap-3">
        <div class="hidden flex-wrap items-center gap-2 md:flex">
            <LibraryFilterControls
                id-prefix="library-desktop"
                :filters="props.filters"
                :topics="props.topics"
                :disabled="props.disabled"
                @change="change"
            />
        </div>

        <div class="flex items-center gap-2 md:hidden">
            <Button
                id="library-filters-button"
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

        <LibraryActiveFilters
            :filters="props.filters"
            :topics="props.topics"
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
                        Narrow the Library by topic, date, content or status.
                    </SheetDescription>
                </SheetHeader>

                <LibraryFilterControls
                    id-prefix="library-mobile"
                    stacked
                    :filters="props.filters"
                    :topics="props.topics"
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
