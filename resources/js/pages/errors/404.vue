<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { SearchXIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';
import { index as customReviewsIndex } from '@/routes/customReviews';
import { index as sessionsIndex } from '@/routes/sessions';

defineOptions({ layout: AppLayout });

/** What the learner was looking for, as far as its address tells us. */
type Missing = 'session' | 'review' | 'page';

/**
 * A missing address usually means the thing was deleted. The server only
 * knows the page is gone, so the address is read here, on the client, to name
 * what is missing and offer the way back to where it used to live.
 */
const missing = ref<Missing>('page');

onMounted(() => {
    const path = window.location.pathname;

    if (/^\/sessions\/\d+(?:\/|$)/u.test(path)) {
        missing.value = 'session';

        return;
    }

    if (/^\/custom-reviews\/\d+(?:\/|$)/u.test(path)) {
        missing.value = 'review';
    }
});

const words = computed(() => {
    if (missing.value === 'session') {
        return {
            title: 'Session not found',
            heading: 'We couldn’t find that session',
            explanation:
                'It may have been deleted. Your other sessions are still in the Library.',
            action: 'Go to Library',
            href: sessionsIndex.url(),
        };
    }

    if (missing.value === 'review') {
        return {
            title: 'Custom review not found',
            heading: 'We couldn’t find that custom review',
            explanation:
                'It may have been deleted, along with its attempts. Your items are still in their collections, and your other reviews are untouched.',
            action: 'Go to Custom reviews',
            href: customReviewsIndex.url(),
        };
    }

    return {
        title: 'Page not found',
        heading: 'We couldn’t find that page',
        explanation:
            'The page you were looking for doesn’t exist or may have been moved. Let’s get you back on track.',
        action: 'Go to Home',
        href: home.url(),
    };
});
</script>

<template>
    <div class="flex flex-col items-center py-16 text-center">
        <Head :title="words.title" />

        <SearchXIcon
            class="mb-4 size-12 text-muted-foreground"
            aria-hidden="true"
        />

        <h1 class="text-2xl font-semibold tracking-tight">
            {{ words.heading }}
        </h1>

        <p class="mt-2 max-w-md text-sm text-muted-foreground">
            {{ words.explanation }}
        </p>

        <Button size="lg" class="mt-6" as-child>
            <Link :href="words.href">{{ words.action }}</Link>
        </Button>
    </div>
</template>
