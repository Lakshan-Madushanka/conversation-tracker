<script setup lang="ts">
import { computed, ref } from 'vue';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import type { DateRange } from 'reka-ui';
import {
    CalendarIcon,
    CheckIcon,
    ChevronDownIcon,
    ListFilterIcon,
    TagsIcon,
} from '@lucide/vue';
import SectionLabel from '@/components/SectionLabel.vue';
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
import { RangeCalendar } from '@/components/ui/range-calendar';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    contentTypes,
    copyLibraryFilters,
    dateChoices,
    dateFilterLabel,
    normalizeLibraryFilters,
    statusChoices,
    sortChoices,
    type LibraryDateFilter,
    type LibraryFilters,
    type LibrarySort,
    type LibraryStatusFilter,
} from '@/lib/library-filters';
import type { FeedbackType, TopicSummary } from '@/types';

const props = withDefaults(
    defineProps<{
        filters: LibraryFilters;
        topics: TopicSummary[];
        /** Lay the controls out in one column, as they are inside the phone panel. */
        stacked?: boolean;
        disabled?: boolean;
        /** Keeps ids unique when the controls appear in two places at once. */
        idPrefix?: string;
    }>(),
    {
        stacked: false,
        disabled: false,
        idPrefix: 'library',
    },
);

const emit = defineEmits<{
    (event: 'change', filters: LibraryFilters): void;
}>();

const topicOpen = ref(false);
const dateOpen = ref(false);
const contentOpen = ref(false);

const selectedTopicCount = computed(() => props.filters.topics.length);
const selectedTypeCount = computed(() => props.filters.types.length);

/** The Archived view holds only finished sessions, so status is not offered. */
const showStatus = computed(() => props.filters.view !== 'archived');

const rangePlaceholder = computed(() =>
    parseDate(props.filters.from ?? todayDay()),
);

const selectedRange = computed<DateRange | null>(() => {
    if (
        props.filters.date !== 'custom' ||
        props.filters.from === null ||
        props.filters.to === null
    ) {
        return null;
    }

    return {
        start: parseDate(props.filters.from),
        end: parseDate(props.filters.to),
    };
});

function apply(partial: Partial<LibraryFilters>): void {
    emit(
        'change',
        normalizeLibraryFilters({
            ...copyLibraryFilters(props.filters),
            ...partial,
        }),
    );
}

function toggleTopic(id: number): void {
    const topics = props.filters.topics.includes(id)
        ? props.filters.topics.filter((topic) => topic !== id)
        : [...props.filters.topics, id];

    apply({ topics });
}

function toggleType(type: FeedbackType): void {
    const types = props.filters.types.includes(type)
        ? props.filters.types.filter((candidate) => candidate !== type)
        : [...props.filters.types, type];

    apply({ types });
}

function chooseDate(date: LibraryDateFilter): void {
    apply({
        date,
        from: date === 'custom' ? props.filters.from : null,
        to: date === 'custom' ? props.filters.to : null,
    });

    if (date !== 'custom') {
        dateOpen.value = false;
    }
}

function chooseRange(value: DateRange | null): void {
    if (value === null || !value.start || !value.end) {
        return;
    }

    apply({
        date: 'custom',
        from: value.start.toString(),
        to: value.end.toString(),
    });
    dateOpen.value = false;
}

function changeStatus(value: unknown): void {
    const status: LibraryStatusFilter =
        value === 'finished' || value === 'unfinished' ? value : 'all';

    apply({ status });
}

function changeSort(value: unknown): void {
    const sort: LibrarySort =
        value === 'oldest' || value === 'recently_edited' ? value : 'newest';

    emitChange({ ...copyLibraryFilters(props.filters), sort });
}

function emitChange(filters: LibraryFilters): void {
    emit('change', normalizeLibraryFilters(filters));
}

function todayDay(): string {
    return today(getLocalTimeZone()).toString();
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
                Topic
            </span>

            <Popover v-model:open="topicOpen">
                <PopoverTrigger as-child>
                    <Button
                        :id="`${props.idPrefix}-topic-filter`"
                        type="button"
                        variant="outline"
                        class="h-11 w-full justify-between gap-2 md:h-8 md:w-auto"
                        :disabled="props.disabled"
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
                                    <span class="truncate">{{
                                        topic.name
                                    }}</span>
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
                Date
            </span>

            <Popover v-model:open="dateOpen">
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 w-full justify-between gap-2 md:h-8 md:w-auto"
                        :disabled="props.disabled"
                    >
                        <CalendarIcon aria-hidden="true" />
                        <span>{{ dateFilterLabel(props.filters) }}</span>
                        <ChevronDownIcon
                            class="ml-auto text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    class="w-auto max-w-[calc(100vw-2rem)] p-3"
                    align="start"
                >
                    <div class="grid gap-1">
                        <Button
                            v-for="choice in dateChoices"
                            :key="choice.value"
                            type="button"
                            variant="ghost"
                            class="h-11 justify-start md:h-9"
                            @click="chooseDate(choice.value)"
                        >
                            <CheckIcon
                                :class="
                                    props.filters.date === choice.value
                                        ? 'opacity-100'
                                        : 'opacity-0'
                                "
                                aria-hidden="true"
                            />
                            {{ choice.label }}
                        </Button>
                    </div>

                    <Separator />

                    <p class="px-1 text-xs font-medium text-muted-foreground">
                        Custom range
                    </p>

                    <RangeCalendar
                        :model-value="selectedRange"
                        :default-placeholder="rangePlaceholder"
                        :number-of-months="1"
                        @update:model-value="chooseRange"
                    />
                </PopoverContent>
            </Popover>
        </div>

        <div :class="props.stacked ? 'grid gap-1.5' : ''">
            <span
                v-if="props.stacked"
                class="text-xs font-medium text-muted-foreground"
            >
                Content
            </span>

            <Popover v-model:open="contentOpen">
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 w-full justify-between gap-2 md:h-8 md:w-auto"
                        :disabled="props.disabled"
                    >
                        <ListFilterIcon aria-hidden="true" />
                        <span>Content</span>
                        <Badge
                            v-if="selectedTypeCount > 0"
                            variant="secondary"
                            class="tabular-nums"
                        >
                            {{ selectedTypeCount }}
                        </Badge>
                        <ChevronDownIcon
                            class="ml-auto text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Button>
                </PopoverTrigger>

                <PopoverContent class="w-72" align="start">
                    <p class="px-1 text-xs font-medium text-muted-foreground">
                        Sessions with at least one…
                    </p>

                    <div
                        v-for="option in contentTypes"
                        :key="option.type"
                        class="flex items-center gap-2 rounded-md px-1 py-1.5 hover:bg-muted"
                    >
                        <Checkbox
                            :id="`${props.idPrefix}-type-${option.type}`"
                            :model-value="
                                props.filters.types.includes(option.type)
                            "
                            @update:model-value="toggleType(option.type)"
                        />
                        <Label
                            :for="`${props.idPrefix}-type-${option.type}`"
                            class="min-h-11 flex-1 cursor-pointer font-normal md:min-h-8"
                        >
                            <SectionLabel :tone="option.tone">
                                {{ option.label }}
                            </SectionLabel>
                        </Label>
                    </div>
                </PopoverContent>
            </Popover>
        </div>

        <div v-if="showStatus" :class="props.stacked ? 'grid gap-1.5' : ''">
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
                        v-for="choice in statusChoices"
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
                Sort
            </span>

            <Select
                :model-value="props.filters.sort"
                :disabled="props.disabled"
                @update:model-value="changeSort"
            >
                <SelectTrigger
                    class="h-11 w-full md:h-8 md:w-auto"
                    aria-label="Sort sessions"
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
