<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <Card aria-hidden="true">
        <CardContent class="p-4">
            <div class="flex items-start gap-3">
                <Skeleton class="mt-0.5 size-4 rounded-full" />

                <div class="min-w-0 flex-1 space-y-2">
                    <Skeleton class="h-4 w-2/3" />
                    <Skeleton class="h-3 w-1/2" />
                    <Skeleton class="h-3 w-1/3" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
