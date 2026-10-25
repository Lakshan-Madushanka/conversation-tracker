<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { LoaderCircleIcon, PlusIcon, TriangleAlertIcon } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SessionCard from '@/components/SessionCard.vue';
import SessionCardSkeleton from '@/components/SessionCardSkeleton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { create as sessionsCreate } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

/** The cards loaded so far, with the states that explain how loading is going. */
const props = withDefaults(
    defineProps<{
        sessions: SessionCardData[];
        hasMore?: boolean;
        loading?: boolean;
        error?: boolean;
        total?: number;
    }>(),
    {
        hasMore: false,
        loading: false,
        error: false,
        total: 0,
    },
);

const emit = defineEmits<{
    (event: 'load-more'): void;
}>();

/** A short row of placeholders while the next page arrives. */
const skeletonCount = 3;

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

/**
 * Watches the end of the list, so scrolling near it starts the next page.
 * The "Load more" button stays available for anyone who prefers it, and for
 * browsers without IntersectionObserver.
 */
function observeSentinel(): void {
    observer?.disconnect();
    observer = null;

    if (
        sentinel.value === null ||
        typeof IntersectionObserver === 'undefined'
    ) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (
                !props.loading &&
                entries.some((entry) => entry.isIntersecting)
            ) {
                emit('load-more');
            }
        },
        { rootMargin: '400px 0px' },
    );

    observer.observe(sentinel.value);
}

onMounted(observeSentinel);

watch(
    () => props.hasMore,
    async () => {
        await nextTick();
        observeSentinel();
    },
);

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div>
        <div
            v-if="props.sessions.length > 0"
            class="grid gap-3"
            :aria-busy="props.loading"
        >
            <SessionCard
                v-for="session in props.sessions"
                :key="session.id"
                :session="session"
            />

            <SessionCardSkeleton
                v-for="skeleton in props.loading ? skeletonCount : 0"
                :key="`skeleton-${skeleton}`"
            />
        </div>

        <div v-else-if="props.loading" class="grid gap-3" aria-hidden="true">
            <SessionCardSkeleton
                v-for="skeleton in skeletonCount"
                :key="skeleton"
            />
        </div>

        <EmptyState
            v-else-if="!props.error"
            title="No sessions yet"
            description="Start your first practice session and it will show up here."
        >
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="sessionsCreate.url()">
                        <PlusIcon aria-hidden="true" />
                        Start your first session
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <Alert v-if="props.error" variant="destructive" class="mt-4">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Could not load more sessions</AlertTitle>
            <AlertDescription>
                <p>
                    Your saved sessions are still here. Check your connection
                    and try again.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    @click="emit('load-more')"
                >
                    Try again
                </Button>
            </AlertDescription>
        </Alert>

        <div
            v-if="props.sessions.length > 0"
            class="mt-5 flex flex-col items-center gap-3"
        >
            <Button
                v-if="props.hasMore"
                type="button"
                variant="outline"
                :disabled="props.loading"
                @click="emit('load-more')"
            >
                <LoaderCircleIcon
                    v-if="props.loading"
                    class="animate-spin"
                    aria-hidden="true"
                />
                {{ props.loading ? 'Loading…' : 'Load more sessions' }}
            </Button>

            <p v-else class="text-xs text-muted-foreground">
                That is every session — {{ props.total }} in total.
            </p>

            <p
                v-if="props.hasMore"
                class="text-xs text-muted-foreground"
                aria-live="polite"
            >
                Showing {{ props.sessions.length }} of {{ props.total }}
            </p>
        </div>

        <div
            v-if="props.hasMore"
            ref="sentinel"
            class="h-px"
            aria-hidden="true"
        />
    </div>
</template>
