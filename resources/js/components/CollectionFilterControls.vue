<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    CheckIcon,
    ChevronDownIcon,
    StarIcon,
    TagsIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    collectionSortChoices,
    collectionSourceChoices,
    collectionStatusChoices,
    copyCollectionFilters,
    normalizeCollectionFilters,
    type CollectionFilters,
    type CollectionSort,
    type CollectionSource,
    type CollectionStatusFilter,
} from '@/lib/collection-filters';
import type {
    CollectionNotebookOption,
    CollectionTabKey,
    CollectionTopicOption,
} from '@/types';

/**
 * The controls that search one collection: its source, the star, the learner's
 * status, the items that need attention, the topics and notebook that feed it,
 * and the order.
 */
const props = withDefaults(
    defineProps<{
        filters: CollectionFilters;
        topics: CollectionTopicOption[];
        notebooks?: CollectionNotebookOption[];
        tab: CollectionTabKey;
        /** How many items of the whole collection need attention. */
        attentionCount?: number;
        /** Lay the controls out in one column, as they are inside the phone panel. */
        stacked?: boolean;
        disabled?: boolean;
        /** Keeps ids unique when the controls appear in two places at once. */
        idPrefix?: string;
    }>(),
    {
        notebooks: () => [],
        attentionCount: 0,
        stacked: false,
        disabled: false,
        idPrefix: 'collection',
    },
);

const emit = defineEmits<{
    (event: 'change', filters: CollectionFilters): void;
}>();

const topicOpen = ref(false);

const selectedTopicCount = computed(() => props.filters.topics.length);

const sortChoices = computed(() => collectionSortChoices(props.tab));

function apply(partial: Partial<CollectionFilters>): void {
    emit(
        'change',
        normalizeCollectionFilters(
            {
                ...copyCollectionFilters(props.filters),
                ...partial,
            },
            props.tab,
        ),
    );
}

function toggleStarred(): void {
    apply({ starred: !props.filters.starred });
}

function toggleAttention(): void {
    apply({ attention: !props.filters.attention });
}

function toggleTopic(id: number): void {
    const topics = props.filters.topics.includes(id)
        ? props.filters.topics.filter((topic) => topic !== id)
        : [...props.filters.topics, id];

    apply({ topics });
}

function changeStatus(value: unknown): void {
    const status: CollectionStatusFilter =
        value === 'none' || value === 'learned' || value === 'still_difficult'
            ? value
            : 'all';

    apply({ status });
}

function changeSort(value: unknown): void {
    const sort: CollectionSort =
        value === 'az' || value === 'most_seen' ? value : 'newest';

    apply({ sort });
}

function changeSource(value: unknown): void {
    const source: CollectionSource =
        value === 'sessions' || value === 'notebooks' ? value : 'all';

    apply({ source });
}

/**
 * Choosing a notebook also narrows the source to notebooks, which the shared
 * normalisation applies, so the row never asks for a notebook the session-only
 * view cannot show.
 */
function changeNotebook(value: unknown): void {
    const id = typeof value === 'string' ? Number(value) : NaN;

    apply({ notebook: Number.isInteger(id) && id > 0 ? id : null });
}
</script>

<template>
    <div
        :class="
            props.stacked ? 'grid gap-4' : 'flex flex-wrap items-center gap-2'
        "
    >
        <div :class="props.stacked ? 'grid gap-1.5' : ''">
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Marks
            </span>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <Checkbox
                        :id="`${props.idPrefix}-starred`"
                        :model-value="props.filters.starred"
                        :disabled="props.disabled"
                        @update:model-value="toggleStarred"
                    />
                    <Label
                        :for="`${props.idPrefix}-starred`"
                        class="flex min-h-11 cursor-pointer items-center gap-1.5 font-normal md:min-h-8"
                    >
                        <StarIcon aria-hidden="true" />
                        Starred only
                    </Label>
                </div>

                <!-- Grammar notes never ask for attention, so the choice is
                    not offered there. -->
                <div
                    v-if="props.tab !== 'grammar'"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`${props.idPrefix}-attention`"
                        :model-value="props.filters.attention"
                        :disabled="props.disabled"
                        @update:model-value="toggleAttention"
                    />
                    <Label
                        :for="`${props.idPrefix}-attention`"
                        class="flex min-h-11 cursor-pointer items-center gap-1.5 font-normal md:min-h-8"
                    >
                        <TriangleAlertIcon aria-hidden="true" />
                        Needs attention only
                        <Badge
                            v-if="props.attentionCount > 0"
                            variant="secondary"
                            class="tabular-nums"
                        >
                            {{ props.attentionCount }}
                        </Badge>
                    </Label>
                </div>
            </div>
        </div>

        <div :class="props.stacked ? 'grid gap-1.5' : ''">
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Source
            </span>

            <Select
                :model-value="props.filters.source"
                :disabled="props.disabled"
                @update:model-value="changeSource"
            >
                <SelectTrigger
                    :id="`${props.idPrefix}-source-filter`"
                    class="h-11 w-full md:h-8 md:w-auto"
                    aria-label="Source"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="choice in collectionSourceChoices"
                        :key="choice.value"
                        :value="choice.value"
                        class="min-h-11 md:min-h-8"
                    >
                        {{ choice.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            v-if="props.filters.source !== 'sessions'"
            :class="props.stacked ? 'grid gap-1.5' : ''"
        >
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Notebook
            </span>

            <Select
                :model-value="
                    props.filters.notebook === null
                        ? 'all'
                        : String(props.filters.notebook)
                "
                :disabled="props.disabled || props.notebooks.length === 0"
                @update:model-value="changeNotebook"
            >
                <SelectTrigger
                    :id="`${props.idPrefix}-notebook-filter`"
                    class="h-11 w-full md:h-8 md:w-auto"
                    aria-label="Notebook"
                >
                    <SelectValue placeholder="All notebooks" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all" class="min-h-11 md:min-h-8">
                        All notebooks
                    </SelectItem>
                    <SelectItem
                        v-for="notebook in props.notebooks"
                        :key="notebook.id"
                        :value="String(notebook.id)"
                        class="min-h-11 md:min-h-8"
                    >
                        {{ notebook.name }} ({{ notebook.count }})
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div :class="props.stacked ? 'grid gap-1.5' : ''">
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Status
            </span>

            <Select
                :model-value="props.filters.status"
                :disabled="props.disabled"
                @update:model-value="changeStatus"
            >
                <SelectTrigger
                    class="h-11 w-full md:h-8 md:w-auto"
                    aria-label="Status"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="choice in collectionStatusChoices"
                        :key="choice.value"
                        :value="choice.value"
                        class="min-h-11 md:min-h-8"
                    >
                        {{ choice.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div :class="props.stacked ? 'grid gap-1.5' : ''">
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Topic
            </span>

            <Popover v-model:open="topicOpen">
                <PopoverTrigger as-child>
                    <Button
                        :id="`${props.idPrefix}-topic-filter`"
                        type="button"
                        variant="outline"
                        class="h-11 w-full justify-between gap-2 md:h-8 md:w-auto"
                        :disabled="props.disabled || props.topics.length === 0"
                    >
                        <TagsIcon aria-hidden="true" />
                        <span>Topic</span>
                        <Badge
                            v-if="selectedTopicCount > 0"
                            variant="secondary"
                            class="tabular-nums"
                        >
                            {{ selectedTopicCount }}
                        </Badge>
                        <ChevronDownIcon
                            class="ml-auto text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Button>
                </PopoverTrigger>

                <PopoverContent class="w-72 p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Find a topic…" />
                        <CommandList>
                            <CommandEmpty>No topics found.</CommandEmpty>
                            <CommandGroup>
                                <CommandItem
                                    v-for="topic in props.topics"
                                    :key="topic.id"
                                    :value="topic.name"
                                    class="min-h-11 md:min-h-8"
                                    :data-checked="
                                        props.filters.topics.includes(topic.id)
                                    "
                                    @select="toggleTopic(topic.id)"
                                >
                                    <CheckIcon
                                        :class="
                                            props.filters.topics.includes(
                                                topic.id,
                                            )
                                                ? 'opacity-100'
                                                : 'opacity-0'
                                        "
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        topic.name
                                    }}</span>
                                    <span
                                        class="ml-auto text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ topic.count }}
                                    </span>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>

        <div :class="props.stacked ? 'grid gap-1.5' : ''">
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Sort
            </span>

            <Select
                :model-value="props.filters.sort"
                :disabled="props.disabled"
                @update:model-value="changeSort"
            >
                <SelectTrigger
                    class="h-11 w-full md:h-8 md:w-auto"
                    aria-label="Sort collection"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="choice in sortChoices"
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
</template>
