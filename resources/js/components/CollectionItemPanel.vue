<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ArrowUpRightIcon, TriangleAlertIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
    collectionNoteHref,
    distinctExamples,
    distinctMeanings,
    isCollectionItem,
    isCollectionNoteItem,
    seenIn,
} from '@/lib/collections';
import { shortDate } from '@/lib/format';
import type {
    CollectionAppearance,
    CollectionItem,
    CollectionNoteItem,
    CollectionOpenedItem,
    CollectionRow,
    CollectionTabKey,
} from '@/types';

const props = withDefaults(
    defineProps<{
        item: CollectionOpenedItem | null;
        tab?: CollectionTabKey;
    }>(),
    { tab: 'vocabulary' },
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

/** The opened panel can hold a word, a phrase, or one grammar note. */
const wordItem = computed(() =>
    isCollectionItem(props.item) ? (props.item as CollectionItem) : null,
);

const noteItem = computed(() =>
    isCollectionNoteItem(props.item)
        ? (props.item as CollectionNoteItem)
        : null,
);

const heading = ref<HTMLElement | null>(null);

/**
 * Move focus to the title itself, so a screen reader hears what was opened.
 * The panel's own auto-focus is prevented below, and this hook runs after the
 * content is mounted, when the heading really exists.
 */
function focusHeading(event: Event): void {
    event.preventDefault();
    heading.value?.focus();
}

/** A different item opened while the panel is already showing. */
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

const meanings = computed(() =>
    wordItem.value === null ? [] : distinctMeanings(wordItem.value),
);

const examples = computed(() =>
    wordItem.value === null ? [] : distinctExamples(wordItem.value),
);

const sightings = computed(() =>
    wordItem.value === null ? [] : seenIn(wordItem.value),
);

const seenLabel = computed(() => {
    const count = wordItem.value?.seen_count ?? 0;

    return count === 1 ? 'Seen once' : `Seen ${count} times`;
});

function hrefFor(appearance: CollectionAppearance): string {
    return collectionItemHref(rowOf(appearance), props.tab);
}

function hrefForNote(note: CollectionNoteItem): string {
    return collectionNoteHref(note);
}

/**
 * A row of the collection as the reading page reads it: the session, the
 * question, and the exact entry.
 */
function rowOf(appearance: CollectionAppearance): CollectionRow {
    return {
        session_id: appearance.session_id,
        question_position: appearance.question_position,
        entry_id: appearance.entry_id,
        entry_position: appearance.entry_position,
    };
}

function sessionLabel(appearance: CollectionAppearance): string {
    const date = appearance.session_date
        ? shortDate(appearance.session_date)
        : '';
    const pieces = [appearance.topic, date].filter((piece) => piece !== '');

    return `${pieces.join(' · ')} · Question ${appearance.question_position}`;
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
                <template v-if="wordItem !== null">
                    <span class="flex flex-wrap items-center gap-2">
                        <SheetTitle as-child>
                            <h2
                                ref="heading"
                                tabindex="-1"
                                class="text-xl font-semibold outline-none"
                            >
                                {{ wordItem.title }}
                            </h2>
                        </SheetTitle>

                        <Badge v-if="wordItem.seen_count > 1" variant="outline">
                            {{ seenLabel }}
                        </Badge>
                    </span>

                    <SheetDescription>
                        {{ config.panelDescription }}
                    </SheetDescription>
                </template>

                <template v-else-if="noteItem !== null">
                    <span class="flex flex-wrap items-center gap-2">
                        <SheetTitle as-child>
                            <h2
                                ref="heading"
                                tabindex="-1"
                                class="text-xl font-semibold outline-none"
                            >
                                Grammar note
                            </h2>
                        </SheetTitle>
                    </span>

                    <SheetDescription>
                        {{ config.panelDescription }}
                    </SheetDescription>
                </template>
            </SheetHeader>

            <ScrollArea class="min-h-0 flex-1">
                <!-- A note reads as prose, its saved formatting kept. -->
                <div v-if="noteItem !== null" class="space-y-5 px-4 pb-6">
                    <div
                        class="text-sm leading-relaxed [&_li]:marker:text-muted-foreground [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-6"
                        v-html="noteItem.html"
                    ></div>

                    <Separator />

                    <section
                        aria-labelledby="collection-note-seen-in"
                        class="space-y-2"
                    >
                        <h3
                            id="collection-note-seen-in"
                            class="text-sm font-medium"
                        >
                            Where it came from
                        </h3>

                        <p class="text-sm">
                            <span class="font-medium">{{
                                noteItem.topic
                            }}</span>
                            <span
                                v-if="noteItem.session_date"
                                class="text-muted-foreground"
                            >
                                · {{ shortDate(noteItem.session_date) }}
                            </span>
                        </p>

                        <p class="text-xs text-muted-foreground">
                            Question {{ noteItem.question_position }}
                        </p>

                        <Link
                            :href="hrefForNote(noteItem)"
                            class="flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 transition-colors outline-none hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50"
                            @click="seeOriginal"
                        >
                            <span class="min-w-0 text-sm font-medium">
                                See original session
                            </span>
                            <ArrowUpRightIcon
                                class="size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                        </Link>
                    </section>
                </div>

                <div v-else-if="wordItem !== null" class="space-y-5 px-4 pb-6">
                    <div>
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Meaning
                        </p>
                        <p v-if="wordItem.meaning" class="text-sm">
                            {{ wordItem.meaning }}
                        </p>
                        <p
                            v-else-if="config.missingMeaning"
                            class="text-sm text-muted-foreground italic"
                        >
                            {{ config.missingMeaning }}
                        </p>
                    </div>

                    <div v-if="wordItem.example">
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Example
                        </p>
                        <!-- The example's own bold and italic are kept; the
                            server has already reduced what was saved to the
                            formatting the app supports. -->
                        <p
                            v-if="
                                props.tab === 'phrases' && wordItem.example_html
                            "
                            class="text-sm text-muted-foreground italic"
                        >
                            “<span v-html="wordItem.example_html"></span>”
                        </p>
                        <p v-else class="text-sm text-muted-foreground italic">
                            “{{ wordItem.example }}”
                        </p>
                    </div>

                    <div
                        v-if="wordItem.needs_attention"
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
                                    {{ wordItem.attention_reason }} Opening the
                                    original session lets you fix it.
                                </span>
                            </span>
                        </span>
                    </div>

                    <template
                        v-if="
                            meanings.length > 1 ||
                            examples.length > 1 ||
                            (meanings.length > 0 && examples.length > 0)
                        "
                    >
                        <Separator />

                        <Collapsible default-open>
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between gap-2 rounded-md text-left text-sm font-medium focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <span>All meanings and examples</span>
                                <Badge variant="secondary">
                                    {{ meanings.length + examples.length }}
                                </Badge>
                            </CollapsibleTrigger>

                            <CollapsibleContent class="mt-3 space-y-4">
                                <div v-if="meanings.length > 0">
                                    <p
                                        class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ config.columns[1] }}
                                    </p>
                                    <ul class="space-y-3">
                                        <li
                                            v-for="meaning in meanings"
                                            :key="`meaning-${meaning.meaning}`"
                                            class="text-sm"
                                        >
                                            <p>{{ meaning.meaning }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-muted-foreground"
                                            >
                                                {{ sessionLabel(meaning) }}
                                            </p>
                                        </li>
                                    </ul>
                                </div>

                                <div v-if="examples.length > 0">
                                    <p
                                        class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ config.columns[2] }}
                                    </p>
                                    <ul class="space-y-3">
                                        <li
                                            v-for="example in examples"
                                            :key="`example-${example.example}`"
                                            class="text-sm text-muted-foreground italic"
                                        >
                                            <p>
                                                “
                                                <span
                                                    v-if="
                                                        props.tab ===
                                                            'phrases' &&
                                                        example.example_html
                                                    "
                                                    v-html="
                                                        example.example_html
                                                    "
                                                ></span>
                                                <template v-else>{{
                                                    example.example
                                                }}</template>
                                                ”
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs not-italic"
                                            >
                                                {{ sessionLabel(example) }}
                                            </p>
                                        </li>
                                    </ul>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </template>

                    <Separator />

                    <section aria-labelledby="collection-seen-in">
                        <div
                            class="mb-2 flex items-center justify-between gap-2"
                        >
                            <h3
                                id="collection-seen-in"
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
