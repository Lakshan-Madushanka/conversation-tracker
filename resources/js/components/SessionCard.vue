<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckIcon, SquarePenIcon } from '@lucide/vue';
import HighlightedText from '@/components/HighlightedText.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import SessionMenu from '@/components/SessionMenu.vue';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { isLaterDay, relativeTime, shortDate } from '@/lib/format';
import {
    matchKindLabel,
    matchTone,
    searchMatchHref,
} from '@/lib/library-search';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

/** How many matching items a card shows before offering the rest. */
const INITIAL_MATCHES = 5;

/** One session, as listed on Home and in the Library. */
const props = withDefaults(
    defineProps<{
        session: SessionCardData;
        /** Home shows cards without actions; the Library and Archived view offer them. */
        showMenu?: boolean;
    }>(),
    { showMenu: true },
);

const emit = defineEmits<{
    (event: 'archive', session: SessionCardData): void;
    (event: 'restore', session: SessionCardData): void;
    (event: 'delete', session: SessionCardData): void;
}>();

const isFinished = computed(() => props.session.status === 'finished');

const isArchived = computed(() => props.session.archived_at !== null);

const statusLabel = computed(() =>
    isFinished.value ? 'Finished' : 'Unfinished',
);

/** An archived session is called that instead of repeating its finished state. */
const badgeLabel = computed(() =>
    isArchived.value ? 'Archived' : statusLabel.value,
);

const badgeVariant = computed<BadgeVariants['variant']>(() => {
    if (isArchived.value) {
        return 'outline';
    }

    return isFinished.value ? 'default' : 'secondary';
});

/** A finished session is read first; an unfinished one opens its editor. */
const href = computed(() =>
    isFinished.value
        ? sessionsShow.url(props.session.id)
        : sessionsEdit.url(props.session.id),
);

const sessionOn = computed(() =>
    props.session.session_date === null
        ? ''
        : shortDate(props.session.session_date),
);

/**
 * Only a later day counts as "edited": saving again on the same day the
 * session happened is not worth a second line on the card.
 */
const editedOn = computed(() => {
    const edited = props.session.last_edited_at;
    const sessionDate = props.session.session_date;

    if (edited === null || sessionDate === null) {
        return '';
    }

    return isLaterDay(edited, sessionDate) ? shortDate(edited) : '';
});

const editedAgo = computed(() =>
    props.session.last_edited_at === null
        ? ''
        : relativeTime(props.session.last_edited_at),
);

const showEdited = computed(
    () => editedOn.value !== '' && editedAgo.value !== '',
);

/** Everything the session holds, in one line that fits a phone. */
const countsLine = computed(() => {
    const counts = props.session.counts;

    return [
        `${counts.questions} ${counts.questions === 1 ? 'question' : 'questions'}`,
        `${counts.words} ${counts.words === 1 ? 'word' : 'words'}`,
        `${counts.phrases} ${counts.phrases === 1 ? 'phrase' : 'phrases'}`,
        `${counts.mistakes} ${counts.mistakes === 1 ? 'mistake' : 'mistakes'}`,
    ].join(' · ');
});

/** The topic split into plain and matching pieces while a search is on. */
const topicSegments = computed(() => props.session.topic_segments ?? null);

const matches = computed(() => props.session.matches ?? []);

/** Whether the learner has asked to see every matching item on this card. */
const showAllMatches = ref(false);

const visibleMatches = computed(() =>
    showAllMatches.value
        ? matches.value
        : matches.value.slice(0, INITIAL_MATCHES),
);

const hiddenMatchCount = computed(() =>
    Math.max(0, matches.value.length - INITIAL_MATCHES),
);

/** The server keeps the list short, so say when there is more to find. */
const cappedMatchCount = computed(() =>
    Math.max(0, (props.session.match_count ?? 0) - matches.value.length),
);

// A new search replaces the matching items, so start each card folded again.
watch(
    () => props.session.matches,
    () => {
        showAllMatches.value = false;
    },
);
</script>

<template>
    <Card :id="`session-card-${props.session.id}`">
        <CardContent class="p-4">
            <div class="flex items-start gap-2">
                <Link
                    :id="`session-link-${props.session.id}`"
                    :href="href"
                    class="group flex min-w-0 flex-1 items-start gap-3 rounded-md outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    <component
                        :is="isFinished ? CheckIcon : SquarePenIcon"
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />

                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <span
                                class="line-clamp-2 min-w-0 flex-1 text-sm font-medium break-words group-hover:text-primary"
                            >
                                <HighlightedText
                                    v-if="topicSegments !== null"
                                    :segments="topicSegments"
                                />
                                <template v-else>{{
                                    props.session.topic
                                }}</template>
                            </span>
                            <Badge :variant="badgeVariant" class="shrink-0">
                                {{ badgeLabel }}
                            </Badge>
                        </span>

                        <span
                            class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
                        >
                            <time
                                v-if="sessionOn !== ''"
                                :datetime="
                                    props.session.session_date ?? undefined
                                "
                            >
                                {{ sessionOn }}
                            </time>
                            <span v-if="sessionOn !== ''" aria-hidden="true">
                                ·
                            </span>
                            <span>{{ countsLine }}</span>
                        </span>

                        <time
                            v-if="showEdited"
                            :datetime="
                                props.session.last_edited_at ?? undefined
                            "
                            :title="`Edited ${editedOn}`"
                            class="mt-0.5 block text-xs text-muted-foreground"
                        >
                            <span>Edited {{ editedAgo }}</span>
                            <span class="sr-only">, on {{ editedOn }}</span>
                        </time>
                    </span>

                    <ArrowRightIcon
                        class="mt-1 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Link>

                <SessionMenu
                    v-if="props.showMenu"
                    :topic="props.session.topic"
                    :edit-href="
                        isArchived ? null : sessionsEdit.url(props.session.id)
                    "
                    :archived="isArchived"
                    :finished="isFinished"
                    class="shrink-0"
                    @archive="emit('archive', props.session)"
                    @restore="emit('restore', props.session)"
                    @delete="emit('delete', props.session)"
                />
            </div>

            <!-- The matching items sit beside the session link, never inside
                it, so each one is its own destination. -->
            <template v-if="matches.length > 0">
                <Separator class="my-3" />

                <ul class="space-y-1">
                    <li v-for="match in visibleMatches" :key="match.key">
                        <Link
                            :href="searchMatchHref(props.session, match)"
                            class="flex min-h-11 flex-wrap items-start gap-x-2 gap-y-1 rounded-md px-1 py-2 outline-none hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <SectionLabel :tone="matchTone(match)">
                                {{ matchKindLabel(match) }}
                            </SectionLabel>

                            <Badge
                                v-if="match.question_position !== null"
                                variant="outline"
                                class="shrink-0"
                            >
                                Question {{ match.question_position }}
                            </Badge>

                            <span
                                class="min-w-0 flex-1 text-sm leading-relaxed break-words text-muted-foreground"
                            >
                                <HighlightedText :segments="match.segments" />
                            </span>
                        </Link>
                    </li>
                </ul>

                <Button
                    v-if="hiddenMatchCount > 0 && !showAllMatches"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="mt-1"
                    @click="showAllMatches = true"
                >
                    Show {{ hiddenMatchCount }} more
                    {{ hiddenMatchCount === 1 ? 'item' : 'items' }}
                </Button>

                <p
                    v-if="cappedMatchCount > 0"
                    class="mt-1 px-1 text-xs text-muted-foreground"
                >
                    Showing the first {{ matches.length }} matching items. There
                    are {{ cappedMatchCount }} more in this session.
                </p>
            </template>
        </CardContent>
    </Card>
</template>
