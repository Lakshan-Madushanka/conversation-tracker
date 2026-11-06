<script setup lang="ts">
import { computed, ref } from 'vue';
import { CheckIcon, ChevronDownIcon, StarIcon, TagsIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    copyCustomChoices,
    customSizeLabel,
    toggleCustomTopic,
    withCustomSize,
} from '@/lib/review-custom';
import type {
    CollectionTopicOption,
    CustomReviewChoices,
    CustomReviewCollectionOption,
} from '@/types';

/**
 * The choices that decide what a custom review covers, in the order the page
 * asks them: which topics, which collections, only starred items, whether
 * Learned items take part, and how many items to look at.
 */
const props = withDefaults(
    defineProps<{
        choices: CustomReviewChoices;
        topics: CollectionTopicOption[];
        collections: CustomReviewCollectionOption[];
        sizes: number[];
        /** The length the daily review uses, named among the options. */
        dailySize: number;
        /** Lay the choices out in one column, as they are inside the phone panel. */
        stacked?: boolean;
        disabled?: boolean;
        /** Keeps ids unique when the choices appear in two places at once. */
        idPrefix?: string;
    }>(),
    {
        stacked: false,
        disabled: false,
        idPrefix: 'custom',
    },
);

const emit = defineEmits<{
    (event: 'change', choices: CustomReviewChoices): void;
}>();

const topicOpen = ref(false);

const chosenTopicNames = computed(() =>
    props.topics
        .filter((topic) => props.choices.topics.includes(topic.id))
        .map((topic) => topic.name),
);

function change(next: CustomReviewChoices): void {
    emit('change', copyCustomChoices(next));
}

function toggleTopic(topicId: number): void {
    change(toggleCustomTopic(props.choices, topicId));
}

/** Keep only the collections the page offers, in the order it lists them. */
function chooseCollections(value: unknown): void {
    const chosen = Array.isArray(value) ? value : [];

    change({
        ...copyCustomChoices(props.choices),
        collections: props.collections
            .map((collection) => collection.value)
            .filter((option) => chosen.includes(option)),
    });
}

function setStarred(value: unknown): void {
    change({ ...copyCustomChoices(props.choices), starred: value === true });
}

function setLearned(value: unknown): void {
    change({ ...copyCustomChoices(props.choices), learned: value === true });
}

function chooseSize(value: unknown): void {
    if (typeof value !== 'string') {
        return;
    }

    change(withCustomSize(props.choices, Number(value)));
}

function clearTopics(): void {
    change({ ...copyCustomChoices(props.choices), topics: [] });
}
</script>

<template>
    <div :class="props.stacked ? 'grid gap-5' : 'grid gap-4 sm:grid-cols-2'">
        <!-- Topics -->
        <div class="grid gap-1.5">
            <span class="text-xs font-medium text-muted-foreground">
                Topics
            </span>

            <Popover v-model:open="topicOpen">
                <PopoverTrigger as-child>
                    <Button
                        :id="`${props.idPrefix}-topics`"
                        type="button"
                        variant="outline"
                        class="h-11 w-full justify-between gap-2 text-left font-normal md:h-9"
                        :disabled="props.disabled || props.topics.length === 0"
                    >
                        <TagsIcon aria-hidden="true" />
                        <span class="truncate">
                            {{
                                props.choices.topics.length === 0
                                    ? 'Every topic'
                                    : `${props.choices.topics.length} chosen`
                            }}
                        </span>
                        <ChevronDownIcon
                            class="ml-auto shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Button>
                </PopoverTrigger>

                <PopoverContent class="w-72 p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Find a topic…" />
                        <CommandList>
                            <CommandEmpty>No topic matches.</CommandEmpty>
                            <CommandGroup>
                                <CommandItem
                                    v-for="topic in props.topics"
                                    :key="topic.id"
                                    :value="topic.name"
                                    class="min-h-11 md:min-h-8"
                                    :data-checked="
                                        props.choices.topics.includes(topic.id)
                                    "
                                    @select="toggleTopic(topic.id)"
                                >
                                    <CheckIcon
                                        :class="
                                            props.choices.topics.includes(
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

            <p
                v-if="props.topics.length === 0"
                class="text-xs text-muted-foreground"
            >
                Only topics with something to review are offered, so there are
                none yet.
            </p>
            <p v-else class="text-xs text-muted-foreground">
                Leave every topic chosen to look at the whole library. The
                number beside each one is how much it holds.
            </p>

            <div
                v-if="chosenTopicNames.length > 0"
                class="flex flex-wrap gap-1"
            >
                <Badge
                    v-for="name in chosenTopicNames"
                    :key="name"
                    variant="secondary"
                >
                    {{ name }}
                </Badge>

                <button
                    type="button"
                    class="text-xs font-medium text-primary underline-offset-2 hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    :disabled="props.disabled"
                    @click="clearTopics"
                >
                    Clear topics
                </button>
            </div>
        </div>

        <!-- Collections -->
        <div class="grid gap-1.5">
            <span class="text-xs font-medium text-muted-foreground">
                Collections
            </span>

            <ToggleGroup
                type="multiple"
                variant="outline"
                :model-value="props.choices.collections"
                :disabled="props.disabled"
                class="h-auto w-full flex-wrap gap-1.5"
                @update:model-value="chooseCollections"
            >
                <ToggleGroupItem
                    v-for="collection in props.collections"
                    :key="collection.value"
                    :value="collection.value"
                    :aria-label="collection.label"
                    class="min-h-11 rounded-lg border px-3 text-sm data-[state=on]:border-primary data-[state=on]:bg-primary/10 data-[state=on]:text-primary md:min-h-9"
                >
                    {{ collection.label }}
                </ToggleGroupItem>
            </ToggleGroup>

            <p class="text-xs text-muted-foreground">
                {{
                    props.choices.collections.length === 0
                        ? 'Nothing chosen means every collection takes part.'
                        : 'Only the collections chosen take part.'
                }}
            </p>
        </div>

        <!-- Starred only -->
        <div class="grid gap-1.5">
            <span class="text-xs font-medium text-muted-foreground">
                Marks
            </span>

            <div class="flex items-center gap-3">
                <Switch
                    :id="`${props.idPrefix}-starred`"
                    :model-value="props.choices.starred"
                    :disabled="props.disabled"
                    @update:model-value="setStarred"
                />
                <Label
                    :for="`${props.idPrefix}-starred`"
                    class="flex min-h-11 cursor-pointer items-center gap-1.5 font-normal md:min-h-9"
                >
                    <StarIcon aria-hidden="true" />
                    Starred only
                </Label>
            </div>

            <p class="text-xs text-muted-foreground">
                Only starred words, phrases and mistakes. Past questions and
                grammar notes carry no star, so they are left out.
            </p>
        </div>

        <!-- Include Learned -->
        <div class="grid gap-1.5">
            <span class="text-xs font-medium text-muted-foreground">
                Learned items
            </span>

            <div class="flex items-center gap-3">
                <Switch
                    :id="`${props.idPrefix}-learned`"
                    :model-value="props.choices.learned"
                    :disabled="props.disabled"
                    @update:model-value="setLearned"
                />
                <Label
                    :for="`${props.idPrefix}-learned`"
                    class="flex min-h-11 cursor-pointer items-center gap-1.5 font-normal md:min-h-9"
                >
                    Include Learned items
                </Label>
            </div>

            <p class="text-xs text-muted-foreground">
                {{
                    props.choices.learned
                        ? 'Learned items may appear. They are not pushed to the front.'
                        : 'Learned items are left out, as in the daily review.'
                }}
            </p>
        </div>

        <!-- How many -->
        <div class="grid gap-1.5">
            <span class="text-xs font-medium text-muted-foreground">
                How many items
            </span>

            <Select
                :model-value="String(props.choices.size)"
                :disabled="props.disabled"
                @update:model-value="chooseSize"
            >
                <SelectTrigger
                    :id="`${props.idPrefix}-size`"
                    class="h-11 w-full md:h-9"
                    aria-label="How many items"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="size in props.sizes"
                        :key="size"
                        :value="String(size)"
                        class="min-h-11 md:min-h-9"
                    >
                        {{ customSizeLabel(size, props.dailySize) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <p class="text-xs text-muted-foreground">
                A shorter sitting is fine. It is still a review.
            </p>
        </div>
    </div>
</template>
