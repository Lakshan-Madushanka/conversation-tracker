<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { SearchXIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';
import { index as sessionsIndex } from '@/routes/sessions';

defineOptions({ layout: AppLayout });

/**
 * A session address that no longer exists usually means the session was
 * deleted. The server only knows the page is missing, so the address is read
 * here, on the client, to offer the right words and the way back to the
 * Library.
 */
const sessionMissing = ref(false);

onMounted(() => {
    sessionMissing.value = /^\/sessions\/\d+(?:\/|$)/u.test(
        window.location.pathname,
    );
});
</script>

<template>
    <div class="flex flex-col items-center py-16 text-center">
        <Head
            :title="sessionMissing ? 'Session not found' : 'Page not found'"
        />

        <SearchXIcon
            class="mb-4 size-12 text-muted-foreground"
            aria-hidden="true"
        />

        <h1 class="text-2xl font-semibold tracking-tight">
            {{
                sessionMissing
                    ? 'We couldn’t find that session'
                    : 'We couldn’t find that page'
            }}
        </h1>

        <p class="mt-2 max-w-md text-sm text-muted-foreground">
            {{
                sessionMissing
                    ? 'It may have been deleted. Your other sessions are still in the Library.'
                    : 'The page you were looking for doesn’t exist or may have been moved. Let’s get you back on track.'
            }}
        </p>

        <Button size="lg" class="mt-6" as-child>
            <Link :href="sessionMissing ? sessionsIndex.url() : home.url()">
                {{ sessionMissing ? 'Go to Library' : 'Go to Home' }}
            </Link>
        </Button>
    </div>
</template>
