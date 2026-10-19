<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckIcon } from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { shortDate } from '@/lib/format';
import { edit as sessionsEdit } from '@/routes/sessions';
import type { RecentSessionData } from '@/types';

/** One finished session on the Home page, openable in the session editor. */
const props = defineProps<{
    session: RecentSessionData;
}>();

const href = computed(() => sessionsEdit.url(props.session.id));

const finishedOn = computed(() =>
    props.session.finished_at === null
        ? ''
        : shortDate(props.session.finished_at),
);

/** Everything the session holds, in one line that fits a phone. */
const countsLine = computed(() => {
    const counts = props.session.counts;

    return [
        `${counts.questions} ${counts.questions === 1 ? 'question' : 'questions'}`,
        `${counts.words} ${counts.words === 1 ? 'word' : 'words'}`,
        `${counts.phrases} ${counts.phrases === 1 ? 'phrase' : 'phrases'}`,
        `${counts.mistakes} ${counts.mistakes === 1 ? 'mistake' : 'mistakes'}`,
        `${counts.grammar_notes} ${
            counts.grammar_notes === 1 ? 'note' : 'notes'
        }`,
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
                <CheckIcon
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />

                <span class="min-w-0 flex-1">
                    <span
                        class="block truncate text-sm font-medium group-hover:text-primary"
                    >
                        {{ props.session.topic }}
                    </span>
                    <span class="mt-1 block text-xs text-muted-foreground">
                        {{ countsLine }}
                    </span>
                    <span
                        v-if="finishedOn !== ''"
                        class="mt-0.5 block text-xs text-muted-foreground"
                    >
                        Finished {{ finishedOn }}
                    </span>
                </span>

                <ArrowRightIcon
                    class="mt-1 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </CardContent>
    </Card>
</template>
