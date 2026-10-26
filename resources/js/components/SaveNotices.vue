<script setup lang="ts">
import {
    ArchiveIcon,
    ArchiveRestoreIcon,
    RefreshCwIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { AutoSaveStatus } from '@/composables/useAutoSave';
import type { PracticeSessionData } from '@/types';

const props = defineProps<{
    /** The session another tab changed first, or null when nothing blocks. */
    conflict: PracticeSessionData | null;
    /** Where saving stands, so a failing save can offer a retry. */
    status: AutoSaveStatus;
    /** Plain words about the save that did not go through. */
    failureMessage: string;
    /** What the server said when saving was refused because of an archive. */
    archivedMessage?: string;
}>();

const emit = defineEmits<{
    (event: 'loadLatest'): void;
    (event: 'keepMine'): void;
    (event: 'retry'): void;
    (event: 'restore'): void;
}>();
</script>

<template>
    <!-- The session was archived elsewhere. The text stays on screen, and
        Restore brings saving back without leaving the editor. -->
    <div
        v-if="props.status === 'archived'"
        role="alert"
        class="mb-4 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-3 text-sm"
    >
        <p class="flex items-start gap-2 font-medium">
            <ArchiveIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            This session is archived.
        </p>
        <p class="mt-1 pl-6 text-muted-foreground">
            {{
                props.archivedMessage ||
                'Your text is still here. Restore the session to keep editing.'
            }}
        </p>
        <div class="mt-3 pl-6">
            <Button type="button" size="sm" @click="emit('restore')">
                <ArchiveRestoreIcon aria-hidden="true" />
                Restore session
            </Button>
        </div>
    </div>

    <!-- A change made elsewhere stops saving until resolved in place. -->
    <div
        v-else-if="props.conflict"
        role="alert"
        class="mb-4 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-3 text-sm"
    >
        <p class="flex items-start gap-2 font-medium">
            <TriangleAlertIcon
                class="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            This session was changed somewhere else.
        </p>
        <p class="mt-1 pl-6 text-muted-foreground">
            Load the latest version to see that work, or keep the text on this
            screen and save over it.
        </p>
        <div class="mt-3 flex flex-wrap gap-2 pl-6">
            <Button type="button" size="sm" @click="emit('loadLatest')">
                Load latest version
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="emit('keepMine')"
            >
                Keep what I have
            </Button>
        </div>
    </div>

    <!-- Saving is failing; keep the text on screen and offer a retry. -->
    <div
        v-else-if="props.status === 'retrying' || props.status === 'failed'"
        role="alert"
        class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm"
    >
        <p class="flex items-start gap-2">
            <TriangleAlertIcon
                class="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            {{ props.failureMessage }}
        </p>
        <Button
            type="button"
            size="sm"
            variant="outline"
            @click="emit('retry')"
        >
            <RefreshCwIcon aria-hidden="true" />
            Retry
        </Button>
    </div>
</template>
