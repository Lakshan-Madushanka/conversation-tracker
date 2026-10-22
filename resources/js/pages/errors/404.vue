<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SearchXIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div class="flex flex-col items-center py-16 text-center">
        <SearchXIcon
            class="mb-4 size-12 text-muted-foreground"
            aria-hidden="true"
        />

        <h1 class="text-2xl font-semibold tracking-tight">
            We couldn’t find that page
        </h1>

        <p class="mt-2 max-w-md text-sm text-muted-foreground">
            The page you were looking for doesn’t exist or may have been moved.
            Let’s get you back on track.
        </p>

        <Button size="lg" class="mt-6" as-child>
            <Link :href="home.url()">Go to Home</Link>
        </Button>
    </div>
</template>
