<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ArrowUpRightIcon, InfoIcon } from '@lucide/vue';
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
import {
    collectionItemHref,
    distinctExamples,
    distinctMeanings,
    seenIn,
} from '@/lib/collections';
import { shortDate } from '@/lib/format';
import type {
    CollectionAppearance,
    CollectionItem,
    CollectionTabKey,
} from '@/types';

const props = withDefaults(
    defineProps<{
        item: CollectionItem | null;
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

const heading = ref<HTMLElement | null>(null);

/**
 * Move focus to the word itself, so a screen reader hears what was opened.
 * The panel's own auto-focus is prevented below, and this hook runs after the
 * content is mounted, when the heading really exists.
 */
function focusHeading(event: Event): void {
    event.preventDefault();
    heading.value?.focus();
}

/** A different word opened while the panel is already showing. */
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
    props.item === null ? [] : distinctMeanings(props.item),
);

const examples = computed(() =>
    props.item === null ? [] : distinctExamples(props.item),
);

const sightings = computed(() =>
    props.item === null ? [] : seenIn(props.item),
);

const seenLabel = computed(() => {
    const count = props.item?.seen_count ?? 0;

    return count === 1 ? 'Seen once' : `Seen ${count} times`;
});

function hrefFor(appearance: CollectionAppearance): string {
    return collectionItemHref(appearance, props.tab);
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
        @update:open="(open) => (!open ? emit('close') : undefined)"
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
                            {{ props.item.word }}
                        </h2>
                    </SheetTitle>

                    <Badge v-if="props.item.seen_count > 1" variant="outline">
                        {{ seenLabel }}
                    </Badge>
                </span>

                <SheetDescription>
                    Everything you saved for this word, gathered from your
                    finished sessions.
                </SheetDescription>
            </SheetHeader>

            <ScrollArea class="min-h-0 flex-1">
                <div class="space-y-5 px-4 pb-6">
                    <div>
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Meaning
                        </p>
                        <p v-if="props.item.meaning" class="text-sm">
                            {{ props.item.meaning }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground italic">
                            Add the meaning
                        </p>
                    </div>

                    <div v-if="props.item.example">
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Example
                        </p>
                        <p class="text-sm text-muted-foreground italic">
                            “{{ props.item.example }}”
                        </p>
                    </div>

                    <div
                        v-if="props.item.needs_attention"
                        class="rounded-lg border border-dashed bg-muted/40 p-3 text-sm text-muted-foreground"
                    >
                        <span class="flex items-start gap-2">
                            <InfoIcon
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                This word has no meaning yet. Adding one while
                                reviewing the session will fill it in here.
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
                                        Meanings
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
                                        Examples
                                    </p>
                                    <ul class="space-y-3">
                                        <li
                                            v-for="example in examples"
                                            :key="`example-${example.example}`"
                                            class="text-sm text-muted-foreground italic"
                                        >
                                            <p>“{{ example.example }}”</p>
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
