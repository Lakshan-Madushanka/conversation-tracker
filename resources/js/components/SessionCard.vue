<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CalendarIcon } from '@lucide/vue';
import type { SessionSummary } from '@/lib/sample-data';
import { show as sessionsShow } from '@/routes/sessions';

const props = defineProps<{
    session: SessionSummary;
}>();

const href = computed(() => sessionsShow.url(props.session.id));

const counts = computed(
    () =>
        `${props.session.questions} questions · ${props.session.words} words · ${props.session.mistakes} mistakes`,
);
</script>

<template>
    <Link
        :href="href"
        class="group block rounded-xl border bg-card p-4 transition-colors hover:border-primary/40 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
    >
        <div class="flex items-start justify-between gap-3 text-balance">
            <h3
                class="text-base font-semibold transition-colors group-hover:text-primary"
            >
                {{ session.topic }}
            </h3>
            <span
                class="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground"
            >
                <CalendarIcon class="size-3.5" aria-hidden="true" />
                {{ session.date }}
            </span>
        </div>

        <p class="mt-1 text-sm text-muted-foreground">
            {{ session.preview }}
        </p>

        <p class="mt-3 text-xs text-muted-foreground">
            {{ counts }}
        </p>
    </Link>
</template>
