<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ArrowUpRightIcon, TriangleAlertIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { collectionTabConfig } from '@/lib/collection-tabs';
import {
    collectionItemHref,
    distinctReasons,
    mistakeSeenIn,
    seenLabel,
} from '@/lib/collections';
import { shortDate } from '@/lib/format';
import type {
    CollectionMistakeAppearance,
    CollectionMistakeItem,
    CollectionRow,
    CollectionTabKey,
} from '@/types';

const props = withDefaults(
    defineProps<{
        item: CollectionMistakeItem | null;
        tab?: CollectionTabKey;
    }>(),
    { tab: 'mistakes' },
);

const emit = defineEmits<{
    (event: 'close'): void;
    /** The learner is leaving for a session, so the list should be kept. */
    (event: 'see-session'): void;
}>();

/** A phone gets a sheet from the bottom; a larger screen gets a side panel. */
const isWide = useMediaQuery('(min-width: 768px)');

const side = computed<'right' | 'bottom'>(() =>
    isWide.value ? 'right' : 'bottom',
);

const contentClass = computed(() =>
    side.value === 'bottom'
        ? 'h-[92dvh] max-h-[92dvh]'
        : 'w-full sm:max-w-md md:max-w-lg',
);

const config = computed(() => collectionTabConfig(props.tab));

const heading = ref<HTMLElement | null>(null);

/**
 * Move focus to the title itself, so a screen reader hears what was opened.
 * The panel's own auto-focus is prevented, and this runs after the content
 * is mounted, when the heading really exists.
 */
function focusHeading(event: Event): void {
    event.preventDefault();
    heading.value?.focus();
}

/** A different mistake opened while the panel is already showing. */
watch(
    () => props.item,
    async (item) => {
        if (item === null) {
            return;
        }

        await nextTick();
        heading.value?.focus();
    },
);

/** How many times the mistake was seen, read as words. */
const seenBadge = computed(() => seenLabel(props.item?.seen_count ?? 0));

const reasons = computed(() =>
    props.item === null ? [] : distinctReasons(props.item),
);

const sightings = computed(() =>
    props.item === null ? [] : mistakeSeenIn(props.item),
);

/**
 * The most recent appearance, so the attention marker can link to the place
 * the mistake is easiest to fix: its latest session.
 */
const newestAppearance = computed(() => props.item?.appearances[0] ?? null);

function hrefFor(appearance: CollectionMistakeAppearance): string {
    return collectionItemHref(rowOf(appearance), props.tab);
}

/**
 * A place the mistake was seen as the reading page reads it: the session,
 * the question and the exact row.
 */
function rowOf(appearance: CollectionMistakeAppearance): CollectionRow {
    return {
        session_id: appearance.session_id,
        question_position: appearance.question_position,
        entry_id: appearance.entry_id,
        entry_position: appearance.entry_position,
    };
}

function sessionLabel(appearance: CollectionMistakeAppearance): string {
    const date = appearance.session_date
        ? shortDate(appearance.session_date)
        : '';
    const pieces = [appearance.topic, date].filter((piece) => piece !== '');

    return `${pieces.join(' · ')} · Question ${appearance.question_position}`;
}

/**
 * The sessions one reason came from, named the way the "Seen in" list names
 * them, so the panel shows where each explanation was given.
 */
function reasonSittings(reason: {
    reason: string;
    appearances: CollectionMistakeAppearance[];
}): string[] {
    return reason.appearances.map(sessionLabel);
}

/**
 * Only a normal click leaves this page, so only then does the list need to be
 * remembered. A modified click opens a new tab and keeps this page as it is.
 */
function seeOriginal(event: MouseEvent): void {
    if (
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey ||
        event.button !== 0
    ) {
        return;
    }

    emit('see-session');
}
</script>

<template>
    <Sheet
        :open="props.item !== null"
        @update:open="(openValue) => (!openValue ? emit('close') : undefined)"
    >
        <SheetContent
            v-if="props.item !== null"
            :side="side"
            :class="contentClass"
            @open-auto-focus="focusHeading"
        >
            <SheetHeader class="border-b">
                <span class="flex flex-wrap items-center gap-2">
                    <SheetTitle as-child>
                        <h2
                            ref="heading"
                            tabindex="-1"
                            class="text-xl font-semibold outline-none"
                        >
                            {{ props.item.title }}
                        </h2>
                    </SheetTitle>

                    <Badge variant="outline">{{ seenBadge }}</Badge>
                    <Badge v-if="props.item.worth_practicing" variant="outline">
                        Worth practicing
                    </Badge>
                </span>

                <SheetDescription>
                    {{ config.panelDescription }}
                </SheetDescription>

                <!-- The heading area holds what the learner said and, right
                    under it, what to say instead with its own light
                    formatting. -->
                <div class="space-y-2 text-sm">
                    <div>
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ config.columns[0] }}
                        </p>
                        <p class="break-words">
                            {{ props.item.title }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ config.columns[1] }}
                        </p>
                        <p
                            v-if="props.item.correction_html"
                            class="break-words"
                            v-html="props.item.correction_html"
                        ></p>
                        <p
                            v-else-if="props.item.correction"
                            class="break-words"
                        >
                            {{ props.item.correction }}
                        </p>
                        <p v-else class="text-muted-foreground italic">
                            {{ config.missingCorrection }}
                        </p>
                    </div>
                </div>
            </SheetHeader>

            <ScrollArea class="min-h-0 flex-1">
                <div class="space-y-5 px-4 pb-6">
                    <div v-if="props.item.needs_attention">
                        <div
                            class="rounded-lg border border-dashed bg-muted/40 p-3 text-sm text-muted-foreground"
                        >
                            <span class="flex items-start gap-2">
                                <TriangleAlertIcon
                                    class="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    <span class="font-medium text-foreground">
                                        Needs attention
                                    </span>
                                    <span class="block">
                                        {{ props.item.attention_reason }}
                                        Opening the original session lets you
                                        improve it.
                                    </span>
                                </span>
                            </span>
                        </div>

                        <Link
                            v-if="newestAppearance !== null"
                            :href="hrefFor(newestAppearance)"
                            class="mt-2 flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 text-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50"
                            @click="seeOriginal"
                        >
                            <span class="text-sm font-medium">
                                Open the original session to fix it
                            </span>
                            <ArrowUpRightIcon
                                class="size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                        </Link>
                    </div>

                    <!-- One reason reads as part of the mistake itself; every
                        different reason is named with where it was given. -->
                    <div v-if="reasons.length === 1">
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ config.columns[2] }}
                        </p>
                        <p class="text-sm break-words">
                            {{ reasons[0].reason }}
                        </p>
                    </div>

                    <template v-else-if="reasons.length > 1">
                        <Separator />

                        <section aria-labelledby="collection-mistake-reasons">
                            <h3
                                id="collection-mistake-reasons"
                                class="text-sm font-medium"
                            >
                                All reasons
                            </h3>

                            <ul class="mt-3 space-y-4">
                                <li
                                    v-for="reason in reasons"
                                    :key="reason.reason"
                                    class="text-sm"
                                >
                                    <p class="break-words">
                                        {{ reason.reason }}
                                    </p>
                                    <ul
                                        class="mt-0.5 space-y-0.5 text-xs text-muted-foreground"
                                    >
                                        <li
                                            v-for="(
                                                where, index
                                            ) in reasonSittings(reason)"
                                            :key="index"
                                        >
                                            {{ where }}
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </section>
                    </template>

                    <Separator />

                    <section aria-labelledby="collection-mistake-seen-in">
                        <div
                            class="mb-2 flex items-center justify-between gap-2"
                        >
                            <h3
                                id="collection-mistake-seen-in"
                                class="text-sm font-medium"
                            >
                                Seen in
                            </h3>
                            <Badge variant="secondary">
                                {{ sightings.length }}
                            </Badge>
                        </div>

                        <ul class="grid gap-2">
                            <li
                                v-for="appearance in sightings"
                                :key="`${appearance.session_id}-${appearance.question_position}`"
                            >
                                <Link
                                    :href="hrefFor(appearance)"
                                    class="flex min-h-11 items-start justify-between gap-3 rounded-lg border p-3 transition-colors outline-none hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50"
                                    @click="seeOriginal"
                                >
                                    <span class="min-w-0">
                                        <span
                                            class="block text-sm font-medium break-words"
                                        >
                                            {{ appearance.topic }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            {{ sessionLabel(appearance) }}
                                        </span>
                                    </span>

                                    <span
                                        class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-primary"
                                    >
                                        See original session
                                        <ArrowUpRightIcon
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </section>
                </div>
            </ScrollArea>
        </SheetContent>
    </Sheet>
</template>
