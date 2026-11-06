<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    InfoIcon,
    PlayIcon,
    SlidersHorizontalIcon,
} from '@lucide/vue';
import CustomReviewControls from '@/components/review/CustomReviewControls.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    clearCustomChoices,
    customChoiceCount,
    customChoicesToQuery,
    customHintSentence,
    customMatchLabel,
    customWideningHints,
    isCustomReviewRefresh,
    normalizeCustomChoices,
    type CustomReviewChoiceOptions,
} from '@/lib/review-custom';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    custom as customReview,
    customStore as customReviewStore,
    index as reviewIndex,
} from '@/routes/review';
import type {
    CollectionTopicOption,
    CustomReviewChoices,
    CustomReviewCollectionOption,
    CustomReviewPreview,
} from '@/types';

/**
 * The custom review page: what to look at, how many items, and a live count of
 * what actually matches.
 *
 * The count is the sitting — the server answers with the same suggestion a
 * started review is built from, so the number, the mix and the estimate never
 * disagree with what Start would give. The choices live in the address and
 * nowhere else: a refresh keeps them and nothing is saved as a preference.
 */
defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** The choices the address carried, as the server understood them. */
    choices: CustomReviewChoices;
    topics: CollectionTopicOption[];
    collections: CustomReviewCollectionOption[];
    sizes: number[];
    /** The length the daily review uses, offered among the sizes. */
    dailySize: number;
    /** What a sitting with these choices would hold right now. */
    preview: CustomReviewPreview;
}>();

/** How many items to wait for before asking the server again. */
const REFRESH_DELAY_MS = 350;

const options = computed<CustomReviewChoiceOptions>(() => ({
    sizes: props.sizes,
    dailySize: props.dailySize,
    collections: props.collections.map((collection) => collection.value),
}));

const choices = ref<CustomReviewChoices>(
    normalizeCustomChoices(props.choices, options.value),
);
const preview = ref<CustomReviewPreview>(props.preview);
const topicOptions = ref<CollectionTopicOption[]>([...props.topics]);

const updating = ref(false);
const countFailed = ref(false);
const starting = ref(false);
const panelOpen = ref(false);

let refreshTimer: number | undefined;

/**
 * Guards against a slow count for older choices replacing a newer one.
 * Only the latest request may change what the page shows.
 */
let generation = 0;

const setCount = computed(() => customChoiceCount(choices.value));

const estimate = computed(() => {
    const minutes = preview.value.minutes;
    const minutesLabel =
        minutes === 1 ? 'About 1 minute' : `About ${minutes} minutes`;

    return `${minutesLabel} · ${customMatchLabel(preview.value.count)}`;
});

const noMatch = computed(() => preview.value.count === 0);

const tooFew = computed(() => !noMatch.value && !preview.value.comfortable);

const matchNoteId = 'custom-review-match';

const wideningHint = computed(() =>
    customHintSentence(
        customWideningHints(choices.value, {
            topics: topicOptions.value.length,
            collections: props.collections.length,
        }),
    ),
);

/** Ask the server what the choices now match, keeping the address in step. */
function requestCount(): void {
    const run = ++generation;
    const address = customReview.url({
        query: customChoicesToQuery(choices.value, options.value),
    });

    updating.value = true;

    router.get(
        address,
        {},
        {
            only: ['preview', 'topics'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                if (run !== generation) {
                    return;
                }

                updating.value = false;

                if (!isCustomReviewRefresh(page.props)) {
                    countFailed.value = true;

                    return;
                }

                countFailed.value = false;
                preview.value = page.props.preview;
                topicOptions.value = page.props.topics;
            },
            onError: () => {
                if (run === generation) {
                    updating.value = false;
                    countFailed.value = true;
                }
            },
            onNetworkError: () => {
                if (run === generation) {
                    updating.value = false;
                    countFailed.value = true;
                }
            },
        },
    );
}

function apply(next: CustomReviewChoices): void {
    choices.value = normalizeCustomChoices(next, options.value);

    if (refreshTimer !== undefined) {
        window.clearTimeout(refreshTimer);
    }

    refreshTimer = window.setTimeout(requestCount, REFRESH_DELAY_MS);
}

function clearChoices(): void {
    apply(clearCustomChoices(choices.value, options.value));
}

function startReview(): void {
    if (noMatch.value || starting.value) {
        return;
    }

    starting.value = true;

    router.post(
        customReviewStore.url(),
        {
            topics: choices.value.topics,
            collections: choices.value.collections,
            starred: choices.value.starred,
            learned: choices.value.learned,
            size: choices.value.size,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                starting.value = false;
            },
        },
    );
}

onBeforeUnmount(() => {
    if (refreshTimer !== undefined) {
        window.clearTimeout(refreshTimer);
    }
});
</script>

<template>
    <div>
        <Head title="Custom review" />

        <PageHeader
            title="Custom review"
            description="Choose what to look at. The same exercises, the same rules, on your terms."
        >
            <template #actions>
                <Button
                    variant="ghost"
                    size="sm"
                    class="cursor-pointer"
                    as-child
                >
                    <Link :href="reviewIndex.url()">
                        <ArrowLeftIcon aria-hidden="true" />
                        Back to Review
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- The choices, on a wide screen -->
        <section class="mb-4 hidden md:block" aria-label="What to review">
            <CustomReviewControls
                id-prefix="custom-desktop"
                :choices="choices"
                :topics="topicOptions"
                :collections="props.collections"
                :sizes="props.sizes"
                :daily-size="props.dailySize"
                :disabled="starting"
                @change="apply"
            />
        </section>

        <!-- The same choices, from a button on a phone -->
        <Sheet v-model:open="panelOpen">
            <div class="mb-4 flex items-center gap-2 md:hidden">
                <SheetTrigger as-child>
                    <Button variant="outline" class="h-11 cursor-pointer">
                        <SlidersHorizontalIcon aria-hidden="true" />
                        Customize
                        <Badge
                            v-if="setCount > 0"
                            variant="secondary"
                            class="tabular-nums"
                        >
                            {{ setCount }}
                        </Badge>
                    </Button>
                </SheetTrigger>

                <Button
                    variant="ghost"
                    class="h-11 cursor-pointer"
                    :disabled="setCount === 0"
                    @click="clearChoices"
                >
                    Clear
                </Button>
            </div>

            <SheetContent
                side="bottom"
                class="max-h-[85dvh] gap-0 overflow-y-auto p-4 pb-24"
            >
                <SheetHeader class="mb-4 text-left">
                    <SheetTitle>What to review</SheetTitle>
                    <SheetDescription>
                        Pick the topics and collections worth another look.
                    </SheetDescription>
                </SheetHeader>

                <CustomReviewControls
                    id-prefix="custom-mobile"
                    stacked
                    :choices="choices"
                    :topics="topicOptions"
                    :collections="props.collections"
                    :sizes="props.sizes"
                    :daily-size="props.dailySize"
                    :disabled="starting"
                    @change="apply"
                />

                <SheetFooter class="mt-6 flex-row gap-2">
                    <Button
                        class="min-h-11 flex-1 cursor-pointer"
                        @click="panelOpen = false"
                    >
                        Done
                    </Button>

                    <Button
                        variant="outline"
                        class="min-h-11 cursor-pointer"
                        :disabled="setCount === 0"
                        @click="clearChoices"
                    >
                        Clear all
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>

        <!-- What the choices match, read as they change -->
        <section class="mb-4 grid gap-3" aria-labelledby="custom-match">
            <h2 id="custom-match" class="sr-only">What matches</h2>

            <p
                class="flex flex-wrap items-center gap-2 text-sm"
                aria-live="polite"
                :aria-busy="updating"
            >
                <Badge :variant="noMatch ? 'secondary' : 'default'">
                    {{ customMatchLabel(preview.count) }}
                </Badge>

                <span class="text-muted-foreground">{{ estimate }}</span>

                <span v-if="updating" class="text-xs text-muted-foreground">
                    Counting…
                </span>
            </p>

            <p v-if="preview.mix" class="text-sm text-muted-foreground">
                {{ preview.mix }}
            </p>

            <Alert v-if="countFailed" class="border-dashed">
                <InfoIcon aria-hidden="true" />
                <AlertTitle>
                    <h3 class="font-medium">That count is out of date</h3>
                </AlertTitle>
                <AlertDescription>
                    <p>
                        The choices are kept — the number below is the last one
                        that came through. Try again in a moment.
                    </p>
                </AlertDescription>
            </Alert>

            <Alert
                v-else-if="tooFew"
                id="custom-review-match"
                class="border-dashed"
            >
                <InfoIcon aria-hidden="true" />
                <AlertTitle>
                    <h3 class="font-medium">
                        Only {{ preview.count }}
                        {{
                            preview.count === 1
                                ? 'item matches'
                                : 'items match'
                        }}.
                    </h3>
                </AlertTitle>
                <AlertDescription>
                    <p>
                        Try {{ wideningHint }}. A short sitting is still worth
                        doing, so you can start with what is here.
                    </p>
                </AlertDescription>
            </Alert>

            <Alert
                v-else-if="noMatch"
                id="custom-review-match"
                class="border-dashed"
            >
                <InfoIcon aria-hidden="true" />
                <AlertTitle>
                    <h3 class="font-medium">
                        Nothing matches these choices yet
                    </h3>
                </AlertTitle>
                <AlertDescription>
                    <p>
                        Try {{ wideningHint }}. Archived and unfinished sessions
                        never appear, so a topic can be empty for a while.
                    </p>
                </AlertDescription>
            </Alert>
        </section>

        <div class="grid gap-2">
            <Button
                size="lg"
                class="h-11 cursor-pointer"
                :disabled="noMatch || starting"
                :aria-describedby="tooFew || noMatch ? matchNoteId : undefined"
                @click="startReview"
            >
                <PlayIcon aria-hidden="true" />
                Start custom review
            </Button>

            <p v-if="noMatch" class="text-sm text-muted-foreground">
                Start becomes available as soon as something matches.
            </p>
            <p v-else-if="tooFew" class="text-sm text-muted-foreground">
                A short review is fine — start with these
                {{ preview.count }}
                {{ preview.count === 1 ? 'item' : 'items' }}.
            </p>
        </div>
    </div>
</template>
