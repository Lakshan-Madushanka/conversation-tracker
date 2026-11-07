<script setup lang="ts">
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <Card class="py-0" aria-hidden="true">
        <div class="grid gap-4 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="grid w-full max-w-sm gap-2">
                    <Skeleton class="h-5 w-2/3" />
                    <Skeleton class="h-4 w-full" />
                </div>
                <Skeleton class="h-5 w-16 rounded-full" />
            </div>

            <div class="flex flex-wrap gap-3">
                <Skeleton class="h-4 w-16" />
                <Skeleton class="h-4 w-24" />
            </div>

            <div class="grid gap-2">
                <Skeleton class="h-4 w-28" />
                <Skeleton class="h-4 w-40" />
            </div>

            <Skeleton class="h-9 w-full max-w-xs" />
        </div>
    </Card>
</template>
