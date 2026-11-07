<script setup lang="ts">
import { computed, nextTick } from 'vue';
import ActiveFilterChips from '@/components/ActiveFilterChips.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    customReviewFilterChips,
    customReviewSortChoices,
    customReviewStatusChoices,
    type CustomReviewFilterChip,
} from '@/lib/custom-review-listing';
import type {
    CustomReviewFiltersState,
    CustomReviewSortValue,
    CustomReviewStatusValue,
} from '@/types';

const props = withDefaults(
    defineProps<{
        filters: CustomReviewFiltersState;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    (event: 'statuses', statuses: CustomReviewStatusValue[]): void;
    (event: 'sort', sort: CustomReviewSortValue): void;
    (event: 'remove-chip', chip: CustomReviewFilterChip): void;
    (event: 'clear-statuses'): void;
    (event: 'focus-fallback'): void;
}>();

const chips = computed(() => customReviewFilterChips(props.filters));

function isStatus(value: unknown): value is CustomReviewStatusValue {
    return value === 'empty' || value === 'draft' || value === 'finished';
}

function changeStatuses(value: unknown): void {
    emit('statuses', Array.isArray(value) ? value.filter(isStatus) : []);
}

function changeSort(value: unknown): void {
    if (
        value === 'recently_attempted' ||
        value === 'name' ||
        value === 'created'
    ) {
        emit('sort', value);
    }
}

function remove(chip: { key: string; label: string }): void {
    emit('remove-chip', chip as CustomReviewFilterChip);
}

/** After Clear all, focus returns to the first status control. */
function focusFirstControl(): void {
    void nextTick(() => {
        document.getElementById('custom-review-status-first')?.focus();
    });
}

defineExpose({ focusFirstControl });
</script>

<template>
    <div class="mb-4 grid gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <span
                    id="custom-review-status-label"
                    class="text-xs font-medium text-muted-foreground"
                >
                    Status
                </span>

                <ToggleGroup
                    type="multiple"
                    variant="outline"
                    :model-value="props.filters.statuses"
                    :disabled="props.disabled"
                    aria-labelledby="custom-review-status-label"
                    class="h-auto flex-wrap justify-start"
                    @update:model-value="changeStatuses"
                >
                    <ToggleGroupItem
                        v-for="(choice, index) in customReviewStatusChoices"
                        :key="choice.value"
                        :id="
                            index === 0
                                ? 'custom-review-status-first'
                                : undefined
                        "
                        :value="choice.value"
                        class="min-h-11 px-4 md:min-h-8"
                    >
                        {{ choice.label }}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <div class="grid gap-1.5">
                <span class="text-xs font-medium text-muted-foreground">
                    Sort
                </span>

                <Select
                    :model-value="props.filters.sort"
                    :disabled="props.disabled"
                    @update:model-value="changeSort"
                >
                    <SelectTrigger
                        class="h-11 w-full md:h-8 md:w-auto"
                        aria-label="Sort custom reviews"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="choice in customReviewSortChoices"
                            :key="choice.value"
                            :value="choice.value"
                            class="min-h-11 md:min-h-8"
                        >
                            {{ choice.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <ActiveFilterChips
            :chips="chips"
            :disabled="props.disabled"
            @remove="remove"
            @clear="emit('clear-statuses')"
            @focus-fallback="emit('focus-fallback')"
        />
    </div>
</template>
