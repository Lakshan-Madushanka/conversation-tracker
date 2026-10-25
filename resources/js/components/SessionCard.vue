<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckIcon, SquarePenIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { isLaterDay, relativeTime, shortDate } from '@/lib/format';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

/** One session, as listed on Home and in the Library. */
const props = defineProps<{
    session: SessionCardData;
}>();

const isFinished = computed(() => props.session.status === 'finished');

const statusLabel = computed(() =>
    isFinished.value ? 'Finished' : 'Unfinished',
);

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
</script>

<template>
    <Card>
        <CardContent class="p-4">
            <Link
                :href="href"
                class="group flex items-start gap-3 rounded-md outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
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
                            {{ props.session.topic }}
                        </span>
                        <Badge
                            :variant="isFinished ? 'default' : 'secondary'"
                            class="shrink-0"
                        >
                            {{ statusLabel }}
                        </Badge>
                    </span>

                    <span
                        class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
                    >
                        <time
                            v-if="sessionOn !== ''"
                            :datetime="props.session.session_date ?? undefined"
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
                        :datetime="props.session.last_edited_at ?? undefined"
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
        </CardContent>
    </Card>
</template>
