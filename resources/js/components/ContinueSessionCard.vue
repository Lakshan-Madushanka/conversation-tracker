<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon, HistoryIcon } from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { relativeTime } from '@/lib/format';
import { edit as sessionsEdit } from '@/routes/sessions';
import type { ContinueCardData } from '@/types';

const props = defineProps<{
    session: ContinueCardData;
}>();

const href = computed(() => sessionsEdit.url(props.session.id));
const changed = computed(() => relativeTime(props.session.updated_at));
</script>

<template>
    <Card>
        <CardContent
            class="flex flex-wrap items-center justify-between gap-3 p-4"
        >
            <div class="flex min-w-0 items-start gap-3">
                <HistoryIcon
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        Continue where you left off
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ props.session.topic }}
                    </p>
                    <p
                        v-if="changed"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Last changed {{ changed }}
                    </p>
                </div>
            </div>

            <Link
                :href="href"
                class="inline-flex shrink-0 items-center gap-1 rounded-md text-sm font-medium text-primary hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                Resume
                <ArrowRightIcon class="size-4" aria-hidden="true" />
            </Link>
        </CardContent>
    </Card>
</template>
